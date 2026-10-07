@extends('layouts.admin')

@section('title', 'Manage Orders')

@section('content')
<div class="space-y-6" x-data>
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-xl font-bold text-cocoa-600">Order Management</h1>
            <p class="text-sm text-cocoa-400 mt-1">Review saved order details and advance preparation and pickup statuses.</p>
        </div>
        @can('manage-orders')
<a href="{{ route('orders.create') }}" class="bg-cocoa-600 hover:bg-cocoa-700 text-white font-semibold text-sm px-4 py-2 rounded-lg transition self-start sm:self-auto"><x-icon name="plus" class="mr-1" /> New Staff Order
        </a>
@endcan
    </div>

    <!-- Filters & Search -->
    <div class="space-y-4">
        <!-- Status Tabs -->
        <div class="flex flex-wrap gap-2 text-sm border-b border-cocoa-100 pb-3">
            @php
                $currentStatus = request('status', '');
                $currentQueue = request('queue', '');
                $statuses = [
                    '' => 'All Statuses',
                    'pending' => 'Pending Review',
                    'deposit' => 'Awaiting Deposit',
                    'receipts' => 'Receipt Verification',
                    'confirmed' => 'Confirmed',
                    'preparing' => 'Preparing',
                    'ready_for_pickup' => 'Ready for Pickup',
                    'completed' => 'Completed',
                    'cancelled' => 'Cancelled',
                ];
            @endphp
            @foreach ($statuses as $key => $label)
                @php
                    $isActive = match($key) {
                        '' => $currentStatus === '' && empty($currentQueue),
                        'deposit' => $currentQueue === 'deposit' || $currentStatus === 'deposit' || $currentStatus === 'awaiting_deposit',
                        'receipts' => $currentQueue === 'receipts' || $currentStatus === 'receipts',
                        default => $currentStatus === $key && empty($currentQueue),
                    };
                    $queryParam = match($key) {
                        'deposit' => ['status' => null, 'queue' => 'deposit', 'page' => 1],
                        'receipts' => ['status' => null, 'queue' => 'receipts', 'page' => 1],
                        default => ['status' => $key ?: null, 'queue' => null, 'page' => 1],
                    };
                @endphp
                <a href="{{ route('orders.index', array_merge(request()->query(), $queryParam)) }}"
                   class="px-3 py-1.5 rounded-lg font-medium transition {{ $isActive ? 'bg-cocoa-600 text-white' : 'text-cocoa-500 hover:bg-cream-100' }}">
                    {{ $label }}
                </a>
            @endforeach
        </div>

        <form action="{{ route('orders.index') }}" method="GET" class="flex flex-col sm:flex-row flex-wrap gap-3">
            <input type="hidden" name="status" value="{{ request('status') }}">
            @if(request('queue'))<input type="hidden" name="queue" value="{{ request('queue') }}">@endif
            <div class="flex-grow min-w-[200px]">
                <input aria-label="Search" type="text" name="search" value="{{ request('search') }}" 
                       placeholder="Search by Order #, Customer Name, or Phone..." 
                       class="w-full text-sm rounded-lg border-cocoa-100 bg-white focus:border-cocoa-300 focus:ring-cocoa-300 placeholder-cocoa-400/50">
            </div>
            <div>
                <select aria-label="Order origin" name="origin" class="w-full sm:w-auto text-sm rounded-lg border-cocoa-100 bg-white focus:border-cocoa-300 focus:ring-cocoa-300">
                    <option value="">All Origins</option>
                    <option value="public" {{ request('origin') === 'public' ? 'selected' : '' }}>Public Web Orders</option>
                    <option value="staff" {{ request('origin') === 'staff' ? 'selected' : '' }}>Staff Created</option>
                </select>
            </div>
            <div class="flex items-center gap-1.5 text-xs text-cocoa-500">
                <label for="filter-date-from" class="sr-only">Pickup from date</label>
                <input id="filter-date-from" aria-label="Pickup from date" type="date" name="date_from" value="{{ request('date_from') }}" class="text-sm rounded-lg border-cocoa-100 bg-white focus:border-cocoa-300 focus:ring-cocoa-300">
                <span>to</span>
                <label for="filter-date-to" class="sr-only">Pickup to date</label>
                <input id="filter-date-to" aria-label="Pickup to date" type="date" name="date_to" value="{{ request('date_to') }}" class="text-sm rounded-lg border-cocoa-100 bg-white focus:border-cocoa-300 focus:ring-cocoa-300">
            </div>
            <button type="submit" class="bg-white border border-cocoa-100 text-cocoa-500 hover:bg-cream-100 font-medium text-sm px-4 py-2 rounded-lg transition">
                Filter
            </button>
            @if(request()->hasAny(['status', 'search', 'origin', 'queue', 'date_from', 'date_to']))
                <a href="{{ route('orders.index') }}" class="text-cocoa-500 hover:text-cocoa-600 font-medium text-sm self-center px-3 py-2">
                    Reset
                </a>
            @endif
        </form>
    </div>

    <!-- Orders Table -->
    <div class="bg-white rounded-xl border border-cocoa-100 overflow-hidden">
        @if ($orders->isEmpty())
            <div class="py-12 text-center text-sm text-cocoa-400">
                No orders found matching the filter criteria.
            </div>
        @else
            <div class="table-scroll" role="region" aria-label="Scrollable data table" tabindex="0">
                <table class="w-full text-left orders-table">
                    <thead class="bg-cream-100 text-cocoa-400 text-xs font-semibold">
                        <tr>
                            <th class="p-4">Order #</th>
                            <th class="p-4">Customer</th>
                            <th class="p-4">Items</th>
                            <th class="p-4">Pickup Schedule</th>
                            <th class="p-4">Total Amount</th>
                            <th class="p-4">Payment</th>
                            <th class="p-4">Status</th>
                            <th class="p-4 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-cocoa-100/60">
                        @foreach ($orders as $order)
                            @php
                                $orderData = [
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
                                        'url' => asset('storage/' . $img->file_path),
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
                                            'review_url' => route('orders.show', $order),
                                            'receipt_url' => route('proofs.receipt', $proof),
                                            'uploaded_at' => $proof->created_at->format('M d, Y h:i A'),
                                        ];
                                    })->values(),
                                ];
                            @endphp
                            <tr class="text-sm text-cocoa-500 hover:bg-cream-50 transition cursor-pointer group"
                                @click="$dispatch('open-order-detail', @js($orderData))">
                                <td class="p-4 whitespace-nowrap">
                                    <a href="{{ route('orders.show', $order) }}" 
                                       class="font-mono font-semibold text-cocoa-700 hover:underline group-hover:text-amber-800 block"
                                       @click="if (!$event.ctrlKey && !$event.metaKey && $event.button === 0) { $event.preventDefault(); $event.stopPropagation(); $dispatch('open-order-detail', @js($orderData)); } else { $event.stopPropagation(); }">
                                        {{ $order->order_number }}
                                    </a>
                                </td>
                                <td class="p-4 whitespace-nowrap">
                                    <div class="font-semibold text-cocoa-700">{{ $order->customer->full_name }}</div>
                                    <div class="text-xs text-cocoa-400">{{ $order->customer->phone_number }}</div>
                                </td>
                                @php
                                    $pkgQty = $order->orderDetails->sum('quantity');
                                    $extrasQty = $order->orderDetails->flatMap->addOns->sum('quantity');
                                @endphp
                                <td class="p-4 whitespace-nowrap">
                                    <div class="font-medium text-cocoa-600">{{ $pkgQty }} {{ $order->fixed_catalog_pricing ? \Illuminate\Support\Str::plural('package', $pkgQty) : \Illuminate\Support\Str::plural('item', $pkgQty) }}</div>
                                    @if ($extrasQty > 0)
                                        <div class="text-xs text-cocoa-400">+ {{ $extrasQty }} {{ \Illuminate\Support\Str::plural('extra', $extrasQty) }}</div>
                                    @endif
                                </td>
                                <td class="p-4 whitespace-nowrap">
                                    <div class="font-semibold text-cocoa-600">{{ $order->pickup_date->format('M d, Y') }}</div>
                                    <div class="text-xs text-cocoa-400">{{ \Carbon\Carbon::parse($order->pickup_time)->format('h:i A') }}</div>
                                </td>
                                <td class="p-4 whitespace-nowrap font-semibold text-cocoa-600">
                                    ₱{{ number_format($order->total_amount, 2) }}
                                </td>
                                <td class="p-4 whitespace-nowrap">
                                    @if ($order->status === 'cancelled')
                                        @if ($order->amount_paid > 0)
                                            <span class="inline-flex items-center text-xs font-semibold px-2 py-0.5 rounded text-amber-800 bg-amber-50 border border-amber-200" title="Deposit recorded">
                                                ₱{{ number_format($order->amount_paid, 2) }} paid
                                            </span>
                                        @else
                                            <span class="text-xs text-cocoa-400 font-medium">—</span>
                                        @endif
                                    @else
                                        <x-status :value="$order->payment_status" />
                                        @if($order->payment_status === 'partially_paid')
                                            <span class="block text-xs text-cocoa-400 mt-1">Bal: ₱{{ number_format($order->remaining_balance, 2) }}</span>
                                        @endif
                                    @endif
                                </td>
                                <td class="p-4 whitespace-nowrap">
                                    <x-status :value="$order->status" :label="$order->workflowLabel()" class="order-workflow-status" />
                                </td>
                                <td class="p-4 text-right whitespace-nowrap" @click.stop>
                                    <a href="{{ route('orders.show', $order) }}" 
                                       class="bg-white border border-cocoa-100 text-cocoa-500 hover:bg-cream-100 font-medium text-xs px-3 py-1.5 rounded-lg transition inline-block">{{ $order->status === 'pending' ? 'Review' : 'View' }}</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="p-4 border-t border-cocoa-100/60">
                {{ $orders->links() }}
            </div>
        @endif
    </div>
</div>
@endsection

@push('drawers')
    <!-- Sliding Detail Drawer from Right (Anchored strictly below the header in all zoom/size states) -->
    <div x-data="{
        showDetailDrawer: false,
        detailLoading: false,
        drawerOrder: null,
        openOrderDetail(order) {
            this.drawerOrder = order;
            this.showDetailDrawer = true;
            this.detailLoading = true;

            fetch('/orders/' + order.id, {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(res => {
                if (!res.ok) throw new Error('Failed to load order details');
                return res.json();
            })
            .then(data => {
                if (this.drawerOrder && this.drawerOrder.id === order.id) {
                    this.drawerOrder = { ...this.drawerOrder, ...data.order };
                    this.detailLoading = false;
                }
            })
            .catch(() => {
                this.detailLoading = false;
            });
        },
        closeDetailDrawer() {
            this.showDetailDrawer = false;
        }
    }"
    @open-order-detail.window="openOrderDetail($event.detail)"
    @keydown.escape.window="closeDetailDrawer"
    x-show="showDetailDrawer"
    x-cloak
    data-detail-drawer
    class="fixed left-0 right-0 bottom-0 z-30 overflow-hidden"
    style="top: var(--topbar-height, 3.5rem); height: calc(100dvh - var(--topbar-height, 3.5rem)); display: none;">

        <!-- Backdrop scrim: strictly below the header, clean dark scrim WITHOUT backdrop-blur -->
        <div x-show="showDetailDrawer"
             x-transition:enter="transition-opacity ease-out duration-200"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="transition-opacity ease-in duration-150"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             @click="closeDetailDrawer"
             class="absolute inset-0 bg-black/25"></div>

        <!-- Sliding Panel Container from Right -->
        <div class="absolute inset-y-0 right-0 max-w-full flex pl-8 pointer-events-none">
            <div x-show="showDetailDrawer"
                 x-transition:enter="transform transition ease-out duration-250"
                 x-transition:enter-start="translate-x-full"
                 x-transition:enter-end="translate-x-0"
                 x-transition:leave="transform transition ease-in duration-200"
                 x-transition:leave-start="translate-x-0"
                 x-transition:leave-end="translate-x-full"
                 @click.away="closeDetailDrawer"
                 class="w-screen max-w-md sm:max-w-[460px] bg-white border-l border-cocoa-100 shadow-2xl flex flex-col h-full pointer-events-auto"
                 role="dialog"
                 aria-modal="true"
                 aria-label="Order details">

                <template x-if="drawerOrder">
                    <div class="flex flex-col h-full overflow-hidden">
                        <!-- Drawer Header -->
                        <div class="px-5 py-3.5 border-b border-cocoa-100 flex items-start justify-between gap-3 bg-white shrink-0">
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-2 mb-1">
                                    <span class="inline-flex items-center text-xs font-semibold px-2.5 py-0.5 rounded border border-cocoa-200 bg-cream-50 text-cocoa-700 uppercase tracking-wider"
                                          x-text="drawerOrder.workflow_label || drawerOrder.status"></span>
                                    <span x-show="detailLoading" class="text-[11px] text-cocoa-400 italic">Syncing...</span>
                                </div>
                                <h2 class="text-base font-bold text-cocoa-800 font-mono tracking-tight" x-text="'#' + drawerOrder.order_number"></h2>
                            </div>
                            <button type="button"
                                    @click="closeDetailDrawer"
                                    class="text-cocoa-400 hover:text-cocoa-600 p-1.5 rounded-lg hover:bg-cream-100 transition focus:outline-none shrink-0"
                                    aria-label="Close detail panel">
                                <x-icon name="close" class="w-5 h-5" />
                            </button>
                        </div>

                        <!-- Drawer Body (Scrollable) -->
                        <div class="flex-1 overflow-y-auto p-5 space-y-4 text-sm">
                            <!-- Needs Review / Proof Alert -->
                            <template x-if="drawerOrder.has_unreviewed_proof">
                                <div class="bg-amber-50 border border-amber-200 text-amber-800 rounded-xl p-3 text-xs flex items-center justify-between gap-2">
                                    <div class="flex items-center gap-2">
                                        <x-icon name="warning" class="w-4 h-4 text-amber-600 shrink-0" />
                                        <span>Payment receipt uploaded and awaiting verification.</span>
                                    </div>
                                    <a :href="drawerOrder.show_url" class="font-bold underline shrink-0 hover:text-amber-900">Review</a>
                                </div>
                            </template>

                            <!-- Customer & Schedule Summary Card -->
                            <div class="bg-cream-50/70 border border-cocoa-100 rounded-xl p-3.5 space-y-2">
                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <span class="text-[10px] font-semibold text-cocoa-400 uppercase tracking-wider block">Customer</span>
                                        <div class="font-bold text-cocoa-700 text-sm mt-0.5 truncate" x-text="drawerOrder.customer_name"></div>
                                        <div class="text-xs text-cocoa-500 mt-0.5" x-text="drawerOrder.customer_phone"></div>
                                    </div>
                                    <div>
                                        <span class="text-[10px] font-semibold text-cocoa-400 uppercase tracking-wider block">Pickup Schedule</span>
                                        <div class="font-bold text-cocoa-700 text-sm mt-0.5" x-text="drawerOrder.pickup_date"></div>
                                        <div class="text-xs text-cocoa-500 mt-0.5" x-text="drawerOrder.pickup_time"></div>
                                    </div>
                                </div>
                            </div>

                            <!-- Financial Breakdown Card -->
                            <div class="bg-white border border-cocoa-100 rounded-xl p-3.5 space-y-2.5">
                                <div class="grid grid-cols-3 gap-2 text-xs">
                                    <div>
                                        <span class="text-cocoa-400 block font-medium text-[10px] uppercase tracking-wider">Total</span>
                                        <span class="font-bold text-cocoa-700 text-sm mt-0.5 block" x-text="'₱' + drawerOrder.formatted_total"></span>
                                    </div>
                                    <div>
                                        <span class="text-cocoa-400 block font-medium text-[10px] uppercase tracking-wider">Total Paid</span>
                                        <span class="font-bold text-emerald-700 text-sm mt-0.5 block" x-text="'₱' + drawerOrder.formatted_paid"></span>
                                    </div>
                                    <div>
                                        <span class="text-cocoa-400 block font-medium text-[10px] uppercase tracking-wider">Balance</span>
                                        <span class="font-bold text-sm mt-0.5 block" :class="drawerOrder.status !== 'cancelled' && drawerOrder.remaining_balance > 0 ? 'text-amber-700' : 'text-cocoa-600'" x-text="drawerOrder.status === 'cancelled' ? '₱0.00' : '₱' + drawerOrder.formatted_balance"></span>
                                    </div>
                                </div>
                                <template x-if="drawerOrder.status !== 'cancelled' && drawerOrder.amount_paid == 0 && drawerOrder.remaining_balance > 0">
                                    <div class="pt-1.5 border-t border-cocoa-100/60 flex items-center justify-between text-xs text-cocoa-500">
                                        <span>50% Deposit Required:</span>
                                        <strong class="text-cocoa-700" x-text="'₱' + drawerOrder.formatted_down_payment"></strong>
                                    </div>
                                </template>
                            </div>

                            <!-- Ordered Items Breakdown -->
                            <div class="space-y-2">
                                <span class="text-[10px] font-semibold text-cocoa-400 uppercase tracking-wider block">Ordered Items</span>
                                <div class="space-y-2">
                                    <template x-for="item in drawerOrder.items" :key="item.id">
                                        <div class="bg-cream-50/50 border border-cocoa-100 rounded-xl p-3 text-xs space-y-2">
                                            <div class="flex items-start gap-3">
                                                <template x-if="item.photo_url">
                                                    <img :src="item.photo_url" :alt="item.product_name" class="w-11 h-11 object-cover rounded-lg border border-cocoa-100 shrink-0">
                                                </template>
                                                <div class="min-w-0 flex-1">
                                                    <span class="font-bold text-cocoa-700 text-sm leading-snug block" x-text="item.product_name"></span>
                                                    <span class="text-cocoa-400 text-[11px] block mt-0.5" x-text="'Qty: ' + item.quantity + ' × ₱' + item.unit_price"></span>
                                                </div>
                                            </div>

                                            <template x-if="item.layers || item.themes || item.special_request">
                                                <div class="pt-1.5 border-t border-cocoa-100/60 space-y-1 text-[11px] text-cocoa-500">
                                                    <template x-if="item.layers">
                                                        <div><strong class="text-cocoa-600">Layers:</strong> <span x-text="item.layers"></span></div>
                                                    </template>
                                                    <template x-if="item.themes">
                                                        <div><strong class="text-cocoa-600">Theme:</strong> <span x-text="item.themes"></span></div>
                                                    </template>
                                                    <template x-if="item.special_request">
                                                        <div><strong class="text-cocoa-600">Special Request:</strong> <span x-text="item.special_request"></span></div>
                                                    </template>
                                                </div>
                                            </template>

                                            <template x-if="item.add_ons && item.add_ons.length > 0">
                                                <div class="pt-1.5 border-t border-cocoa-100/60 space-y-1">
                                                    <span class="text-[10px] font-bold text-cocoa-400 uppercase tracking-wider block">Extras / Add-ons</span>
                                                    <template x-for="addon in item.add_ons" :key="addon.name">
                                                        <div class="flex items-center justify-between text-[11px] bg-white px-2 py-1 rounded border border-cocoa-100/60">
                                                            <span class="text-cocoa-600" x-text="addon.quantity + '× ' + addon.name"></span>
                                                            <span class="font-semibold text-cocoa-700" x-text="'₱' + addon.price"></span>
                                                        </div>
                                                    </template>
                                                </div>
                                            </template>
                                        </div>
                                    </template>
                                </div>
                            </div>

                            <!-- Customer Reference Photos (if uploaded) -->
                            <template x-if="drawerOrder.images && drawerOrder.images.length > 0">
                                <div class="space-y-1.5">
                                    <span class="text-[10px] font-semibold text-cocoa-400 uppercase tracking-wider block">Reference Photos</span>
                                    <div class="flex gap-2 overflow-x-auto py-1">
                                        <template x-for="img in drawerOrder.images" :key="img.id">
                                            <img :src="img.url" class="w-14 h-14 object-cover rounded-lg border border-cocoa-100 shrink-0">
                                        </template>
                                    </div>
                                </div>
                            </template>

                            <!-- Notes Section -->
                            <template x-if="drawerOrder.notes_text">
                                <div class="space-y-1">
                                    <span class="text-[10px] font-semibold text-cocoa-400 uppercase tracking-wider block">Order Notes</span>
                                    <div class="bg-white border border-cocoa-100 rounded-xl p-3 text-xs text-cocoa-600 leading-relaxed italic" x-text="drawerOrder.notes_text"></div>
                                </div>
                            </template>

                            <!-- Payments History -->
                            <div class="space-y-2">
                                <span class="text-[10px] font-semibold text-cocoa-400 uppercase tracking-wider block">Payment Records</span>
                                <template x-if="!drawerOrder.payments || drawerOrder.payments.length === 0">
                                    <div class="bg-white border border-cocoa-100 rounded-xl p-3 text-center text-xs text-cocoa-400">
                                        No payments recorded yet.
                                    </div>
                                </template>
                                <template x-if="drawerOrder.payments && drawerOrder.payments.length > 0">
                                    <div class="space-y-1.5">
                                        <template x-for="payment in drawerOrder.payments" :key="payment.id">
                                            <div class="bg-white border border-cocoa-100 rounded-lg p-2.5 text-xs flex items-center justify-between gap-3">
                                                <div class="min-w-0">
                                                    <div class="flex items-center gap-1.5">
                                                        <span class="font-bold text-cocoa-700" x-text="payment.payment_type"></span>
                                                        <span class="text-cocoa-400">·</span>
                                                        <span class="text-cocoa-500 font-medium" x-text="payment.payment_method"></span>
                                                    </div>
                                                    <div class="text-[11px] text-cocoa-400 mt-0.5 truncate" x-text="payment.paid_at + (payment.reference_number ? ' · Ref: ' + payment.reference_number : '')"></div>
                                                </div>
                                                <div class="text-right shrink-0">
                                                    <span class="font-bold text-emerald-700 block" x-text="'₱' + payment.amount"></span>
                                                    <template x-if="payment.recorded_by">
                                                        <span class="text-[10px] text-cocoa-400 block" x-text="'by ' + payment.recorded_by"></span>
                                                    </template>
                                                </div>
                                            </div>
                                        </template>
                                    </div>
                                </template>
                            </div>

                            <!-- Uploaded Proofs -->
                            <template x-if="drawerOrder.proofs && drawerOrder.proofs.length > 0">
                                <div class="space-y-2">
                                    <span class="text-[10px] font-semibold text-cocoa-400 uppercase tracking-wider block">Uploaded Proofs</span>
                                    <div class="space-y-1.5">
                                        <template x-for="proof in drawerOrder.proofs" :key="proof.id">
                                            <div class="bg-white border border-cocoa-100 rounded-lg p-2.5 text-xs flex items-center justify-between gap-3">
                                                <div>
                                                    <span class="font-semibold text-cocoa-700 capitalize" x-text="proof.status.replace('_', ' ')"></span>
                                                    <div class="text-[11px] text-cocoa-400 mt-0.5" x-text="'Uploaded ' + proof.uploaded_at"></div>
                                                </div>
                                                <a :href="proof.review_url" class="ui-button text-xs py-1 px-2.5">
                                                    Review
                                                </a>
                                            </div>
                                        </template>
                                    </div>
                                </div>
                            </template>
                        </div>

                        <!-- Drawer Footer -->
                        <div class="p-3 border-t border-cocoa-100 bg-white flex items-center justify-between gap-3 shrink-0">
                            <a :href="drawerOrder.show_url" class="ui-button primary text-xs justify-center py-2 px-3">
                                <x-icon name="clipboard" class="mr-1.5 w-3.5 h-3.5" /> Full Order Details
                            </a>
                            <button type="button" @click="closeDetailDrawer" class="ui-button quiet text-xs py-2 px-3">
                                Close
                            </button>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </div>
@endpush
