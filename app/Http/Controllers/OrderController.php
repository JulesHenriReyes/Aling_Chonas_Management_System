<?php

namespace App\Http\Controllers;

use App\Http\Requests\CatalogOrderRules;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Services\CatalogPricingService;
use App\Services\OrderDraftService;
use App\Services\OrderService;
use App\Services\OrderReviewService;
use App\Support\PhilippineContact;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
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
        $query = Order::with(['customer', 'user', 'orderDetails.product', 'orderDetails.addOns', 'payments', 'paymentProofs'])
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

        $draft = $drafts->get($request, true);

        return view('admin.orders.create', compact('customers', 'products', 'draft'));
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
        $quote = $pricing->quote($draft['items']);
        $customers = Customer::orderBy('last_name')->get();

        return view('admin.orders.details', compact('draft', 'quote', 'customers'));
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
        $draft = $drafts->get($request, true);
        $rules = ($draft ? array_diff_key(CatalogOrderRules::order(), CatalogOrderRules::items()) : CatalogOrderRules::order()) + ['customer_id' => ['required', 'exists:customers,id']];
        $validated = $request->validate($rules);
        if ($draft) {
            $validated['items'] = $drafts->itemsForOrder($draft);
        }

        // Internal staff order must strictly assign user_id = Auth::id()
        $staffUser = Auth::user();
        if (! $staffUser) {
            abort(403, 'Unauthorized staff action.');
        }

        $items = $validated['items'];
        foreach ($items as $index => &$item) {
            if ($request->hasFile("items.{$index}.images")) {
                $item['images'] = $request->file("items.{$index}.images");
            }
        }
        unset($item);

        $order = $this->orderService->createInternalOrder([
            'customer_id' => $validated['customer_id'],
            'pickup_date' => $validated['pickup_date'],
            'pickup_time' => $validated['pickup_time'],
            'notes_text' => $validated['notes_text'] ?? null,
            'items' => $items,
            'expected_total' => $validated['expected_total'],
            'images' => $request->file('images', []),
        ], $staffUser);

        if ($draft) {
            $drafts->finish($request, true);
        }

        return redirect()->route('orders.show', $order)
            ->with('success', "Order {$order->order_number} created successfully.");
    }

    /**
     * Detailed order review page (fixed-price snapshots, customizations, payments, lifecycle).
     */
    public function show(Order $order): View
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
            'refund.requestedBy',
            'refund.completedBy',
            'reviewer',
        ]);

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
        $path = $imageFile->store("order_images/{$order->id}", 'public');
        $filename = $imageFile->getClientOriginalName();

        $this->orderService->attachImage($order, $path, $filename, $orderDetail, Auth::user());

        return back()->with('success', 'Reference image attached successfully.');
    }
}
