@extends('layouts.admin')

@section('title', 'Order ' . $order->order_number)

@section('content')
<div class="space-y-6">
    <!-- Top Nav / Back Button -->
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-3">
            <a href="{{ route('orders.index') }}" class="text-xs text-rose-900 font-bold hover:underline">
                ← Back to Orders
            </a>
            <span class="text-stone-300">/</span>
            <span class="text-xs text-stone-500 font-mono">{{ $order->order_number }}</span>
        </div>
        <div>
            @if ($order->user_id === null)
                <span class="inline-block px-3 py-1 rounded-full text-xs font-bold bg-purple-100 text-purple-800">
                    🌐 Origin: Public Web Submission
                </span>
            @else
                <span class="inline-block px-3 py-1 rounded-full text-xs font-bold bg-stone-200 text-stone-800">
                    👤 Origin: Staff Created ({{ $order->user->first_name }})
                </span>
            @endif
        </div>
    </div>

    <!-- Order Header Card -->
    <div class="bg-white rounded-2xl border border-stone-200 p-6 shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-6">
        <div>
            <div class="flex items-center gap-3 flex-wrap">
                <h1 class="text-2xl font-black text-stone-900 font-mono">{{ $order->order_number }}</h1>
                <span class="px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wider
                    @if($order->status === 'completed') bg-emerald-100 text-emerald-800
                    @elseif($order->status === 'cancelled') bg-red-100 text-red-800
                    @elseif($order->status === 'confirmed') bg-blue-100 text-blue-800
                    @elseif($order->status === 'ready_for_pickup') bg-teal-100 text-teal-800
                    @elseif($order->status === 'preparing') bg-indigo-100 text-indigo-800
                    @else bg-amber-100 text-amber-800 @endif">
                    {{ str_replace('_', ' ', $order->status) }}
                </span>
                <span class="px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wider
                    @if($order->payment_status === 'fully_paid') bg-emerald-100 text-emerald-800
                    @elseif($order->payment_status === 'partially_paid') bg-blue-100 text-blue-800
                    @else bg-red-100 text-red-800 @endif">
                    {{ str_replace('_', ' ', $order->payment_status) }}
                </span>
            </div>
            <div class="text-xs text-stone-500 mt-2 space-x-4">
                <span>Submitted: <strong>{{ $order->created_at->format('M d, Y h:i A') }}</strong></span>
                @if ($order->completed_at)
                    <span>Completed: <strong class="text-emerald-700">{{ $order->completed_at->format('M d, Y h:i A') }}</strong></span>
                @endif
                @if ($order->cancelled_at)
                    <span>Cancelled: <strong class="text-red-700">{{ $order->cancelled_at->format('M d, Y h:i A') }}</strong></span>
                @endif
            </div>
        </div>

        <!-- Quick Summary Amounts -->
        <div class="flex items-center gap-6 border-t md:border-t-0 md:border-l md:pl-6 pt-4 md:pt-0">
            <div>
                <span class="text-[10px] text-stone-500 uppercase tracking-wider font-bold block">Total Amount</span>
                <span class="text-2xl font-black text-rose-950">₱{{ number_format($order->total_amount, 2) }}</span>
            </div>
            <div>
                <span class="text-[10px] text-stone-500 uppercase tracking-wider font-bold block">Amount Paid</span>
                <span class="text-lg font-bold text-emerald-700">₱{{ number_format($order->amount_paid, 2) }}</span>
            </div>
            <div>
                <span class="text-[10px] text-stone-500 uppercase tracking-wider font-bold block">Balance</span>
                <span class="text-lg font-bold {{ $order->remaining_balance > 0 ? 'text-red-600' : 'text-stone-400' }}">
                    ₱{{ number_format($order->remaining_balance, 2) }}
                </span>
            </div>
        </div>
    </div>

    <!-- Workflow Guidance Banner for Pending Orders -->
    @if ($order->status === 'pending')
        <div class="bg-gradient-to-r from-amber-50 to-orange-50 border border-amber-300 rounded-2xl p-5 shadow-sm text-xs text-amber-950">
            <div class="flex items-start gap-3">
                <span class="text-2xl">⏳</span>
                <div>
                    <h3 class="font-bold text-sm text-amber-900">Pending Order Workflow</h3>
                    <p class="mt-1 leading-relaxed">
                        1. <strong>Review Customizations & Images:</strong> Inspect each product's layers, themes, special requests, and reference photos below.<br>
                        2. <strong>Adjust Prices:</strong> If the custom design requires extra labor/materials, revise the unit price for each item. The total and 50% deposit recalculate automatically.<br>
                        3. <strong>Confirm via 50% Down Payment:</strong> Record the exact 50% deposit (₱{{ number_format($order->required_down_payment, 2) }}) using the payment form below to confirm the order.
                    </p>
                </div>
            </div>
        </div>
    @endif

    <!-- 2 Column Details Section -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Customer & Schedule Info (1 Column) -->
        <div class="space-y-6">
            <!-- Customer Card -->
            <div class="bg-white rounded-2xl border border-stone-200 p-5 shadow-sm">
                <h3 class="text-xs font-bold text-stone-400 uppercase tracking-wider mb-3">Customer Information</h3>
                <div class="space-y-2 text-xs">
                    <div>
                        <span class="text-stone-500 block">Name</span>
                        <a href="{{ route('customers.show', $order->customer) }}" class="font-bold text-stone-900 hover:text-rose-900 hover:underline">
                            {{ $order->customer->full_name }}
                        </a>
                    </div>
                    <div>
                        <span class="text-stone-500 block">Phone Number</span>
                        <span class="font-mono font-semibold text-stone-800">{{ $order->customer->phone_number }}</span>
                    </div>
                </div>
            </div>

            <!-- Pickup Schedule Card -->
            <div class="bg-white rounded-2xl border border-stone-200 p-5 shadow-sm">
                <h3 class="text-xs font-bold text-stone-400 uppercase tracking-wider mb-3">Pickup Schedule</h3>
                <div class="space-y-2 text-xs">
                    <div>
                        <span class="text-stone-500 block">Date</span>
                        <span class="font-bold text-stone-900">{{ $order->pickup_date->format('F d, Y (l)') }}</span>
                    </div>
                    <div>
                        <span class="text-stone-500 block">Time</span>
                        <span class="font-bold text-stone-900">{{ \Carbon\Carbon::parse($order->pickup_time)->format('h:i A') }}</span>
                    </div>
                    @if ($order->notes_text)
                        <div class="pt-2 border-t mt-2">
                            <span class="text-stone-500 block">Order Notes</span>
                            <p class="text-stone-700 italic mt-0.5">{{ $order->notes_text }}</p>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Lifecycle Actions Card -->
            <div class="bg-white rounded-2xl border border-stone-200 p-5 shadow-sm space-y-4">
                <h3 class="text-xs font-bold text-stone-400 uppercase tracking-wider">Lifecycle Transitions</h3>

                @if ($order->status === 'confirmed')
                    <form action="{{ route('orders.updateStatus', $order) }}" method="POST">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="status" value="preparing">
                        <button type="submit" class="w-full py-2 bg-indigo-700 hover:bg-indigo-600 text-white font-bold text-xs rounded-xl shadow transition">
                            👩‍🍳 Start Preparing (Baking)
                        </button>
                    </form>
                @elseif ($order->status === 'preparing')
                    <form action="{{ route('orders.updateStatus', $order) }}" method="POST">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="status" value="ready_for_pickup">
                        <button type="submit" class="w-full py-2 bg-teal-700 hover:bg-teal-600 text-white font-bold text-xs rounded-xl shadow transition">
                            📦 Mark as Ready for Pickup
                        </button>
                    </form>
                @elseif ($order->status === 'ready_for_pickup')
                    @if ($order->remaining_balance > 0)
                        <div class="p-3 bg-amber-50 border border-amber-200 rounded-xl text-xs text-amber-800">
                            <strong>Full payment required:</strong> Please settle the remaining balance of ₱{{ number_format($order->remaining_balance, 2) }} before completing the order.
                        </div>
                    @else
                        <form action="{{ route('orders.updateStatus', $order) }}" method="POST">
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="status" value="completed">
                            <button type="submit" class="w-full py-2 bg-emerald-700 hover:bg-emerald-600 text-white font-bold text-xs rounded-xl shadow transition">
                                ✓ Complete Order (Picked Up)
                            </button>
                        </form>
                    @endif
                @elseif ($order->status === 'completed')
                    <div class="p-3 bg-emerald-50 border border-emerald-200 rounded-xl text-xs text-emerald-800 font-bold text-center">
                        Order is Fully Completed
                    </div>
                @elseif ($order->status === 'cancelled')
                    <div class="p-3 bg-red-50 border border-red-200 rounded-xl text-xs text-red-800 font-bold text-center">
                        Order is Cancelled
                    </div>
                @endif

                <!-- Cancellation Button (available before completed and not already cancelled) -->
                @if ($order->status !== 'completed' && $order->status !== 'cancelled')
                    <form action="{{ route('orders.cancel', $order) }}" method="POST" onsubmit="return confirm('Are you sure you want to cancel this order? Any payments received will be retained as non-refundable cancellation income.');">
                        @csrf
                        <button type="submit" class="w-full py-2 bg-red-50 hover:bg-red-100 text-red-700 font-bold text-xs rounded-xl border border-red-200 transition">
                            ✕ Cancel Order
                        </button>
                    </form>
                @endif
            </div>
        </div>

        <!-- Per-Product Customizations & Price Adjustments (2 Columns) -->
        <div class="lg:col-span-2 space-y-6">
            <!-- Line Items Card -->
            <div class="bg-white rounded-2xl border border-stone-200 p-6 shadow-sm">
                <div class="flex items-center justify-between border-b pb-4 mb-4">
                    <h2 class="text-sm font-bold text-stone-900">Per-Product Customizations & Line Items</h2>
                    @if ($order->status === 'pending')
                        <span class="text-xs bg-amber-100 text-amber-800 px-2.5 py-0.5 rounded-full font-bold">
                            Prices Adjustable
                        </span>
                    @else
                        <span class="text-xs bg-stone-100 text-stone-600 px-2.5 py-0.5 rounded-full font-bold">
                            🔒 Prices Locked
                        </span>
                    @endif
                </div>

                <div class="space-y-6">
                    @foreach ($order->orderDetails as $detail)
                        <div class="border border-stone-200 rounded-xl p-4 bg-stone-50/40 space-y-3">
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-stone-200/60 pb-3">
                                <div>
                                    <h4 class="font-bold text-stone-900 text-sm">{{ $detail->product->product_name }}</h4>
                                    <span class="text-xs text-stone-500">Catalog Price: ₱{{ number_format($detail->product->price, 2) }}</span>
                                </div>
                                <div class="text-right">
                                    <span class="text-xs text-stone-500 block">Subtotal ({{ $detail->quantity }} qty)</span>
                                    <span class="text-base font-extrabold text-stone-900">₱{{ number_format($detail->subtotal, 2) }}</span>
                                </div>
                            </div>

                            <!-- Customization details -->
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 text-xs">
                                <div>
                                    <span class="text-stone-400 font-semibold block uppercase text-[10px]">Layers</span>
                                    <span class="font-bold text-stone-800">{{ $detail->layers ?? 'N/A' }}</span>
                                </div>
                                <div>
                                    <span class="text-stone-400 font-semibold block uppercase text-[10px]">Theme / Colors</span>
                                    <span class="font-bold text-stone-800">{{ $detail->themes ?? 'None specified' }}</span>
                                </div>
                                <div>
                                    <span class="text-stone-400 font-semibold block uppercase text-[10px]">Special Requests</span>
                                    <span class="text-stone-800">{{ $detail->special_request ?? 'None' }}</span>
                                </div>
                            </div>

                            <!-- Associated Reference Images for this Product Line -->
                            @if ($detail->images->isNotEmpty())
                                <div class="pt-2">
                                    <span class="text-[10px] text-stone-400 uppercase font-bold block mb-1">Product Reference Photos:</span>
                                    <div class="flex flex-wrap gap-2">
                                        @foreach ($detail->images as $img)
                                            <a href="{{ asset('storage/' . $img->file_path) }}" target="_blank" class="block border rounded-lg overflow-hidden w-16 h-16 bg-white shadow-sm hover:opacity-80 transition">
                                                <img src="{{ asset('storage/' . $img->file_path) }}" alt="{{ $img->original_filename }}" class="w-full h-full object-cover">
                                            </a>
                                        @endforeach
                                    </div>
                                </div>
                            @endif

                            <!-- Price Adjustment Form (Pending Status only) -->
                            @if ($order->status === 'pending')
                                <div class="pt-3 border-t border-dashed border-stone-200">
                                    <form action="{{ route('orders.updateDetailPrice', [$order, $detail]) }}" method="POST" class="flex items-center gap-3">
                                        @csrf
                                        @method('PATCH')
                                        <div class="flex items-center gap-1.5">
                                            <label class="text-xs font-bold text-stone-700">Unit Price (₱):</label>
                                            <input type="number" step="0.01" min="0" name="unit_price" value="{{ $detail->unit_price }}" 
                                                   class="w-28 text-xs rounded-lg border-stone-300 focus:border-rose-700 focus:ring-rose-700 shadow-sm font-semibold">
                                        </div>
                                        <button type="submit" class="px-3 py-1.5 bg-stone-800 hover:bg-stone-700 text-white font-bold text-xs rounded-lg transition shadow">
                                            Update Price
                                        </button>
                                    </form>
                                </div>
                            @else
                                <div class="pt-2 text-[11px] text-stone-400 italic">
                                    Price locked at ₱{{ number_format($detail->unit_price, 2) }} per unit.
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>

            <!-- General Order Reference Images & Attach Image Form -->
            <div class="bg-white rounded-2xl border border-stone-200 p-6 shadow-sm">
                <div class="flex items-center justify-between border-b pb-4 mb-4">
                    <h2 class="text-sm font-bold text-stone-900">All Order Reference Images</h2>
                    <span class="text-xs text-stone-400">Total: {{ $order->images->count() }} image(s)</span>
                </div>

                @if ($order->images->isNotEmpty())
                    <div class="grid grid-cols-3 sm:grid-cols-4 md:grid-cols-6 gap-3 mb-4">
                        @foreach ($order->images as $img)
                            <div class="border rounded-xl overflow-hidden bg-stone-50 group relative">
                                <a href="{{ asset('storage/' . $img->file_path) }}" target="_blank" class="block aspect-square">
                                    <img src="{{ asset('storage/' . $img->file_path) }}" alt="{{ $img->original_filename }}" class="w-full h-full object-cover">
                                </a>
                                <div class="p-1 text-[9px] text-stone-500 truncate" title="{{ $img->original_filename }}">
                                    {{ $img->original_filename }}
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="text-xs text-stone-400 py-3 text-center">No images uploaded for this order.</p>
                @endif

                <!-- Staff Upload Image Form -->
                @if ($order->status !== 'completed' && $order->status !== 'cancelled')
                    <form action="{{ route('orders.attachImage', $order) }}" method="POST" enctype="multipart/form-data" class="pt-3 border-t flex flex-col sm:flex-row items-center gap-3">
                        @csrf
                        <div class="w-full sm:w-auto flex-grow">
                            <input type="file" name="image" required accept="image/*" class="w-full text-xs text-stone-500 file:mr-2 file:py-1.5 file:px-3 file:rounded file:border-0 file:text-xs file:bg-stone-100">
                        </div>
                        <div>
                            <select name="order_detail_id" class="text-xs rounded-lg border-stone-300">
                                <option value="">Associate with: Whole Order</option>
                                @foreach ($order->orderDetails as $d)
                                    <option value="{{ $d->id }}">{{ $d->product->product_name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <button type="submit" class="w-full sm:w-auto px-4 py-1.5 bg-rose-900 hover:bg-rose-800 text-white font-bold text-xs rounded-lg shadow">
                            Upload
                        </button>
                    </form>
                @endif
            </div>

            <!-- Payment Management & Confirmation Section -->
            <div class="bg-white rounded-2xl border border-stone-200 p-6 shadow-sm space-y-4">
                <div class="flex items-center justify-between border-b pb-4">
                    <h2 class="text-sm font-bold text-stone-900">Payments & Receipts</h2>
                    <span class="text-xs font-mono font-bold text-emerald-700">
                        Paid: ₱{{ number_format($order->amount_paid, 2) }} / ₱{{ number_format($order->total_amount, 2) }}
                    </span>
                </div>

                <!-- Down Payment Recording Form (for Pending Orders) -->
                @if ($order->status === 'pending')
                    <div class="bg-amber-50/70 border border-amber-200 rounded-xl p-5 space-y-3">
                        <div class="flex items-center justify-between">
                            <h4 class="font-bold text-amber-950 text-xs uppercase tracking-wide">
                                Record 50% Down Payment to Confirm Order
                            </h4>
                            <span class="font-extrabold text-sm text-rose-950">
                                Exact 50%: ₱{{ number_format($order->required_down_payment, 2) }}
                            </span>
                        </div>
                        <p class="text-xs text-amber-800">
                            Recording this verified payment will automatically transition the order to <strong>Confirmed</strong> and lock product pricing.
                        </p>

                        <form action="{{ route('orders.payments.store', $order) }}" method="POST" class="grid grid-cols-1 sm:grid-cols-4 gap-3 pt-2">
                            @csrf
                            <input type="hidden" name="payment_type" value="down_payment">
                            
                            <div>
                                <label class="block text-[10px] uppercase font-bold text-stone-600 mb-1">Amount (₱)</label>
                                <input type="number" step="0.01" name="amount" value="{{ $order->required_down_payment }}" readonly 
                                       class="w-full text-xs rounded-lg border-stone-300 bg-stone-100 font-bold text-stone-800">
                            </div>

                            <div>
                                <label class="block text-[10px] uppercase font-bold text-stone-600 mb-1">Payment Method</label>
                                <select name="payment_method" required class="w-full text-xs rounded-lg border-stone-300 focus:border-rose-700 focus:ring-rose-700">
                                    <option value="cash">Cash</option>
                                    <option value="gcash">GCash</option>
                                </select>
                            </div>

                            <div>
                                <label class="block text-[10px] uppercase font-bold text-stone-600 mb-1">GCash Ref #</label>
                                <input type="text" name="reference_number" placeholder="Required if GCash" 
                                       class="w-full text-xs rounded-lg border-stone-300 focus:border-rose-700 focus:ring-rose-700">
                            </div>

                            <div class="flex items-end">
                                <button type="submit" class="w-full py-2 bg-emerald-700 hover:bg-emerald-600 text-white font-bold text-xs rounded-lg shadow transition">
                                    Confirm & Pay 50%
                                </button>
                            </div>
                        </form>
                    </div>
                @endif

                <!-- Final Payment Recording Form (for Confirmed / Ready orders with balance) -->
                @if ($order->status !== 'pending' && $order->status !== 'cancelled' && $order->status !== 'completed' && $order->remaining_balance > 0)
                    <div class="bg-blue-50/70 border border-blue-200 rounded-xl p-5 space-y-3">
                        <div class="flex items-center justify-between">
                            <h4 class="font-bold text-blue-950 text-xs uppercase tracking-wide">
                                Record Final Payment (Settle Balance)
                            </h4>
                            <span class="font-extrabold text-sm text-blue-950">
                                Balance: ₱{{ number_format($order->remaining_balance, 2) }}
                            </span>
                        </div>

                        <form action="{{ route('orders.payments.store', $order) }}" method="POST" class="grid grid-cols-1 sm:grid-cols-4 gap-3 pt-2">
                            @csrf
                            <input type="hidden" name="payment_type" value="final_payment">
                            
                            <div>
                                <label class="block text-[10px] uppercase font-bold text-stone-600 mb-1">Amount (₱)</label>
                                <input type="number" step="0.01" name="amount" value="{{ $order->remaining_balance }}" readonly 
                                       class="w-full text-xs rounded-lg border-stone-300 bg-stone-100 font-bold text-stone-800">
                            </div>

                            <div>
                                <label class="block text-[10px] uppercase font-bold text-stone-600 mb-1">Payment Method</label>
                                <select name="payment_method" required class="w-full text-xs rounded-lg border-stone-300 focus:border-rose-700 focus:ring-rose-700">
                                    <option value="cash">Cash</option>
                                    <option value="gcash">GCash</option>
                                </select>
                            </div>

                            <div>
                                <label class="block text-[10px] uppercase font-bold text-stone-600 mb-1">GCash Ref #</label>
                                <input type="text" name="reference_number" placeholder="Required if GCash" 
                                       class="w-full text-xs rounded-lg border-stone-300 focus:border-rose-700 focus:ring-rose-700">
                            </div>

                            <div class="flex items-end">
                                <button type="submit" class="w-full py-2 bg-blue-700 hover:bg-blue-600 text-white font-bold text-xs rounded-lg shadow transition">
                                    Record Final Settlement
                                </button>
                            </div>
                        </form>
                    </div>
                @endif

                <!-- Payment Records Table -->
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-stone-50 border-b text-stone-500 uppercase text-[10px]">
                            <tr>
                                <th class="p-3">Date</th>
                                <th class="p-3">Type</th>
                                <th class="p-3">Method</th>
                                <th class="p-3">Ref #</th>
                                <th class="p-3">Recorded By</th>
                                <th class="p-3 text-right">Amount</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-stone-100">
                            @forelse ($order->payments as $payment)
                                <tr>
                                    <td class="p-3">{{ $payment->payment_date->format('M d, Y h:i A') }}</td>
                                    <td class="p-3">
                                        <span class="inline-block px-2 py-0.5 rounded text-[10px] font-bold uppercase
                                            {{ $payment->payment_type === 'down_payment' ? 'bg-amber-100 text-amber-800' : 'bg-emerald-100 text-emerald-800' }}">
                                            {{ str_replace('_', ' ', $payment->payment_type) }}
                                        </span>
                                    </td>
                                    <td class="p-3 uppercase font-semibold text-stone-700">{{ $payment->payment_method }}</td>
                                    <td class="p-3 font-mono text-stone-600">{{ $payment->reference_number ?? '—' }}</td>
                                    <td class="p-3 text-stone-600">{{ $payment->user->first_name ?? 'Staff' }}</td>
                                    <td class="p-3 text-right font-bold text-stone-900">₱{{ number_format($payment->amount, 2) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="p-4 text-center text-stone-400">No payment records yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
