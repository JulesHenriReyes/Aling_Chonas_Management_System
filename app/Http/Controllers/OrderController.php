<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\Product;
use App\Services\OrderService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
        $query = Order::with(['customer', 'user', 'orderDetails.product', 'payments'])
            ->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
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
    public function create(): View
    {
        $customers = Customer::orderBy('last_name')->get();
        $products = Product::where('is_active', true)->orderBy('product_name')->get();

        return view('admin.orders.create', compact('customers', 'products'));
    }

    /**
     * Store an internally created order. Strictly requires authenticated staff user_id.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'customer_id' => ['required', 'exists:customers,id'],
            'pickup_date' => ['required', 'date', 'after_or_equal:today'],
            'pickup_time' => ['required'],
            'notes_text' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0'],
            'items.*.layers' => ['nullable', 'integer', 'min:1'],
            'items.*.themes' => ['nullable', 'string', 'max:255'],
            'items.*.special_request' => ['nullable', 'string', 'max:1000'],
            'items.*.images' => ['nullable', 'array'],
            'items.*.images.*' => ['nullable', 'image', 'max:5120'],
            'images' => ['nullable', 'array'],
            'images.*' => ['nullable', 'image', 'max:5120'],
        ]);

        // Internal staff order must strictly assign user_id = Auth::id()
        $staffUser = Auth::user();
        if (!$staffUser) {
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
            'images' => $request->file('images', []),
        ], $staffUser);

        return redirect()->route('orders.show', $order)
            ->with('success', "Order {$order->order_number} created successfully.");
    }

    /**
     * Detailed order review page (customizations, price adjustments, payments, lifecycle).
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
        ]);

        return view('admin.orders.show', compact('order'));
    }

    /**
     * Staff adjusts unit_price on a pending order detail.
     */
    public function updateDetailPrice(Request $request, Order $order, OrderDetail $orderDetail): RedirectResponse
    {
        $request->validate([
            'unit_price' => ['required', 'numeric', 'min:0'],
        ]);

        $this->orderService->updateOrderDetailPrice($order, $orderDetail, (float) $request->unit_price, Auth::user());

        return back()->with('success', "Updated unit price for {$orderDetail->product->product_name} to ₱" . number_format($request->unit_price, 2) . ".");
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

        return back()->with('success', "Order status transitioned to " . str_replace('_', ' ', $request->status) . ".");
    }

    /**
     * Cancel an order.
     */
    public function cancel(Order $order): RedirectResponse
    {
        $this->orderService->cancelOrder($order, Auth::user());

        return back()->with('success', "Order {$order->order_number} has been cancelled.");
    }

    /**
     * Attach an additional image to an order or specific order_detail.
     */
    public function attachImage(Request $request, Order $order): RedirectResponse
    {
        $request->validate([
            'image' => ['required', 'image', 'max:5120'],
            'order_detail_id' => ['nullable', 'exists:order_details,id'],
        ]);

        $orderDetail = null;
        if ($request->filled('order_detail_id')) {
            $orderDetail = OrderDetail::findOrFail($request->order_detail_id);
        }

        $imageFile = $request->file('image');
        $path = $imageFile->store("order_images/{$order->id}", 'public');
        $filename = $imageFile->getClientOriginalName();

        $this->orderService->attachImage($order, $path, $filename, $orderDetail, Auth::user());

        return back()->with('success', 'Reference image attached successfully.');
    }
}
