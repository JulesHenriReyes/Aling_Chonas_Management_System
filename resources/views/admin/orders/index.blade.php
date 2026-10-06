@extends('layouts.admin')

@section('title', 'Manage Orders')

@section('content')
<div class="space-y-6">
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
                        default => $currentStatus === $key && empty($currentQueue),
                    };
                    $queryParam = $key === 'deposit'
                        ? ['status' => null, 'queue' => 'deposit', 'page' => 1]
                        : ['status' => $key ?: null, 'queue' => null, 'page' => 1];
                @endphp
                <a href="{{ route('orders.index', array_merge(request()->query(), $queryParam)) }}"
                   class="px-3 py-1.5 rounded-lg font-medium transition {{ $isActive ? 'bg-cocoa-600 text-white' : 'text-cocoa-500 hover:bg-cream-100' }}">
                    {{ $label }}
                </a>
            @endforeach
        </div>

        <form action="{{ route('orders.index') }}" method="GET" class="flex flex-col sm:flex-row gap-3">
            <input type="hidden" name="status" value="{{ request('status') }}">
            @if(request('queue'))<input type="hidden" name="queue" value="{{ request('queue') }}">@endif
            <div class="flex-grow">
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
            <button type="submit" class="bg-white border border-cocoa-100 text-cocoa-500 hover:bg-cream-100 font-medium text-sm px-4 py-2 rounded-lg transition">
                Filter
            </button>
            @if(request()->hasAny(['status', 'search', 'origin', 'queue']))
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
                    <thead class="bg-cream-100 text-cocoa-400 text-xs  font-semibold">
                        <tr>
                            <th class="p-4">Order #</th>
                            <th class="p-4">Customer</th>
                            <th class="p-4">Origin</th>
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
                            <tr class="text-sm text-cocoa-500 hover:bg-cream-50 transition">
                                <td class="p-4 font-mono font-semibold text-cocoa-600">
                                    <a href="{{ route('orders.show', $order) }}" class="hover:underline">
                                        {{ $order->order_number }}
                                    </a>
                                </td>
                                <td class="p-4">
                                    <div class="font-semibold text-cocoa-600">{{ $order->customer->full_name }}</div>
                                    <div class="text-xs text-cocoa-400">{{ $order->customer->phone_number }}</div>
                                </td>
                                <td class="p-4">
                                    @if ($order->user_id === null)
                                        <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-medium text-cocoa-500">
                                            Public Web
                                            <x-tooltip text="Order submitted online by customer via public storefront." />
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1 text-xs text-cocoa-500">
                                            Staff ({{ $order->user->first_name }})
                                            <x-tooltip text="Order manually created by staff member {{ $order->user->first_name }}." />
                                        </span>
                                    @endif
                                </td>
                                <td class="p-4">
                                    <span class="font-medium text-cocoa-600">{{ $order->orderDetails->sum('quantity') }} {{ $order->fixed_catalog_pricing ? 'packages' : 'items' }}</span>
                                    @if ($order->orderDetails->flatMap->addOns->sum('quantity'))
                                        <span class="block text-sm">{{ $order->orderDetails->flatMap->addOns->sum('quantity') }} paid extras</span>
                                    @endif
                                    <span class="text-cocoa-400 block text-xs">{{ $order->orderDetails->count() }} product lines</span>
                                </td>
                                <td class="p-4">
                                    <div class="font-semibold text-cocoa-600">{{ $order->pickup_date->format('M d, Y') }}</div>
                                    <div class="text-xs text-cocoa-400">{{ \Carbon\Carbon::parse($order->pickup_time)->format('h:i A') }}</div>
                                </td>
                                <td class="p-4 font-semibold text-cocoa-600">
                                    ₱{{ number_format($order->total_amount, 2) }}
                                </td>
                                <td class="p-4">
                                    <x-status :value="$order->payment_status" />
                                    @if($order->payment_status === 'partially_paid')
                                        <span class="block text-xs text-cocoa-400 mt-1">Bal: ₱{{ number_format($order->remaining_balance, 2) }}</span>
                                    @endif
                                </td>
                                <td class="p-4">
                                    <x-status :value="$order->status" :label="$order->workflowLabel()" class="order-workflow-status" />
                                </td>
                                <td class="p-4 text-right">
                                    <a href="{{ route('orders.show', $order) }}" 
                                       class="bg-white border border-cocoa-100 text-cocoa-500 hover:bg-cream-100 font-medium text-sm px-3 py-1.5 rounded-lg transition inline-block">{{ $order->status === 'pending' ? 'Review' : 'View' }}</a>
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
