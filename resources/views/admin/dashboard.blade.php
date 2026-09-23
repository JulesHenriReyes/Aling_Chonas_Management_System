@extends('layouts.admin')

@section('title', 'Staff Dashboard')

@section('content')
<div class="space-y-8">
    <!-- Top Welcome Banner -->
    <div class="bg-gradient-to-r from-rose-900 to-amber-900 rounded-2xl p-6 text-white shadow-md flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-black text-amber-100">Welcome, {{ Auth::user()->first_name }}!</h1>
            <p class="text-xs text-rose-200 mt-1">Here is today's overview for Aling Chona Cakes & Cupcakes.</p>
        </div>
        <div class="flex items-center gap-3">
            <a href="{{ route('orders.create') }}" class="px-4 py-2 bg-amber-400 hover:bg-amber-300 text-rose-950 font-bold text-xs rounded-xl shadow transition">
                + Create Staff Order
            </a>
            <a href="{{ route('public.order.index') }}" target="_blank" class="px-4 py-2 bg-white/10 hover:bg-white/20 text-white font-medium text-xs rounded-xl border border-white/20 transition">
                View Public Store ↗
            </a>
        </div>
    </div>

    <!-- Metric Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- Pending Review -->
        <a href="{{ route('orders.index', ['status' => 'pending']) }}" 
           class="bg-white p-5 rounded-2xl border border-stone-200 shadow-sm hover:shadow transition block">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold text-stone-500 uppercase tracking-wide">Pending Review</span>
                <span class="w-8 h-8 rounded-full bg-amber-100 text-amber-800 flex items-center justify-center font-bold text-sm">⏳</span>
            </div>
            <div class="text-3xl font-black text-amber-600 mt-2">{{ $pendingCount }}</div>
            <p class="text-[11px] text-stone-400 mt-1">Orders awaiting price review & deposit</p>
        </a>

        <!-- In Production -->
        <a href="{{ route('orders.index', ['status' => 'confirmed']) }}" 
           class="bg-white p-5 rounded-2xl border border-stone-200 shadow-sm hover:shadow transition block">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold text-stone-500 uppercase tracking-wide">Active In-Kitchen</span>
                <span class="w-8 h-8 rounded-full bg-blue-100 text-blue-800 flex items-center justify-center font-bold text-sm">👩‍🍳</span>
            </div>
            <div class="text-3xl font-black text-blue-600 mt-2">{{ $activeOrdersCount }}</div>
            <p class="text-[11px] text-stone-400 mt-1">Confirmed, preparing, or ready</p>
        </a>

        <!-- Completed Orders -->
        <a href="{{ route('orders.index', ['status' => 'completed']) }}" 
           class="bg-white p-5 rounded-2xl border border-stone-200 shadow-sm hover:shadow transition block">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold text-stone-500 uppercase tracking-wide">Completed</span>
                <span class="w-8 h-8 rounded-full bg-emerald-100 text-emerald-800 flex items-center justify-center font-bold text-sm">✓</span>
            </div>
            <div class="text-3xl font-black text-emerald-600 mt-2">{{ $completedCount }}</div>
            <p class="text-[11px] text-stone-400 mt-1">Delivered & fully settled orders</p>
        </a>

        <!-- Low Stock Alert -->
        <a href="{{ route('supplies.index') }}" 
           class="bg-white p-5 rounded-2xl border border-stone-200 shadow-sm hover:shadow transition block">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold text-stone-500 uppercase tracking-wide">Low Stock Supplies</span>
                <span class="w-8 h-8 rounded-full bg-rose-100 text-rose-800 flex items-center justify-center font-bold text-sm">⚠️</span>
            </div>
            <div class="text-3xl font-black text-rose-600 mt-2">{{ $lowStockCount }}</div>
            <p class="text-[11px] text-stone-400 mt-1">Items at or below reorder level</p>
        </a>
    </div>

    <!-- Grid: Pickups Today & Recent Orders -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Pickups Due Today (1 column) -->
        <div class="bg-white rounded-2xl border border-stone-200 p-6 shadow-sm">
            <div class="flex items-center justify-between border-b pb-4 mb-4">
                <h2 class="text-sm font-bold text-stone-900 flex items-center gap-2">
                    <span>🗓️</span> Pickups Due Today ({{ $todayPickups->count() }})
                </h2>
                <a href="{{ route('schedule.index') }}" class="text-xs text-rose-800 hover:underline">View All</a>
            </div>

            @if ($todayPickups->isEmpty())
                <p class="text-xs text-stone-400 py-8 text-center">No orders scheduled for pickup today.</p>
            @else
                <div class="divide-y divide-stone-100 space-y-3">
                    @foreach ($todayPickups as $order)
                        <div class="pt-3 first:pt-0">
                            <div class="flex items-start justify-between">
                                <div>
                                    <a href="{{ route('orders.show', $order) }}" class="font-bold text-xs text-rose-900 hover:underline">
                                        {{ $order->order_number }}
                                    </a>
                                    <div class="text-xs font-medium text-stone-800 mt-0.5">
                                        {{ $order->customer->full_name }}
                                    </div>
                                    <div class="text-[11px] text-stone-500">
                                        {{ $order->customer->phone_number }}
                                    </div>
                                </div>
                                <div class="text-right">
                                    <span class="text-xs font-mono font-bold text-stone-700 block">
                                        {{ \Carbon\Carbon::parse($order->pickup_time)->format('h:i A') }}
                                    </span>
                                    <span class="inline-block mt-1 px-2 py-0.5 rounded text-[10px] font-bold uppercase
                                        @if($order->status === 'ready_for_pickup') bg-emerald-100 text-emerald-800
                                        @elseif($order->status === 'preparing') bg-blue-100 text-blue-800
                                        @else bg-amber-100 text-amber-800 @endif">
                                        {{ str_replace('_', ' ', $order->status) }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        <!-- Recent Orders (2 columns) -->
        <div class="lg:col-span-2 bg-white rounded-2xl border border-stone-200 p-6 shadow-sm">
            <div class="flex items-center justify-between border-b pb-4 mb-4">
                <h2 class="text-sm font-bold text-stone-900 flex items-center gap-2">
                    <span>📦</span> Recent Order Submissions
                </h2>
                <a href="{{ route('orders.index') }}" class="text-xs text-rose-800 hover:underline">View All Orders</a>
            </div>

            @if ($recentOrders->isEmpty())
                <p class="text-xs text-stone-400 py-8 text-center">No orders recorded yet.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead>
                            <tr class="text-stone-500 border-b uppercase text-[10px] tracking-wider">
                                <th class="pb-2">Order</th>
                                <th class="pb-2">Customer</th>
                                <th class="pb-2">Origin</th>
                                <th class="pb-2">Pickup</th>
                                <th class="pb-2">Total</th>
                                <th class="pb-2">Status</th>
                                <th class="pb-2 text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-stone-100">
                            @foreach ($recentOrders as $order)
                                <tr>
                                    <td class="py-3 font-mono font-bold text-stone-900">
                                        <a href="{{ route('orders.show', $order) }}" class="text-rose-900 hover:underline">
                                            {{ $order->order_number }}
                                        </a>
                                    </td>
                                    <td class="py-3">
                                        <div class="font-medium text-stone-800">{{ $order->customer->full_name }}</div>
                                    </td>
                                    <td class="py-3">
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
                                    <td class="py-3 text-stone-600">
                                        {{ $order->pickup_date->format('M d') }} {{ \Carbon\Carbon::parse($order->pickup_time)->format('h:i A') }}
                                    </td>
                                    <td class="py-3 font-semibold text-stone-900">
                                        ₱{{ number_format($order->total_amount, 2) }}
                                    </td>
                                    <td class="py-3">
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
                                    <td class="py-3 text-right">
                                        <a href="{{ route('orders.show', $order) }}" 
                                           class="px-2.5 py-1 bg-rose-50 hover:bg-rose-100 text-rose-900 rounded font-medium text-xs">
                                            Review
                                        </a>
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
