@extends('layouts.admin')

@section('title', 'Manage Orders')

@section('content')
<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-stone-900">Order Management</h1>
            <p class="text-xs text-stone-500 mt-1">Review public submissions, adjust custom pricing, and record payments.</p>
        </div>
        <a href="{{ route('orders.create') }}" class="px-4 py-2 bg-rose-900 hover:bg-rose-800 text-white font-bold text-xs rounded-xl shadow transition self-start sm:self-auto">
            + New Staff Order
        </a>
    </div>

    <!-- Filters & Search -->
    <div class="bg-white p-4 rounded-2xl border border-stone-200 shadow-sm space-y-4">
        <!-- Status Tabs -->
        <div class="flex flex-wrap gap-1 text-xs border-b border-stone-200 pb-3">
            @php
                $currentStatus = request('status', '');
                $statuses = [
                    '' => 'All Statuses',
                    'pending' => 'Pending Review',
                    'confirmed' => 'Confirmed',
                    'preparing' => 'Preparing',
                    'ready_for_pickup' => 'Ready for Pickup',
                    'completed' => 'Completed',
                    'cancelled' => 'Cancelled',
                ];
            @endphp
            @foreach ($statuses as $key => $label)
                <a href="{{ route('orders.index', array_merge(request()->query(), ['status' => $key, 'page' => 1])) }}"
                   class="px-3 py-1.5 rounded-lg font-medium transition {{ $currentStatus === $key ? 'bg-rose-900 text-white' : 'text-stone-600 hover:bg-stone-100' }}">
                    {{ $label }}
                </a>
            @endforeach
        </div>

        <form action="{{ route('orders.index') }}" method="GET" class="flex flex-col sm:flex-row gap-3">
            <input type="hidden" name="status" value="{{ request('status') }}">
            <div class="flex-grow">
                <input type="text" name="search" value="{{ request('search') }}" 
                       placeholder="Search by Order #, Customer Name, or Phone..." 
                       class="w-full text-xs rounded-xl border-stone-300 focus:border-rose-700 focus:ring-rose-700 shadow-sm">
            </div>
            <div>
                <select name="origin" class="text-xs rounded-xl border-stone-300 focus:border-rose-700 focus:ring-rose-700 shadow-sm">
                    <option value="">All Origins</option>
                    <option value="public" {{ request('origin') === 'public' ? 'selected' : '' }}>Public Web Orders</option>
                    <option value="staff" {{ request('origin') === 'staff' ? 'selected' : '' }}>Staff Created</option>
                </select>
            </div>
            <button type="submit" class="px-4 py-2 bg-stone-800 hover:bg-stone-700 text-white text-xs font-bold rounded-xl shadow">
                Filter
            </button>
            @if(request()->hasAny(['status', 'search', 'origin']))
                <a href="{{ route('orders.index') }}" class="px-3 py-2 text-stone-500 hover:text-stone-800 text-xs self-center">
                    Reset
                </a>
            @endif
        </form>
    </div>

    <!-- Orders Table -->
    <div class="bg-white rounded-2xl border border-stone-200 shadow-sm overflow-hidden">
        @if ($orders->isEmpty())
            <div class="p-12 text-center text-stone-400 text-xs">
                No orders found matching the filter criteria.
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-stone-50 border-b text-stone-500 uppercase text-[10px] tracking-wider">
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
                    <tbody class="divide-y divide-stone-100">
                        @foreach ($orders as $order)
                            <tr class="hover:bg-stone-50/60 transition">
                                <td class="p-4 font-mono font-bold text-rose-950">
                                    <a href="{{ route('orders.show', $order) }}" class="hover:underline">
                                        {{ $order->order_number }}
                                    </a>
                                </td>
                                <td class="p-4">
                                    <div class="font-bold text-stone-900">{{ $order->customer->full_name }}</div>
                                    <div class="text-[11px] text-stone-500">{{ $order->customer->phone_number }}</div>
                                </td>
                                <td class="p-4">
                                    @if ($order->user_id === null)
                                        <span class="inline-block px-2 py-0.5 rounded text-[10px] font-bold bg-purple-100 text-purple-800">
                                            Public Web
                                        </span>
                                    @else
                                        <span class="inline-block px-2 py-0.5 rounded text-[10px] font-bold bg-stone-100 text-stone-700">
                                            Staff ({{ $order->user->first_name }})
                                        </span>
                                    @endif
                                </td>
                                <td class="p-4">
                                    <span class="text-stone-700">{{ $order->orderDetails->sum('quantity') }} items</span>
                                    <span class="text-stone-400 block text-[10px]">({{ $order->orderDetails->count() }} line items)</span>
                                </td>
                                <td class="p-4">
                                    <div class="font-semibold text-stone-800">{{ $order->pickup_date->format('M d, Y') }}</div>
                                    <div class="text-[11px] text-stone-500">{{ \Carbon\Carbon::parse($order->pickup_time)->format('h:i A') }}</div>
                                </td>
                                <td class="p-4 font-bold text-stone-900">
                                    ₱{{ number_format($order->total_amount, 2) }}
                                </td>
                                <td class="p-4">
                                    <span class="inline-block px-2 py-0.5 rounded text-[10px] font-bold uppercase
                                        @if($order->payment_status === 'fully_paid') bg-emerald-100 text-emerald-800
                                        @elseif($order->payment_status === 'partially_paid') bg-blue-100 text-blue-800
                                        @else bg-red-100 text-red-800 @endif">
                                        {{ str_replace('_', ' ', $order->payment_status) }}
                                    </span>
                                    @if($order->payment_status === 'partially_paid')
                                        <span class="block text-[10px] text-stone-500">Bal: ₱{{ number_format($order->remaining_balance, 2) }}</span>
                                    @endif
                                </td>
                                <td class="p-4">
                                    <span class="inline-block px-2 py-0.5 rounded text-[10px] font-bold uppercase
                                        @if($order->status === 'completed') bg-emerald-100 text-emerald-800
                                        @elseif($order->status === 'cancelled') bg-red-100 text-red-800
                                        @elseif($order->status === 'confirmed') bg-blue-100 text-blue-800
                                        @elseif($order->status === 'ready_for_pickup') bg-teal-100 text-teal-800
                                        @elseif($order->status === 'preparing') bg-indigo-100 text-indigo-800
                                        @else bg-amber-100 text-amber-800 @endif">
                                        {{ str_replace('_', ' ', $order->status) }}
                                    </span>
                                </td>
                                <td class="p-4 text-right">
                                    <a href="{{ route('orders.show', $order) }}" 
                                       class="px-3 py-1.5 bg-rose-50 hover:bg-rose-100 text-rose-900 font-bold rounded-lg transition">
                                        Review
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="p-4 border-t">
                {{ $orders->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
