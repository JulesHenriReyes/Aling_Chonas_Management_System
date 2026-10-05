@extends('layouts.admin')

@section('title', 'Dashboard')

@section('content')
<div class="space-y-6">
    {{-- Welcome --}}
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-xl font-bold text-cocoa-600">Welcome back, {{ Auth::user()->first_name }}</h1>
            <p class="text-sm text-cocoa-400 mt-0.5">Today's bakery overview · {{ \App\Support\PickupCalendar::today()->format('F j, Y') }} ({{ config('bakery.pickup_timezone') }})</p>
        </div>
        <div class="flex items-center gap-2">
            @can('manage-orders')
<a href="{{ route('orders.create') }}" class="bg-cocoa-600 hover:bg-cocoa-700 text-white font-semibold text-sm px-4 py-2 rounded-lg transition"><x-icon name="plus" class="mr-1" /> New Staff Order
            </a>
@endcan
            <a href="{{ route('public.order.index') }}" target="_blank" class="bg-white border border-cocoa-100 text-cocoa-500 hover:bg-cream-100 font-medium text-sm px-4 py-2 rounded-lg transition">
                Public Store <x-icon name="external" class="ml-1" />
            </a>
        </div>
    </div>

    {{-- Metric Cards --}}
    <div class="metrics">
        <a href="{{ route('orders.index', ['status' => 'pending']) }}" class="bg-white border border-cocoa-100 rounded-xl p-5 hover:border-cocoa-200 transition block">
            <div class="text-xs font-semibold text-cocoa-400 ">Pending Review</div>
            <div class="text-2xl font-bold text-cocoa-600 mt-1.5">{{ $pendingCount }}</div>
            <div class="text-xs text-cocoa-400 mt-1">Awaiting deposit verification</div>
        </a>

        <a href="{{ route('orders.index', ['status' => 'confirmed']) }}" class="bg-white border border-cocoa-100 rounded-xl p-5 hover:border-cocoa-200 transition block">
            <div class="text-xs font-semibold text-cocoa-400 ">Active Orders</div>
            <div class="text-2xl font-bold text-cocoa-600 mt-1.5">{{ $activeOrdersCount }}</div>
            <div class="text-xs text-cocoa-400 mt-1">Confirmed & preparing</div>
        </a>

        <a href="{{ route('orders.index', ['status' => 'completed']) }}" class="bg-white border border-cocoa-100 rounded-xl p-5 hover:border-cocoa-200 transition block">
            <div class="text-xs font-semibold text-cocoa-400 ">Completed</div>
            <div class="text-2xl font-bold text-cocoa-600 mt-1.5">{{ $completedCount }}</div>
            <div class="text-xs text-cocoa-400 mt-1">Delivered & settled</div>
        </a>

        <a href="{{ route('supplies.index') }}" aria-label="Low stock supplies: {{ $lowStockCount }} items" class="bg-white border border-cocoa-100 rounded-xl p-5 hover:border-cocoa-200 transition block">
            <div class="text-xs font-semibold text-cocoa-400 ">Low stock supplies @if($lowStockCount > 0)<x-icon name="warning" />@endif</div>
            <div class="text-2xl font-bold {{ $lowStockCount > 0 ? 'text-red-600' : 'text-cocoa-400' }} mt-1.5">{{ $lowStockCount }}</div>
            <div class="text-xs text-cocoa-400 mt-1">Below reorder level</div>
        </a>
    </div>

    {{-- Two-Column Grid --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Today's Pickups --}}
        <div class="bg-white border border-cocoa-100 rounded-xl">
            <div class="flex items-center justify-between px-5 py-4 border-b border-cocoa-100">
                <h2 class="text-sm font-semibold text-cocoa-600">Today's Pickups ({{ $todayPickups->count() }})</h2>
                <a href="{{ route('schedule.index') }}" class="text-xs text-cocoa-500 hover:text-cocoa-500 font-medium transition">View All</a>
            </div>

            @if ($todayPickups->isEmpty())
                <div class="px-5 py-10 text-center text-sm text-cocoa-400">
                    No pickups scheduled for today.
                </div>
            @else
                <div class="divide-y divide-cocoa-100/60">
                    @foreach ($todayPickups as $order)
                        <div class="px-5 py-3 hover:bg-cream-50 transition">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <a href="{{ route('orders.show', $order) }}" class="text-sm font-semibold text-cocoa-600 hover:text-cocoa-500 transition">
                                        {{ $order->order_number }}
                                    </a>
                                    <div class="text-xs text-cocoa-500 mt-0.5 truncate">{{ $order->customer->full_name }}</div>
                                </div>
                                <div class="text-right flex-shrink-0">
                                    <span class="text-xs font-mono font-semibold text-cocoa-500">
                                        {{ \Carbon\Carbon::parse($order->pickup_time)->format('h:i A') }}
                                    </span>
                                    <x-status :value="$order->status" />
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Recent Orders --}}
        <div class="lg:col-span-2 bg-white border border-cocoa-100 rounded-xl">
            <div class="flex items-center justify-between px-5 py-4 border-b border-cocoa-100">
                <h2 class="text-sm font-semibold text-cocoa-600">Recent Orders</h2>
                <a href="{{ route('orders.index') }}" class="text-xs text-cocoa-500 hover:text-cocoa-500 font-medium transition">View All Orders</a>
            </div>

            @if ($recentOrders->isEmpty())
                <div class="px-5 py-10 text-center text-sm text-cocoa-400">
                    No orders recorded yet.
                </div>
            @else
                <div class="table-scroll" role="region" aria-label="Scrollable data table" tabindex="0">
                    <table class="w-full text-left">
                        <thead>
                            <tr class="bg-cream-100 text-cocoa-400 text-xs  font-semibold">
                                <th class="px-5 py-3">Order</th>
                                <th class="px-5 py-3">Customer</th>
                                <th class="px-5 py-3">Pickup</th>
                                <th class="px-5 py-3">Total</th>
                                <th class="px-5 py-3">Status</th>
                                <th class="px-5 py-3 text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-cocoa-100/60">
                            @foreach ($recentOrders as $order)
                                <tr class="text-sm hover:bg-cream-50 transition">
                                    <td class="px-5 py-3">
                                        <a href="{{ route('orders.show', $order) }}" class="font-mono font-semibold text-cocoa-600 hover:text-cocoa-500 transition">
                                            {{ $order->order_number }}
                                        </a>
                                    </td>
                                    <td class="px-5 py-3 text-cocoa-500">{{ $order->customer->full_name }}</td>
                                    <td class="px-5 py-3 text-cocoa-400 text-xs">
                                        {{ $order->pickup_date->format('M d') }}, {{ \Carbon\Carbon::parse($order->pickup_time)->format('h:i A') }}
                                    </td>
                                    <td class="px-5 py-3 font-semibold text-cocoa-600">₱{{ number_format($order->total_amount, 2) }}</td>
                                    <td class="px-5 py-3">
                                        <x-status :value="$order->status" />
                                    </td>
                                    <td class="px-5 py-3 text-right">
                                        <a href="{{ route('orders.show', $order) }}"
                                           class="text-xs font-semibold text-cocoa-500 hover:text-cocoa-700 bg-cream-50 hover:bg-cream-100 border border-cocoa-100 px-3 py-1.5 rounded-lg transition inline-block">{{ $order->status === 'pending' ? 'Review' : 'View' }}</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
