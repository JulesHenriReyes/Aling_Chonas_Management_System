<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Product;
use App\Services\OrderService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PublicOrderController extends Controller
{
    public function __construct(
        protected OrderService $orderService
    ) {}

    /**
     * Display the public ordering page with active products.
     */
    public function index(): View
    {
        $products = Product::where('is_active', true)->orderBy('product_name')->get();

        return view('public.index', compact('products'));
    }

    /**
     * Handle public order submission.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'phone_number' => ['required', 'string', 'min:7', 'max:20'],
            'pickup_date' => ['required', 'date', 'after_or_equal:today'],
            'pickup_time' => ['required'],
            'notes_text' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => [
                'required',
                'integer',
                Rule::exists('products', 'id')->where('is_active', true),
            ],
            'items.*.quantity' => ['required', 'integer'],
            'items.*.layers' => ['nullable', 'integer', 'min:1', 'max:10'],
            'items.*.themes' => ['nullable', 'string', 'max:255'],
            'items.*.special_request' => ['nullable', 'string', 'max:1000'],
            'items.*.images' => ['nullable', 'array'],
            'items.*.images.*' => ['nullable', 'image', 'max:5120'],
            'images' => ['nullable', 'array'],
            'images.*' => ['nullable', 'image', 'max:5120'],
        ]);

        // Filter out items where quantity is zero or negative
        $validItems = [];
        foreach ($validated['items'] as $index => $item) {
            $qty = (int) ($item['quantity'] ?? 0);
            if ($qty > 0) {
                // Attach uploaded files for this item if present
                if ($request->hasFile("items.{$index}.images")) {
                    $item['images'] = $request->file("items.{$index}.images");
                }
                $validItems[] = $item;
            }
        }

        if (empty($validItems)) {
            return back()->withInput()->withErrors([
                'items' => 'You must select at least one product with a quantity greater than zero.',
            ]);
        }

        // Strict customer matching: phone_number (normalized) + first_name + last_name (+ middle_name)
        $customer = Customer::findOrCreateMatching([
            'first_name' => $validated['first_name'],
            'middle_name' => $validated['middle_name'] ?? null,
            'last_name' => $validated['last_name'],
            'phone_number' => $validated['phone_number'],
        ]);

        // Public order submission explicitly assigns user_id = null via createPublicOrder()
        $order = $this->orderService->createPublicOrder([
            'customer_id' => $customer->id,
            'pickup_date' => $validated['pickup_date'],
            'pickup_time' => $validated['pickup_time'],
            'notes_text' => $validated['notes_text'] ?? null,
            'items' => $validItems,
            'images' => $request->file('images', []),
        ]);

        // Store confirmation data strictly in temporary session state
        $request->session()->flash('public_order_success', [
            'order_number' => $order->order_number,
            'customer_name' => $customer->full_name,
            'pickup_date' => $order->pickup_date->format('F d, Y'),
            'pickup_time' => Carbon::parse($order->pickup_time)->format('h:i A'),
            'items' => $order->orderDetails->map(fn ($detail) => [
                'product_name' => $detail->product->product_name,
                'quantity' => $detail->quantity,
                'provisional_price' => $detail->unit_price,
                'subtotal' => $detail->subtotal,
                'layers' => $detail->layers,
                'themes' => $detail->themes,
                'special_request' => $detail->special_request,
                'images_count' => $detail->images()->count(),
            ])->toArray(),
            'provisional_total' => $order->total_amount,
            'status' => $order->status,
        ]);

        return redirect()->route('public.order.success');
    }

    /**
     * Display confirmation only within the buyer's submission flow via session data.
     */
    public function success(Request $request): View|RedirectResponse
    {
        $orderData = $request->session()->get('public_order_success');

        if (!$orderData) {
            return redirect()->route('public.order.index')
                ->with('info', 'No recent order submission found in this session.');
        }

        return view('public.success', compact('orderData'));
    }
}
