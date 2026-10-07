<?php

namespace App\Http\Controllers;

use App\Http\Requests\CatalogOrderRules;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderImage;
use App\Models\Product;
use App\Services\CatalogPricingService;
use App\Services\OrderDraftService;
use App\Services\OrderService;
use App\Services\OrderReviewService;
use App\Services\PackageDraftService;
use App\Services\StaffAccess;
use App\Support\PhilippineContact;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class OrderController extends Controller
{
    public function __construct(
        protected OrderService $orderService
    ) {}

    /**
     * Display a listing of orders with status filtering and search.
     */
    public function index(Request $request): View
    {
        $query = Order::with(['customer', 'user', 'orderDetails.product', 'orderDetails.addOns', 'payments', 'paymentProofs', 'images'])
            ->latest();

        if ($request->filled('status')) {
            if ($request->status === 'awaiting_deposit' || $request->status === 'deposit') {
                $query->workflowQueue('deposit');
            } else {
                $query->where('status', $request->status);
            }
        }
        if ($request->filled('queue')) {
            $request->validate(['queue' => ['required', Rule::in(['review', 'deposit', 'receipts', 'booked'])]]);
            $query->workflowQueue($request->string('queue')->toString());
        }

        if ($request->filled('origin')) {
            if ($request->origin === 'public') {
                $query->whereNull('user_id');
            } elseif ($request->origin === 'staff') {
                $query->whereNotNull('user_id');
            }
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('order_number', 'like', "%{$search}%")
                    ->orWhereHas('customer', function ($cq) use ($search) {
                        $cq->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%")
                            ->orWhere('phone_number', 'like', "%{$search}%");
                    });
            });
        }

        if ($request->filled('date_from')) {
            $query->whereDate('pickup_date', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('pickup_date', '<=', $request->date_to);
        }

        $orders = $query->paginate(15)->withQueryString();

        return view('admin.orders.index', compact('orders'));
    }

    /**
     * Show form for staff to manually create an order.
     */
    public function create(Request $request, OrderDraftService $drafts): View
    {
        $customers = Customer::orderBy('last_name')->get();
        $products = Product::active()->whereHas('options', fn ($query) => $query->where('is_active', true))
            ->with(['options' => fn ($query) => $query->where('is_active', true)->with('includedItems'), 'addOns' => fn ($query) => $query->where('is_active', true)])
            ->orderBy('product_name')->get();

        $draft = $drafts->start($request);
        $workspace = app(PackageDraftService::class);
        $draftLines = $this->draftLines($draft, $products, $workspace);
        $drafts->put($request, true, $draft);
        $context = $workspace->context($request, true, $draft);
        $packageLinks = [];
        foreach ($products as $product) {
            $packageLinks[$product->id] = route('orders.package.customize', ['product' => $product,
                'line' => $workspace->issueLine($request, $product), 'draft_id' => $draft['draft_id']]);
        }
        return view('admin.orders.create', compact('products', 'draft', 'draftLines', 'context', 'packageLinks'));
    }

    private function draftLines(array &$draft, $products, PackageDraftService $workspace): array
    {
        $lines = [];
        foreach ($draft['items'] as $index => $item) {
            $error = null;
            $quote = null;
            try {
                $quote = app(CatalogPricingService::class)->quote([$item]);
                $signature = $workspace->signature($quote['lines'][0]);
                if (isset($item['catalog_signature']) && $item['catalog_signature'] !== $signature) {
                    $error = 'The catalog changed. Edit this package to review its current prices and inclusions.';
                } else {
                    $draft['items'][$index]['catalog_signature'] = $signature;
                }
            } catch (ValidationException $exception) {
                $error = collect($exception->errors())->flatten()->first();
            }
            $lines[] = ['item' => $item, 'quote' => $quote, 'error' => $error,
                'product' => $products->firstWhere('id', $item['product_id']) ?? Product::find($item['product_id'])];
        }
        return $lines;
    }

    private function requireDraft(Request $request, OrderDraftService $drafts): array
    {
        $request->validate(['draft_id' => ['required', 'uuid']]);
        $draft = $drafts->get($request, true);
        abort_unless($draft && hash_equals($draft['draft_id'], (string) $request->input('draft_id')), 404);
        return $draft;
    }

    public function customize(Request $request, Product $product, string $line, OrderDraftService $drafts, PackageDraftService $workspace)
    {
        $draft = $this->requireDraft($request, $drafts);
        if (isset($draft['removed_lines'][$line])) return redirect()->route('orders.create')->with('success', 'This package line was removed. Select a package to add a new line.');
        $product->load(['options' => fn ($q) => $q->where('is_active', true)->with('includedItems'),
            'addOns' => fn ($q) => $q->where('is_active', true)]);
        $editor = $workspace->editor($request, $product, $line, true);
        $editor['staged_images'] = $workspace->imagesForBrowser($request, $editor, true);
        return view('admin.orders.customize', ['products' => collect([$product]), 'product' => $product,
            'line' => $line, 'editor' => $editor, 'context' => $workspace->context($request, true, $draft)]);
    }

    public function savePackage(Request $request, Product $product, string $line, OrderDraftService $drafts, PackageDraftService $workspace, CatalogPricingService $pricing)
    {
        $this->requireDraft($request, $drafts);
        $product->load('options');
        $workspace->save($request, $product, $line, $pricing, true);
        return redirect()->route('orders.create', ['saved_line' => $line])->with('success', 'Package saved to the staff order.');
    }

    public function saveEditor(Request $request, Product $product, string $line, OrderDraftService $drafts, PackageDraftService $workspace)
    {
        $this->requireDraft($request, $drafts);
        $product->load('options');
        $editor = $workspace->remember($request, $product, $line, true);
        return response()->json(['staged_images' => $workspace->imagesForBrowser($request, $editor, true)]);
    }

    public function removePackage(Request $request, string $line, OrderDraftService $drafts, PackageDraftService $workspace)
    {
        $this->requireDraft($request, $drafts);
        $workspace->remove($request, $line, true);
        return redirect()->route('orders.create')->with('success', 'Package removed from the staff order.')->with('removed_staff_line', $line);
    }

    public function draftImage(Request $request, string $draft, string $line, string $image, OrderDraftService $drafts)
    {
        $current = $drafts->get($request, true);
        abort_unless($current && hash_equals($current['draft_id'], $draft) && !isset($current['removed_lines'][$line]), 404);
        $saved = collect($current['items'])->firstWhere('draft_key', $line);
        $images = array_merge($saved['staged_images'] ?? [], $current['package_editors'][$line]['staged_images'] ?? []);
        $reference = collect($images)->firstWhere('image_id', $image);
        $prefix = $current['owner_id'].'/'.$current['scope'].'/'.$draft.'/'.$line.'/';
        abort_unless($reference && str_starts_with($reference['staged_path'], $prefix)
            && Storage::disk('staff_drafts')->exists($reference['staged_path']), 404);
        return Storage::disk('staff_drafts')->response($reference['staged_path']);
    }

    public function referenceImage(Order $order, OrderImage $image)
    {
        abort_unless((int) $image->order_id === $order->id && str_starts_with($image->file_path, 'staff/'.$order->id.'/')
            && Storage::disk('staff_references')->exists($image->file_path), 404);
        return Storage::disk('staff_references')->response($image->file_path);
    }

    public function saveDetailsDraft(Request $request, OrderDraftService $drafts)
    {
        $this->requireDraft($request, $drafts);
        $request->validate(['customer_id' => ['nullable', 'integer'], 'pickup_date' => ['nullable', 'string', 'max:100'],
            'pickup_time' => ['nullable', 'string', 'max:100'], 'notes_text' => ['nullable', 'string', 'max:2000'],
            'customer_picker' => ['nullable', 'array:first_name,middle_name,last_name,phone_number,search,showAdd'],
            'customer_picker.*' => ['nullable', 'string', 'max:500']]);
        $drafts->saveDetails($request, true);
        return response()->json(['saved' => true]);
    }

    public function quote(Request $request, OrderDraftService $drafts, CatalogPricingService $pricing)
    {
        $this->requireDraft($request, $drafts);
        $data = $request->validate(CatalogOrderRules::items());
        return response()->json(DB::transaction(fn () => $pricing->quote($data['items'])));
    }

    public function saveSelection(Request $request, OrderDraftService $drafts, CatalogPricingService $pricing): RedirectResponse
    {
        $drafts->save($request, true, $pricing);

        return redirect()->route('orders.details');
    }

    public function details(Request $request, OrderDraftService $drafts, CatalogPricingService $pricing)
    {
        $draft = $drafts->get($request, true);
        if (! $draft || empty($draft['items'])) {
            return redirect()->route('orders.create');
        }
        $workspace = app(PackageDraftService::class);
        $lines = $this->draftLines($draft, collect(), $workspace);
        $drafts->put($request, true, $draft);
        if (collect($lines)->contains(fn ($line) => $line['error'])) {
            return redirect()->route('orders.create')->withErrors(['items' => 'Review the affected packages before continuing. Your customer and pickup details are kept.']);
        }
        $quote = $pricing->quote($draft['items']);
        $customers = Customer::orderBy('last_name')->get();
        $context = $workspace->context($request, true, $draft);
        return view('admin.orders.details', compact('draft', 'quote', 'customers', 'context'));
    }

    public function backToSelection(Request $request, OrderDraftService $drafts): RedirectResponse
    {
        $drafts->saveDetails($request, true);

        return redirect()->route('orders.create');
    }

    public function inlineCustomer(Request $request)
    {
        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'phone_number' => PhilippineContact::rules(),
        ]);
        $customer = Customer::findOrCreateMatching($data);

        return response()->json(['id' => $customer->id, 'full_name' => $customer->full_name, 'phone_number' => $customer->phone_number]);
    }

    /**
     * Store an internally created order. Strictly requires authenticated staff user_id.
     */
    public function store(Request $request, OrderDraftService $drafts): RedirectResponse
    {
        $staffUser = StaffAccess::requireOwner($request->user());
        $draft = $drafts->get($request, true);
        $submissionKey = $drafts->submissionKey($request, $draft);
        if ($existing = Order::where('submission_key', $submissionKey)->where('user_id', $staffUser->id)->first()) {
            return redirect()->route('orders.show', $existing)
                ->with('completed_staff_browser_prefix', $drafts->submissionBrowserPrefix($request, $draft));
        }
        if (collect($request->input('items', []))->contains(fn ($item) => is_array($item) && array_key_exists('draft_key', $item))) {
            throw ValidationException::withMessages(['items' => 'Package lines must be saved on the customization page. Return to the saved staff order before creating it.']);
        }
        if ($request->has('draft_id')) {
            $this->requireDraft($request, $drafts);
            if ($request->has('items') || empty($draft['items'])) {
                throw ValidationException::withMessages(['items' => 'Save packages to the staff order before creating it. Package changes must be saved on the customization page.']);
            }
        }
        $draft ??= $drafts->get($request, true);
        $drafts->saveDetails($request, true);
        $hasDraftItems = !empty($draft['items']);
        $rules = ($hasDraftItems ? array_diff_key(CatalogOrderRules::order(), CatalogOrderRules::items()) : CatalogOrderRules::order()) + ['customer_id' => ['required', 'exists:customers,id']];
        $validated = $request->validate($rules);
        if ($hasDraftItems) {
            $validated['items'] = $drafts->itemsForOrder($draft);
        }

        $items = $validated['items'];
        foreach ($items as $index => &$item) {
            if (!$hasDraftItems && $request->hasFile("items.{$index}.images")) {
                $item['images'] = $request->file("items.{$index}.images");
            }
        }
        unset($item);

        try {
            $order = DB::transaction(function () use ($draft, $hasDraftItems, $validated, $items, $submissionKey, $request, $staffUser) {
                if ($hasDraftItems) {
                    $quote = app(CatalogPricingService::class)->quote($draft['items']);
                    foreach ($quote['lines'] as $index => $line) {
                        if (isset($draft['items'][$index]['catalog_signature']) && $draft['items'][$index]['catalog_signature'] !== app(PackageDraftService::class)->signature($line)) {
                            throw ValidationException::withMessages(['items' => 'A catalog price or inclusion changed. Edit the affected package before creating the order.']);
                        }
                    }
                }
                return $this->orderService->createInternalOrder([
            'customer_id' => $validated['customer_id'],
            'pickup_date' => $validated['pickup_date'],
            'pickup_time' => $validated['pickup_time'],
            'notes_text' => $validated['notes_text'] ?? null,
            'items' => $items,
            'expected_total' => $validated['expected_total'],
            'images' => $request->file('images', []),
            'submission_key' => $submissionKey,
                ], $staffUser);
            });
        } catch (UniqueConstraintViolationException $exception) {
            $order = Order::where('submission_key', $submissionKey)->where('user_id', $staffUser->id)->first();
            if (!$order) throw $exception;
        } catch (ValidationException $exception) {
            if ($hasDraftItems && collect(array_keys($exception->errors()))->contains(fn ($key) => $key === 'items' || str_starts_with($key, 'items.'))) {
                return redirect()->route('orders.create')->withErrors($exception->errors())->withInput();
            }
            throw $exception;
        }

        if ($draft) {
            $drafts->finish($request, true);
        }

        return redirect()->route('orders.show', $order)
            ->with('success', "Order {$order->order_number} created. Record the Cash or GCash deposit to secure the booking.")
            ->with('completed_staff_browser_prefix', $draft ? app(PackageDraftService::class)->context($request, true, $draft)['browser_prefix'] : null);
    }

    /**
     * Detailed order review page (fixed-price snapshots, customizations, payments, lifecycle).
     */
    public function show(Request $request, Order $order): View|JsonResponse
    {
        $order->load([
            'customer',
            'user',
            'orderDetails.product',
            'orderDetails.images',
            'images',
            'payments.user',
            'orderDetails.addOns',
            'paymentProofs.reviewer',
            'reviewer',
        ]);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'order' => [
                    'id' => $order->id,
                    'order_number' => $order->order_number,
                    'status' => $order->status,
                    'workflow_label' => $order->workflowLabel(),
                    'payment_status' => $order->payment_status,
                    'is_public' => $order->user_id === null,
                    'origin_label' => $order->user_id === null ? 'Public Web' : 'Staff (' . ($order->user->first_name ?? 'Staff') . ')',
                    'customer_name' => $order->customer->full_name,
                    'customer_phone' => $order->customer->phone_number,
                    'pickup_date' => $order->pickup_date->format('M d, Y'),
                    'pickup_time' => \Carbon\Carbon::parse($order->pickup_time)->format('h:i A'),
                    'total_amount' => (float) $order->total_amount,
                    'formatted_total' => number_format($order->total_amount, 2),
                    'amount_paid' => (float) $order->amount_paid,
                    'formatted_paid' => number_format($order->amount_paid, 2),
                    'remaining_balance' => (float) $order->remaining_balance,
                    'formatted_balance' => number_format($order->remaining_balance, 2),
                    'required_down_payment' => (float) $order->required_down_payment,
                    'formatted_down_payment' => number_format($order->required_down_payment, 2),
                    'notes_text' => $order->notes_text,
                    'created_at' => $order->created_at->format('M d, Y h:i A'),
                    'completed_at' => $order->completed_at?->format('M d, Y h:i A'),
                    'cancelled_at' => $order->cancelled_at?->format('M d, Y h:i A'),
                    'show_url' => route('orders.show', $order),
                    'has_unreviewed_proof' => $order->paymentProofs->where('status', 'awaiting_verification')->isNotEmpty(),
                    'needs_review' => $order->needsStaffReview(),
                    'items_count' => $order->orderDetails->sum('quantity'),
                    'items' => $order->orderDetails->map(function ($detail) {
                        return [
                            'id' => $detail->id,
                            'product_name' => $detail->product_name_snapshot ?? ($detail->product->product_name ?? 'Package Item'),
                            'quantity' => $detail->quantity,
                            'unit_price' => number_format($detail->unit_price, 2),
                            'subtotal' => number_format($detail->subtotal, 2),
                            'photo_url' => $detail->product?->photo_path ? asset('storage/' . $detail->product->photo_path) : null,
                            'layers' => $detail->layers,
                            'themes' => $detail->themes,
                            'special_request' => $detail->special_request,
                            'add_ons' => $detail->addOns->filter(fn ($a) => (int) $a->quantity > 0)->map(fn ($addOn) => [
                                'name' => $addOn->name_snapshot ?? ($addOn->addOn?->name ?? 'Extra'),
                                'quantity' => $addOn->quantity,
                                'price' => number_format((float) $addOn->unit_price, 2),
                            ])->values(),
                        ];
                    })->values(),
                    'images' => $order->images->map(fn ($img) => [
                        'id' => $img->id,
                        'url' => $img->url(),
                    ])->values(),
                    'payments' => $order->payments->map(function ($payment) {
                        return [
                            'id' => $payment->id,
                            'amount' => number_format($payment->amount, 2),
                            'payment_method' => ucfirst(str_replace('_', ' ', $payment->payment_method ?? 'Cash')),
                            'payment_type' => ucfirst(str_replace('_', ' ', $payment->payment_type ?? 'Payment')),
                            'reference_number' => $payment->reference_number,
                            'recorded_by' => $payment->user?->first_name,
                            'paid_at' => $payment->paid_at ? \Carbon\Carbon::parse($payment->paid_at)->format('M d, Y h:i A') : $payment->created_at?->format('M d, Y h:i A'),
                        ];
                    })->values(),
                    'proofs' => $order->paymentProofs->map(function ($proof) use ($order) {
                        return [
                            'id' => $proof->id,
                            'status' => $proof->status,
                            'reference_number' => $proof->reference_number,
                            'rejection_reason' => $proof->rejection_reason,
                            'review_url' => route('orders.show', $order) . '#proof-review',
                            'receipt_url' => route('proofs.receipt', $proof),
                            'uploaded_at' => $proof->created_at->format('M d, Y h:i A'),
                        ];
                    })->values(),
                ],
            ]);
        }

        $otherRequests = Order::whereDate('pickup_date', $order->pickup_date->toDateString())->whereKeyNot($order->id);
        $pickupContext = ['booked' => (clone $otherRequests)->workflowQueue('booked')->count(),
            'awaitingDeposit' => (clone $otherRequests)->where('status', 'confirmed')
                ->whereDoesntHave('payments', fn ($query) => $query->where('amount', '>', 0))->count()];

        return view('admin.orders.show', compact('order', 'pickupContext'));
    }

    public function confirm(Request $request, Order $order, OrderReviewService $reviews): RedirectResponse
    {
        Gate::authorize('confirm-orders');
        $request->validate(['feasibility_confirmed' => ['accepted']]);
        $reviews->confirm($order, $request->user(), $request->boolean('feasibility_confirmed'));

        return back()->with('success', 'Request confirmed as feasible. The exact 50% deposit is now available; preparation requires verification.');
    }

    public function decline(Request $request, Order $order, OrderReviewService $reviews): RedirectResponse
    {
        Gate::authorize('decline-orders');
        $request->validate(['decline_reason' => ['required', 'string', 'max:1000'], 'no_funds_checked' => ['sometimes', 'accepted']]);
        $reviews->decline($order, $request->string('decline_reason')->toString(), $request->user(), $request->boolean('no_funds_checked'));

        return back()->with('success', 'Request declined. The reason is available through the customer’s private order link.');
    }

    /**
     * Advance order status (preparing, ready_for_pickup, completed).
     */
    public function updateStatus(Request $request, Order $order): RedirectResponse
    {
        $request->validate([
            'status' => ['required', Rule::in(['confirmed', 'preparing', 'ready_for_pickup', 'completed'])],
        ]);

        $this->orderService->updateStatus($order, $request->status, Auth::user());

        return back()->with('success', 'Order status transitioned to '.str_replace('_', ' ', $request->status).'.');
    }

    /**
     * Cancel an order.
     */
    public function cancel(Request $request, Order $order): RedirectResponse
    {
        $request->validate(['no_funds_checked' => ['sometimes', 'accepted']]);
        $this->orderService->cancelOrder($order, Auth::user(), $request->boolean('no_funds_checked'));

        return back()->with('success', "Order {$order->order_number} has been cancelled.");
    }

    /**
     * Attach an additional image to an order or specific order_detail.
     */
    public function attachImage(Request $request, Order $order): RedirectResponse
    {
        Gate::authorize('manage-orders');
        $request->validate([
            'image' => ['required', 'image', 'max:5120'],
            'order_detail_id' => ['nullable', 'exists:order_details,id'],
        ]);

        $orderDetail = null;
        if ($request->filled('order_detail_id')) {
            $orderDetail = $order->orderDetails()->findOrFail($request->order_detail_id);
        }

        $imageFile = $request->file('image');
        $path = $imageFile->store("staff/{$order->id}", 'staff_references');
        $filename = $imageFile->getClientOriginalName();

        try {
            $this->orderService->attachImage($order, $path, $filename, $orderDetail, Auth::user());
        } catch (\Throwable $exception) {
            Storage::disk('staff_references')->delete($path);
            throw $exception;
        }

        return back()->with('success', 'Reference image attached successfully.');
    }
}
