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
    <div class="orders-filters">
        <!-- Status Tabs -->
        <nav id="orders-status-tabs" aria-label="Order status filters">
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
                   data-status-tab="{{ $key }}"
                   @if($isActive) aria-current="page" @endif
                   class="orders-status-link {{ $isActive ? 'is-active' : '' }}">
                    {{ $label }}
                </a>
            @endforeach
        </nav>

        <form id="orders-filter-form" action="{{ route('orders.index') }}" method="GET">
            <input type="hidden" name="status" value="{{ request('status') }}">
            @if(request('queue'))<input type="hidden" name="queue" value="{{ request('queue') }}">@endif
            <div class="orders-filter-field orders-filter-search">
                <label for="orders-search">Search orders</label>
                <div class="orders-search-control">
                    <span class="orders-search-icon" aria-hidden="true">
                        <x-icon name="search" class="w-4 h-4 text-cocoa-400" />
                    </span>
                    <input id="orders-search" type="text" name="search" value="{{ request('search') }}"
                           placeholder="Order #, name, or phone"
                           autocomplete="off"
                           class="orders-search-input">
                    <button type="button" data-clear-search class="orders-search-clear {{ request('search') ? '' : 'hidden' }}" aria-label="Clear search">
                        <x-icon name="close" class="w-3.5 h-3.5" />
                    </button>
                </div>
            </div>
            <div class="orders-filter-field orders-filter-origin">
                <label for="orders-origin">Order origin</label>
                <select id="orders-origin" name="origin">
                    <option value="">All Origins</option>
                    <option value="public" {{ request('origin') === 'public' ? 'selected' : '' }}>Public Web Orders</option>
                    <option value="staff" {{ request('origin') === 'staff' ? 'selected' : '' }}>Staff Created</option>
                </select>
            </div>
            <div class="orders-filter-field">
                <label for="filter-date-from">Pickup from</label>
                <input id="filter-date-from" type="date" name="date_from" value="{{ request('date_from') }}">
            </div>
            <div class="orders-filter-field">
                <label for="filter-date-to">Pickup to</label>
                <input id="filter-date-to" type="date" name="date_to" value="{{ request('date_to') }}">
            </div>
        </form>

        <div id="orders-results-meta">
            <span class="orders-results-count" aria-live="polite" aria-atomic="true">
                {{ number_format($orders->total()) }} {{ $orders->total() === 1 ? 'order' : 'orders' }}
                @if(request()->hasAny(['search', 'origin', 'date_from', 'date_to', 'status', 'queue']))
                    <span class="text-cocoa-400">matching active filters</span>
                @endif
            </span>
            <div class="orders-results-actions">
                <div class="orders-filter-feedback">
                    <div id="orders-filter-loading" class="hidden items-center gap-1.5 text-xs text-cocoa-400" role="status" aria-label="Filtering orders">
                        <svg class="animate-spin h-3.5 w-3.5 text-cocoa-500" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                        </svg>
                        <span>Filtering…</span>
                    </div>
                </div>
                @if(request()->hasAny(['status', 'search', 'origin', 'queue', 'date_from', 'date_to']))
                    <a href="{{ route('orders.index') }}" data-filter-reset class="orders-filter-reset" aria-label="Reset all filters">
                        <x-icon name="close" class="w-3 h-3" />
                        <span>Reset filters</span>
                    </a>
                @endif
            </div>
        </div>
    </div>

    <!-- Orders Table -->
    <div id="orders-table-container" class="bg-white rounded-xl border border-cocoa-100 overflow-hidden transition-opacity duration-150">
        @if ($orders->isEmpty())
            <div class="py-12 text-center text-sm text-cocoa-400 space-y-2">
                <p>No orders found matching the filter criteria.</p>
                @if(request()->hasAny(['status', 'search', 'origin', 'queue', 'date_from', 'date_to']))
                    <p>
                        <a href="{{ route('orders.index') }}" data-filter-reset class="inline-flex items-center gap-1 text-sm font-medium text-cocoa-600 hover:text-cocoa-800 underline">
                            <x-icon name="close" class="w-3.5 h-3.5" />
                            <span>Reset all filters</span>
                        </a>
                    </p>
                @endif
            </div>
        @else
            <div class="table-scroll orders-scroll" role="region" aria-label="Scrollable data table" tabindex="0">
                <table class="w-full text-left orders-table">
                    <thead class="bg-cream-100 text-cocoa-500 text-xs font-semibold border-b border-cocoa-100">
                        <tr>
                            <th class="py-2.5 px-3">Order #</th>
                            <th class="py-2.5 px-3">Customer</th>
                            <th class="py-2.5 px-3">Items</th>
                            <th class="py-2.5 px-3">Pickup Schedule</th>
                            <th class="py-2.5 px-3">Total Amount</th>
                            <th class="py-2.5 px-3">Payment</th>
                            <th class="py-2.5 px-3">Status</th>
                            <th class="py-2.5 px-3 text-right">Action</th>
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
                                ];
                            @endphp
                            <tr class="text-xs text-cocoa-600 hover:bg-cream-50 transition cursor-pointer group"
                                @click="$dispatch('open-order-detail', @js($orderData))">
                                <td class="py-1.5 px-3 whitespace-nowrap align-middle">
                                    <a href="{{ route('orders.show', $order) }}" 
                                       class="font-mono font-semibold text-xs text-cocoa-700 hover:underline group-hover:text-amber-800 block leading-tight"
                                       @click="if (!$event.ctrlKey && !$event.metaKey && $event.button === 0) { $event.preventDefault(); $event.stopPropagation(); $dispatch('open-order-detail', @js($orderData)); } else { $event.stopPropagation(); }">
                                        {{ $order->order_number }}
                                    </a>
                                </td>
                                <td class="py-1.5 px-3 whitespace-nowrap align-middle">
                                    <div class="font-semibold text-xs text-cocoa-700 leading-tight">{{ $order->customer->full_name }}</div>
                                    <div class="text-[11px] text-cocoa-400 font-mono leading-tight mt-0.5">{{ $order->customer->phone_number }}</div>
                                </td>
                                @php
                                    $pkgQty = $order->orderDetails->sum('quantity');
                                    $extrasQty = $order->orderDetails->flatMap->addOns->sum('quantity');
                                @endphp
                                <td class="py-1.5 px-3 whitespace-nowrap align-middle">
                                    <div class="package-count">{{ $pkgQty }} {{ $order->fixed_catalog_pricing ? \Illuminate\Support\Str::plural('package', $pkgQty) : \Illuminate\Support\Str::plural('item', $pkgQty) }}</div>
                                    @if ($extrasQty > 0)
                                        <div class="text-[11px] text-cocoa-400 leading-tight mt-0.5">+ {{ $extrasQty }} {{ \Illuminate\Support\Str::plural('extra', $extrasQty) }}</div>
                                    @endif
                                </td>
                                <td class="py-1.5 px-3 whitespace-nowrap align-middle">
                                    <div class="font-semibold text-xs text-cocoa-700 leading-tight">{{ $order->pickup_date->format('M d, Y') }}</div>
                                    <div class="text-[11px] text-cocoa-400 leading-tight mt-0.5">{{ \Carbon\Carbon::parse($order->pickup_time)->format('h:i A') }}</div>
                                </td>
                                <td class="py-1.5 px-3 whitespace-nowrap font-semibold text-xs text-cocoa-700 tabular-nums align-middle">
                                    ₱{{ number_format($order->total_amount, 2) }}
                                </td>
                                <td class="py-1.5 px-3 whitespace-nowrap align-middle">
                                    @if ($order->status === 'cancelled')
                                        @if ($order->amount_paid > 0)
                                            <span class="inline-flex items-center text-xs font-semibold px-2 py-0.5 rounded text-amber-800 bg-amber-50 border border-amber-200" title="Deposit recorded">
                                                ₱{{ number_format($order->amount_paid, 2) }} paid
                                            </span>
                                        @else
                                            <span class="text-xs text-cocoa-400 font-medium">—</span>
                                        @endif
                                    @else
                                        @if($order->payment_status === 'partially_paid')
                                            <span class="payment-split"><span>₱{{ number_format($order->amount_paid, 2) }} paid</span><strong>₱{{ number_format($order->remaining_balance, 2) }} due</strong></span>
                                        @else
                                            <x-status :value="$order->payment_status" />
                                        @endif
                                    @endif
                                </td>
                                <td class="py-1.5 px-3 whitespace-nowrap align-middle">
                                    <x-status :value="$order->status" :label="$order->workflowLabel()" class="order-workflow-status" />
                                </td>
                                <td class="py-1.5 px-3 text-right whitespace-nowrap align-middle" @click.stop>
                                    <a href="{{ route('orders.show', $order) }}{{ $order->hasAwaitingReceipt() && Gate::allows('review-proofs') ? '#proof-review' : ($order->status === 'ready_for_pickup' ? ($order->remaining_balance > 0 ? '#pickup-payment' : '#order-actions') : '') }}"
                                       class="bg-white border border-cocoa-100 text-cocoa-600 hover:bg-cream-100 font-medium text-xs px-2.5 py-1 rounded-md transition inline-block shadow-2xs">{{ $order->hasAwaitingReceipt() && Gate::allows('review-proofs') ? 'Verify Receipt' : ($order->status === 'ready_for_pickup' && Gate::allows('record-payments') ? 'Complete Pickup' : ($order->status === 'pending' ? 'Review' : 'View')) }}</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="px-4 py-3 border-t border-cocoa-100/60">
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
        expandedImage: null,
        openOrderDetail(order) {
            this.drawerOrder = order;
            this.showDetailDrawer = true;
            this.detailLoading = true;
            this.expandedImage = null;

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
            this.expandedImage = null;
        }
    }"
    @open-order-detail.window="openOrderDetail($event.detail)"
    @keydown.escape.window="if (expandedImage) { expandedImage = null; } else { closeDetailDrawer(); }"
    x-show="showDetailDrawer"
    x-cloak
    data-detail-drawer
    class="fixed left-0 right-0 bottom-0 z-30 overflow-hidden"
    style="top: var(--topbar-height, 3.5rem); height: calc(100dvh - var(--topbar-height, 3.5rem)); display: none;">

        <!-- Backdrop scrim: strictly below the header, clean dark scrim WITHOUT backdrop-blur -->
        <div data-dialog-backdrop x-show="showDetailDrawer"
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
                 class="w-screen max-w-md sm:max-w-[440px] bg-white border-l border-cocoa-100 shadow-2xl flex flex-col h-full pointer-events-auto"
                 data-dialog
                 role="dialog"
                 aria-modal="true"
                 aria-label="Order details">

                <template x-if="drawerOrder">
                    <div class="flex flex-col h-full overflow-hidden">
                        <!-- Drawer Header -->
                        <div class="px-4 py-3 bg-cream-50/80 border-b border-cocoa-100/90 flex items-center justify-between gap-3 shrink-0">
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <span class="status text-[11px]" :class="drawerOrder.has_unreviewed_proof || drawerOrder.status === 'pending' ? 'status-action' : (drawerOrder.status === 'cancelled' ? 'status-neutral' : (['ready_for_pickup','completed'].includes(drawerOrder.status) || drawerOrder.amount_paid > 0 && drawerOrder.status === 'confirmed' ? 'status-success' : 'status-progress'))"
                                          x-text="drawerOrder.workflow_label || drawerOrder.status"></span>
                                    <span x-show="detailLoading" class="text-[10px] text-cocoa-400 italic">Syncing...</span>
                                </div>
                                <div class="flex items-center gap-2 mt-1">
                                    <h2 class="text-sm font-bold text-cocoa-900 font-mono tracking-tight" x-text="'#' + drawerOrder.order_number"></h2>
                                    <template x-if="drawerOrder.origin_label">
                                        <span class="text-[10px] text-cocoa-500 font-medium px-1.5 py-0.5 rounded bg-cocoa-100/60" x-text="drawerOrder.origin_label"></span>
                                    </template>
                                </div>
                            </div>
                            <button type="button"
                                    @click="closeDetailDrawer"
                                    class="text-cocoa-400 hover:text-cocoa-700 p-1.5 rounded-lg hover:bg-cocoa-100/70 transition focus:outline-none shrink-0 cursor-pointer"
                                    aria-label="Close detail panel">
                                <x-icon name="close" class="w-4 h-4" />
                            </button>
                        </div>

                        <!-- Drawer Body (Scrollable with refined spacing) -->
                        <div class="flex-1 overflow-y-auto p-3.5 sm:p-4 space-y-2.5 sm:space-y-3 text-sm drawer-scrollable">
                            <!-- Needs Review / Proof Alert -->
                            <template x-if="drawerOrder.has_unreviewed_proof">
                                <div class="bg-amber-50 border border-amber-200 text-amber-900 rounded-xl p-2.5 text-xs flex items-center justify-between gap-2 shadow-xs">
                                    <div class="flex items-center gap-2 min-w-0">
                                        <x-icon name="warning" class="w-4 h-4 text-amber-600 shrink-0" />
                                        <span class="truncate">Payment receipt uploaded and awaiting verification.</span>
                                    </div>
                                    <a :href="drawerOrder.show_url" class="font-bold underline shrink-0 hover:text-amber-950">Review</a>
                                </div>
                            </template>

                            <!-- Integrated Order Overview Card (Customer, Pickup & Financials Unified) -->
                            <div class="bg-white border border-cocoa-100 rounded-xl shadow-xs overflow-hidden">
                                <!-- Customer & Pickup Row -->
                                <div class="p-3 bg-cream-50/60 border-b border-cocoa-100/70 grid grid-cols-2 gap-3 text-xs">
                                    <div class="min-w-0">
                                        <span class="text-[10px] font-semibold text-cocoa-400 uppercase tracking-wider block">Customer</span>
                                        <div class="font-bold text-cocoa-800 text-xs mt-0.5 truncate" x-text="drawerOrder.customer_name"></div>
                                        <div class="text-[11px] text-cocoa-500 mt-0.5 tabular-nums truncate" x-text="drawerOrder.customer_phone"></div>
                                    </div>
                                    <div class="min-w-0">
                                        <span class="text-[10px] font-semibold text-cocoa-400 uppercase tracking-wider block">Pickup Schedule</span>
                                        <div class="font-bold text-cocoa-800 text-xs mt-0.5 truncate" x-text="drawerOrder.pickup_date"></div>
                                        <div class="text-[11px] text-cocoa-500 mt-0.5 truncate" x-text="drawerOrder.pickup_time"></div>
                                    </div>
                                </div>

                                <!-- Financial Breakdown Stats Ribbon -->
                                <div class="p-2.5 bg-white grid grid-cols-3 divide-x divide-cocoa-100/60 text-center text-xs">
                                    <div class="px-2 text-left">
                                        <span class="text-cocoa-400 block font-medium text-[10px] uppercase tracking-wider">Total</span>
                                        <span class="font-extrabold text-cocoa-900 text-sm mt-0.5 block tabular-nums" x-text="'₱' + drawerOrder.formatted_total"></span>
                                    </div>
                                    <div class="px-2 text-left">
                                        <span class="text-cocoa-400 block font-medium text-[10px] uppercase tracking-wider">Paid</span>
                                        <span class="font-extrabold text-emerald-700 text-sm mt-0.5 block tabular-nums" x-text="'₱' + drawerOrder.formatted_paid"></span>
                                    </div>
                                    <div class="px-2 text-left">
                                        <span class="text-cocoa-400 block font-medium text-[10px] uppercase tracking-wider">Balance</span>
                                        <span class="font-extrabold text-sm mt-0.5 block tabular-nums" 
                                              :class="drawerOrder.status !== 'cancelled' && drawerOrder.remaining_balance > 0 ? 'text-amber-700' : 'text-cocoa-600'" 
                                              x-text="drawerOrder.status === 'cancelled' ? '₱0.00' : '₱' + drawerOrder.formatted_balance"></span>
                                    </div>
                                </div>

                                <!-- 50% Deposit Callout if applicable -->
                                <template x-if="drawerOrder.status !== 'cancelled' && drawerOrder.amount_paid == 0 && drawerOrder.remaining_balance > 0">
                                    <div class="px-3 py-1.5 bg-amber-50/70 border-t border-amber-200/60 flex items-center justify-between text-xs text-amber-900">
                                        <span>Exact 50% deposit due <small class="text-amber-700/80 block text-[10px]" x-show="drawerOrder.status === 'pending'">Collect after staff approval</small></span>
                                        <strong class="font-bold text-amber-950 tabular-nums" x-text="'₱' + drawerOrder.formatted_down_payment"></strong>
                                    </div>
                                </template>
                            </div>

                            <!-- Ordered Items Breakdown -->
                            <div class="space-y-1.5">
                                <div class="flex items-center justify-between">
                                    <span class="text-[10px] font-bold text-cocoa-400 uppercase tracking-wider">Ordered Items</span>
                                    <span class="text-[10px] text-cocoa-400 font-medium" x-text="(drawerOrder.items ? drawerOrder.items.length : 0) + ' item' + ((drawerOrder.items && drawerOrder.items.length === 1) ? '' : 's')"></span>
                                </div>

                                <div class="space-y-2">
                                    <template x-for="item in drawerOrder.items" :key="item.id">
                                        <div class="bg-white border border-cocoa-100 rounded-xl p-2.5 shadow-xs space-y-2">
                                            <!-- Product header row -->
                                            <div class="flex items-center gap-2.5">
                                                <template x-if="item.photo_url">
                                                    <button type="button" 
                                                            @click="expandedImage = item.photo_url" 
                                                            :aria-label="'Preview ' + item.product_name" 
                                                            class="drawer-thumb-btn relative w-12 h-12 rounded-lg overflow-hidden border border-cocoa-100 bg-cream-50 shrink-0 cursor-pointer hover:opacity-90 group transition">
                                                        <img :src="item.photo_url" :alt="item.product_name" class="w-full h-full object-cover">
                                                        <div class="absolute inset-0 bg-black/20 opacity-0 group-hover:opacity-100 transition flex items-center justify-center text-white">
                                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0zM10 7v6m3-3H7"/></svg>
                                                        </div>
                                                    </button>
                                                </template>
                                                <div class="min-w-0 flex-1">
                                                    <div class="flex items-baseline justify-between gap-2">
                                                        <span class="font-bold text-cocoa-800 text-xs leading-snug truncate" x-text="item.product_name"></span>
                                                        <span class="font-extrabold text-cocoa-900 text-xs tabular-nums shrink-0" x-text="'₱' + item.subtotal"></span>
                                                    </div>
                                                    <div class="text-[11px] text-cocoa-400 mt-0.5" x-text="'Qty: ' + item.quantity + ' × ₱' + item.unit_price"></div>
                                                </div>
                                            </div>

                                            <!-- Customization Specs (Compact Pills) -->
                                            <template x-if="item.layers || item.themes || item.special_request || (drawerOrder.images && drawerOrder.images.length > 0 && drawerOrder.items && drawerOrder.items[0]?.id === item.id)">
                                                <div class="pt-2 border-t border-cocoa-100/70 space-y-1.5 text-xs">
                                                    <!-- Horizontal Tags for Layers & Theme -->
                                                    <div class="flex flex-wrap items-center gap-1.5" x-show="item.layers || item.themes">
                                                        <template x-if="item.layers">
                                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[11px] bg-cream-50 border border-cocoa-100/80 text-cocoa-700 font-medium">
                                                                <span class="text-cocoa-400">Layers:</span>
                                                                <strong class="text-cocoa-800" x-text="item.layers"></strong>
                                                            </span>
                                                        </template>
                                                        <template x-if="item.themes">
                                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[11px] bg-cream-50 border border-cocoa-100/80 text-cocoa-700 font-medium">
                                                                <span class="text-cocoa-400">Theme:</span>
                                                                <strong class="text-cocoa-800" x-text="item.themes"></strong>
                                                            </span>
                                                        </template>
                                                    </div>

                                                    <!-- Special Request Note Box -->
                                                    <template x-if="item.special_request">
                                                        <div class="text-[11px] text-cocoa-700 bg-amber-50/50 border border-amber-200/50 rounded-md px-2 py-1 leading-snug">
                                                            <span class="font-semibold text-amber-800">Request:</span>
                                                            <span class="italic text-cocoa-800" x-text="item.special_request"></span>
                                                        </div>
                                                    </template>

                                                    <!-- Customer Design Reference Photo (attached directly to item) -->
                                                    <template x-if="drawerOrder.images && drawerOrder.images.length > 0 && drawerOrder.items && drawerOrder.items[0]?.id === item.id">
                                                        <div class="flex items-center justify-between gap-2 pt-0.5">
                                                            <span class="text-[11px] font-medium text-cocoa-600 flex items-center gap-1.5">
                                                                <svg class="w-3.5 h-3.5 text-cocoa-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                                                <span>Design reference:</span>
                                                            </span>
                                                            <div class="flex items-center gap-1.5 shrink-0">
                                                                <template x-for="img in drawerOrder.images" :key="img.id">
                                                                    <button type="button" 
                                                                            @click="expandedImage = img.url" 
                                                                            aria-label="Preview customer reference" 
                                                                            class="drawer-thumb-btn relative w-8 h-8 rounded-md overflow-hidden border border-cocoa-200 bg-white shrink-0 cursor-pointer hover:ring-2 hover:ring-amber-500 transition shadow-2xs"
                                                                            title="Click to zoom reference photo">
                                                                        <img :src="img.url" alt="Customer reference design" class="w-full h-full object-cover">
                                                                    </button>
                                                                </template>
                                                            </div>
                                                        </div>
                                                    </template>
                                                </div>
                                            </template>

                                            <!-- Extras / Add-ons -->
                                            <template x-if="item.add_ons && item.add_ons.length > 0">
                                                <div class="pt-1.5 border-t border-cocoa-100/70 space-y-1">
                                                    <span class="text-[10px] font-bold text-cocoa-400 uppercase tracking-wider block">Extras / Add-ons</span>
                                                    <div class="space-y-1">
                                                        <template x-for="addon in item.add_ons" :key="addon.name">
                                                            <div class="flex items-center justify-between text-[11px] bg-cream-50/50 px-2 py-0.5 rounded border border-cocoa-100/60">
                                                                <span class="text-cocoa-700" x-text="addon.quantity + '× ' + addon.name"></span>
                                                                <span class="font-semibold text-cocoa-800 tabular-nums" x-text="'₱' + addon.price"></span>
                                                            </div>
                                                        </template>
                                                    </div>
                                                </div>
                                            </template>
                                        </div>
                                    </template>

                                    <!-- Standalone Reference Photos Fallback (if no items exist) -->
                                    <template x-if="drawerOrder.images && drawerOrder.images.length > 0 && (!drawerOrder.items || drawerOrder.items.length === 0)">
                                        <div class="flex items-center justify-between gap-2 px-2.5 py-1.5 bg-cream-50/80 border border-cocoa-100 rounded-lg text-xs">
                                            <div class="flex items-center gap-1.5 text-cocoa-600 shrink-0">
                                                <svg class="w-3.5 h-3.5 text-cocoa-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                                <span class="text-[10px] font-bold text-cocoa-700 uppercase tracking-wider">Design Reference</span>
                                            </div>
                                            <div class="flex items-center gap-1.5 overflow-x-auto py-0.5">
                                                <template x-for="img in drawerOrder.images" :key="img.id">
                                                    <button type="button" 
                                                            @click="expandedImage = img.url" 
                                                            aria-label="Preview customer reference" 
                                                            class="drawer-thumb-btn relative w-8 h-8 rounded-md overflow-hidden border border-cocoa-200 bg-white shrink-0 cursor-pointer hover:ring-2 hover:ring-amber-500 transition shadow-2xs"
                                                            title="Click to zoom reference photo">
                                                        <img :src="img.url" alt="Customer reference design" class="w-full h-full object-cover">
                                                    </button>
                                                </template>
                                            </div>
                                        </div>
                                    </template>
                                </div>
                            </div>

                            <!-- Notes Section (Compact Callout) -->
                            <template x-if="drawerOrder.notes_text">
                                <div class="flex items-start gap-2 px-2.5 py-2 bg-amber-50/50 border border-amber-200/70 rounded-xl text-xs text-cocoa-700 shadow-2xs">
                                    <svg class="w-3.5 h-3.5 text-amber-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z"/></svg>
                                    <div class="min-w-0 flex-1 leading-snug">
                                        <span class="text-[10px] font-bold uppercase tracking-wider text-amber-800 mr-1.5">Order Note:</span>
                                        <span class="text-[11px] italic text-cocoa-800" x-text="drawerOrder.notes_text"></span>
                                    </div>
                                </div>
                            </template>

                            <!-- Payments & Verification History (Unified Card) -->
                            <div class="space-y-1.5">
                                <div class="flex items-center justify-between">
                                    <span class="text-[10px] font-bold text-cocoa-400 uppercase tracking-wider">Payments & Receipts</span>
                                    <template x-if="drawerOrder.payments && drawerOrder.payments.length > 0">
                                        <span class="text-[10px] text-cocoa-400 font-medium" x-text="drawerOrder.payments.length + ' payment' + (drawerOrder.payments.length === 1 ? '' : 's')"></span>
                                    </template>
                                </div>

                                <div class="bg-white border border-cocoa-100 rounded-xl overflow-hidden shadow-xs divide-y divide-cocoa-100/60">
                                    <!-- Recorded Payments -->
                                    <template x-if="!drawerOrder.payments || drawerOrder.payments.length === 0">
                                        <div class="p-2.5 text-center text-xs text-cocoa-400">
                                             No payments recorded yet.
                                        </div>
                                    </template>
                                    <template x-if="drawerOrder.payments && drawerOrder.payments.length > 0">
                                        <div>
                                            <template x-for="payment in drawerOrder.payments" :key="payment.id">
                                                <div class="p-2.5 text-xs flex items-center justify-between gap-3">
                                                    <div class="min-w-0">
                                                        <div class="flex items-center gap-1.5">
                                                            <span class="font-bold text-cocoa-800 text-xs" x-text="payment.payment_type"></span>
                                                            <span class="text-cocoa-300">·</span>
                                                            <span class="text-cocoa-600 font-medium text-[11px]" x-text="payment.payment_method"></span>
                                                        </div>
                                                        <div class="text-[10px] text-cocoa-400 mt-0.5 truncate" x-text="payment.paid_at + (payment.reference_number ? ' · Ref: ' + payment.reference_number : '')"></div>
                                                    </div>
                                                    <div class="text-right shrink-0">
                                                        <span class="font-extrabold text-emerald-700 text-xs tabular-nums block" x-text="'₱' + payment.amount"></span>
                                                        <template x-if="payment.recorded_by">
                                                            <span class="text-[10px] text-cocoa-400 block" x-text="'by ' + payment.recorded_by"></span>
                                                        </template>
                                                    </div>
                                                </div>
                                            </template>
                                        </div>
                                    </template>

                                    <!-- Customer Uploaded Proof Receipts -->
                                    <template x-if="drawerOrder.proofs && drawerOrder.proofs.length > 0">
                                        <div class="bg-cream-50/30 divide-y divide-cocoa-100/50">
                                            <template x-for="proof in drawerOrder.proofs" :key="proof.id">
                                                <div class="p-2.5 text-xs flex items-center justify-between gap-3">
                                                    <div class="flex items-center gap-2.5 min-w-0">
                                                        <button type="button"
                                                                @click="expandedImage = proof.receipt_url"
                                                                class="drawer-thumb-btn shrink-0 relative w-9 h-9 rounded-lg border border-cocoa-100 overflow-hidden bg-white hover:opacity-90 focus:outline-none transition group cursor-pointer"
                                                                title="Click to view full receipt">
                                                            <img :src="proof.receipt_url" alt="Receipt preview" class="w-full h-full object-cover">
                                                            <div class="absolute inset-0 bg-black/20 opacity-0 group-hover:opacity-100 transition flex items-center justify-center text-white">
                                                                <svg class="w-3.5 h-3.5 drop-shadow" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0zM10 7v6m3-3H7" />
                                                                </svg>
                                                            </div>
                                                        </button>

                                                        <div class="min-w-0 flex-1">
                                                            <div class="flex items-center gap-1.5 flex-wrap">
                                                                <template x-if="proof.status === 'verified'">
                                                                    <span class="inline-flex items-center px-1.5 py-0.2 rounded text-[10px] font-bold bg-emerald-50 text-emerald-800 border border-emerald-200 uppercase tracking-wide">Verified</span>
                                                                </template>
                                                                <template x-if="proof.status === 'rejected'">
                                                                    <span class="inline-flex items-center px-1.5 py-0.2 rounded text-[10px] font-bold bg-rose-50 text-rose-800 border border-rose-200 uppercase tracking-wide">Rejected</span>
                                                                </template>
                                                                <template x-if="proof.status === 'awaiting_verification'">
                                                                    <span class="inline-flex items-center px-1.5 py-0.2 rounded text-[10px] font-bold bg-amber-50 text-amber-800 border border-amber-200 uppercase tracking-wide">Awaiting Verification</span>
                                                                </template>

                                                                <template x-if="proof.reference_number">
                                                                    <span class="font-mono text-[10px] text-cocoa-600 font-medium truncate" x-text="'Ref: ' + proof.reference_number"></span>
                                                                </template>
                                                            </div>

                                                            <div class="text-[10px] text-cocoa-400 mt-0.5" x-text="'Uploaded ' + proof.uploaded_at"></div>

                                                            <template x-if="proof.status === 'rejected' && proof.rejection_reason">
                                                                <div class="text-[10px] text-rose-700 mt-0.5 italic leading-snug" x-text="'Reason: ' + proof.rejection_reason"></div>
                                                            </template>
                                                        </div>
                                                    </div>

                                                    <div class="shrink-0">
                                                        <template x-if="proof.status === 'awaiting_verification'">
                                                            <a :href="proof.review_url" class="ui-button primary text-xs py-1 px-2.5">
                                                                Review
                                                            </a>
                                                        </template>
                                                        <template x-if="proof.status !== 'awaiting_verification'">
                                                            <button type="button"
                                                                    @click="expandedImage = proof.receipt_url"
                                                                    class="ui-button quiet text-xs py-1 px-2 font-medium text-cocoa-600 hover:text-cocoa-800 cursor-pointer">
                                                                View Receipt
                                                            </button>
                                                        </template>
                                                    </div>
                                                </div>
                                            </template>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </div>

                        <!-- Drawer Footer -->
                        <div class="p-3 border-t border-cocoa-100 bg-white flex items-center justify-between gap-3 shrink-0">
                            <a :href="drawerOrder.show_url" class="ui-button primary drawer-btn text-xs justify-center flex-1">
                                <x-icon name="clipboard" class="mr-1.5 w-3.5 h-3.5" /> Full Order Details
                            </a>
                            <button type="button" @click="closeDetailDrawer" class="ui-button quiet drawer-btn text-xs px-4 shrink-0 cursor-pointer">
                                Close
                            </button>
                        </div>
                    </div>
                </template>
            </div>
        </div>

        <!-- Fullscreen Image Preview Lightbox -->
        <template x-teleport="body">
            <div x-show="expandedImage"
                 x-cloak
                 style="display: none;"
                 class="fixed inset-0 z-[100] flex items-center justify-center bg-black/80 p-4 backdrop-blur-sm"
                 @keydown.escape.window="expandedImage = null"
                 data-dialog
                 role="dialog"
                 aria-modal="true"
                 aria-label="Screenshot preview">
                <div class="relative max-w-4xl max-h-[90vh] flex flex-col items-center justify-center" @click.outside="expandedImage = null">
                    <div class="absolute -top-10 right-0 flex items-center gap-3">
                        <a :href="expandedImage" target="_blank" class="text-white/80 hover:text-white text-xs flex items-center gap-1 underline" title="Open original image in new tab">
                            <span>Open original</span>
                            <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" /></svg>
                        </a>
                        <button type="button" @click="expandedImage = null" class="text-white hover:text-gray-300 focus:outline-none" aria-label="Close image preview">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 drop-shadow-md" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                        </button>
                    </div>
                    <img :src="expandedImage" alt="Expanded preview" class="max-w-full max-h-[85vh] object-contain rounded-lg shadow-2xl bg-black/40">
                </div>
            </div>
        </template>
    </div>
@endpush
