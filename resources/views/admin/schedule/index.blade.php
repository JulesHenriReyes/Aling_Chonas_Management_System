@extends('layouts.admin')

@section('title', 'Pickup Schedule')

@section('content')
<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-xl font-bold text-cocoa-600">Pickup Schedule</h1>
            <p class="text-sm text-cocoa-400 mt-1">Active customer pickups organized chronologically by date and time.</p>
        </div>
        <form id="schedule-filter-form" method="GET" action="{{ route('schedule.index') }}" class="flex items-center w-full sm:w-auto">
            <div class="relative w-full sm:w-52 shrink-0">
                <input aria-label="Pickup date" type="date" name="pickup_date" value="{{ request('pickup_date') }}" class="w-full h-10 text-sm rounded-lg border border-cocoa-100 bg-white px-3 pr-9 text-cocoa-600 focus:border-cocoa-300 focus:ring-1 focus:ring-cocoa-300 transition">
                <button type="button" data-clear-date class="absolute right-2.5 top-1/2 -translate-y-1/2 text-cocoa-400 hover:text-cocoa-700 p-1 rounded transition {{ request('pickup_date') ? '' : 'hidden' }}" title="Clear date filter" aria-label="Clear date filter">
                    <x-icon name="close" class="w-3.5 h-3.5" />
                </button>
            </div>
        </form>
    </div>

    <div id="schedule-container" class="space-y-6 transition-opacity duration-150">
        {{-- Weekly Capacity Snapshot Bar --}}
    @php
        $overviewOrders = (!empty($capacityOrders) && $capacityOrders->isNotEmpty()) ? $capacityOrders : $orders;
    @endphp
    {{-- Weekly Capacity Snapshot Bar --}}
    @if ($overviewOrders->isNotEmpty())
        <div class="bg-white border border-cocoa-100 rounded-xl p-4 space-y-3">
            <div class="flex items-center justify-between flex-wrap gap-2">
                <span class="text-xs font-bold text-cocoa-700 uppercase tracking-wider flex items-center gap-1.5">
                    <x-icon path="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" class="w-4 h-4 text-cocoa-500" />
                    Pickup Schedule Capacity Overview
                </span>
                <div class="flex items-center gap-3 text-xs">
                    <span class="text-cocoa-400 font-medium">{{ $overviewOrders->flatten()->count() }} active orders across {{ $overviewOrders->count() }} day(s)</span>
                    @if(request('pickup_date'))
                        <a href="{{ route('schedule.index') }}" data-filter-reset class="text-cocoa-600 hover:text-cocoa-800 underline font-semibold inline-flex items-center gap-1">
                            Show all dates
                        </a>
                    @endif
                </div>
            </div>
            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-7 gap-2.5">
                @foreach ($overviewOrders->take(7) as $dateStr => $dayOrders)
                    @php
                        $count = $dayOrders->count();
                        $isSelected = request('pickup_date') === $dateStr;
                        $statusClass = $count >= 5 ? 'bg-amber-50 border-amber-200 text-amber-800' : ($count >= 3 ? 'bg-cocoa-50 border-cocoa-200 text-cocoa-700' : 'bg-cream-50 border-cocoa-100 text-cocoa-600');
                        $capacityLabel = $count >= 5 ? 'High volume' : ($count >= 3 ? 'Moderate' : 'Normal');
                    @endphp
                    <a href="{{ $isSelected ? route('schedule.index') : route('schedule.index', ['pickup_date' => $dateStr]) }}" 
                       data-schedule-filter
                       @if($isSelected) aria-current="true" @endif
                       class="p-2.5 rounded-lg border {{ $statusClass }} {{ $isSelected ? 'ring-2 ring-cocoa-700 bg-white shadow-sm font-semibold' : (request('pickup_date') ? 'hover:border-cocoa-300 opacity-75 hover:opacity-100' : 'hover:border-cocoa-300') }} transition text-center block cursor-pointer">
                        <span class="block text-[11px] font-bold">{{ \Carbon\Carbon::parse($dateStr)->format('D, M j') }}</span>
                        <span class="block text-base font-bold my-0.5">{{ $count }}</span>
                        <span class="block text-[10px] font-medium opacity-80">{{ $capacityLabel }}</span>
                    </a>
                @endforeach
            </div>
        </div>
    @endif

    @forelse ($orders as $date => $dailyOrders)
        <section class="bg-white border border-cocoa-100 rounded-xl overflow-hidden shadow-xs">
            <div class="px-3.5 py-2 sm:px-4 sm:py-2.5 border-b border-cocoa-100/60 bg-cream-50/70 flex flex-wrap gap-2 items-center justify-between">
                <div class="flex items-center gap-2">
                    <x-icon path="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" class="w-4 h-4 text-cocoa-400" />
                    <h2 class="text-xs sm:text-sm font-bold text-cocoa-800">{{ \Carbon\Carbon::parse($date)->format('l, F d, Y') }}</h2>
                </div>
                <div class="flex items-center gap-2 text-xs">
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full font-semibold bg-cream-100 text-cocoa-700 border border-cocoa-200/80 text-[11px]">
                        {{ $dailyOrders->count() }} scheduled
                    </span>
                    <span class="text-cocoa-400 font-medium text-[11px]">
                        {{ $dailyOrders->count() }} pickup(s)
                    </span>
                </div>
            </div>
            <div class="divide-y divide-cocoa-100/60">
                @foreach ($dailyOrders as $order)
                    <div class="px-3.5 py-2.5 sm:px-4 sm:py-2.5 flex flex-col sm:flex-row sm:items-center justify-between gap-2.5 hover:bg-cream-50/50 transition">
                        <div class="space-y-1 min-w-0 flex-1">
                            {{-- Line 1: Customer Name, Pickup Time Badge, Order #, Phone --}}
                            <div class="flex items-center flex-wrap gap-x-2.5 gap-y-1">
                                <a href="{{ route('customers.show', $order->customer) }}" class="text-sm font-bold text-cocoa-900 hover:text-cocoa-700 hover:underline">
                                    {{ $order->customer->full_name }}
                                </a>
                                <span class="inline-flex items-center gap-1 text-[11px] font-semibold text-cocoa-700 bg-cream-100/90 px-1.5 py-0.5 rounded border border-cocoa-200/60">
                                    <x-icon name="clock" class="w-3 h-3 text-cocoa-500" />
                                    {{ \Carbon\Carbon::parse($order->pickup_time)->format('h:i A') }}
                                </span>
                                <a href="{{ route('orders.show', $order) }}" class="text-xs font-mono text-cocoa-500 hover:text-cocoa-800 hover:underline">
                                    {{ $order->order_number }}
                                </a>
                                <span class="text-cocoa-300 text-xs select-none">·</span>
                                <a href="tel:{{ $order->customer->phone_number }}" class="text-xs text-cocoa-500 hover:underline">
                                    {{ $order->customer->phone_number }}
                                </a>
                            </div>

                            {{-- Line 2: Items Inline --}}
                            <div class="text-xs text-cocoa-600 flex flex-wrap items-center gap-x-1.5 gap-y-0.5">
                                <span class="text-[11px] font-semibold uppercase tracking-wider text-cocoa-400">Items:</span>
                                @foreach ($order->orderDetails as $detail)
                                    <span class="font-medium text-cocoa-800">
                                        {{ $detail->quantity }}× {{ $detail->product_name_snapshot ?? $detail->product->product_name }}@if($detail->addOns->isNotEmpty())<span class="text-cocoa-500 font-normal"> (+{{ $detail->addOns->map(fn($extra) => $extra->quantity.'× '.$extra->name_snapshot)->join(', ') }})</span>@endif
                                    </span>
                                    @if (!$loop->last)
                                        <span class="text-cocoa-300">·</span>
                                    @endif
                                @endforeach
                            </div>

                            {{-- Line 3: Order Notes (if present) --}}
                            @if ($order->notes_text)
                                <div class="text-[11px] text-cocoa-500 italic truncate max-w-2xl" title="{{ $order->notes_text }}">
                                    “{{ $order->notes_text }}”
                                </div>
                            @endif
                        </div>

                        {{-- Right Column: Status Badges and Action Buttons --}}
                        <div class="flex items-center gap-2 shrink-0 flex-wrap justify-end">
                            <x-status :value="$order->status" :label="$order->workflowLabel()" class="order-workflow-status" />
                            <x-status :value="$order->payment_status" />
                            @if (Gate::allows('update-order-status') && $order->status === 'preparing')
                                <form action="{{ route('orders.updateStatus', $order) }}" method="POST" class="inline">
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="status" value="ready_for_pickup">
                                    <button type="submit" class="bg-amber-600 hover:bg-amber-700 text-white font-semibold text-xs px-2.5 py-1 rounded-lg transition shadow-2xs">
                                        Mark Ready
                                    </button>
                                </form>
                            @endif
                            <a href="{{ route('orders.show', $order) }}" class="bg-white border border-cocoa-100 text-cocoa-600 hover:bg-cream-100 font-semibold text-xs px-2.5 py-1 rounded-lg transition">
                                View
                            </a>
                        </div>
                    </div>
                @endforeach
            </div>
        </section>
    @empty
        <div class="bg-white border border-cocoa-100 rounded-xl p-12 text-center text-sm text-cocoa-400 space-y-2">
            <p>No active pickups scheduled.</p>
            @if(request('pickup_date'))
                <p>
                    <a href="{{ route('schedule.index') }}" data-filter-reset class="inline-flex items-center gap-1 text-sm font-medium text-cocoa-600 hover:text-cocoa-800 underline">
                        <x-icon name="close" class="w-3.5 h-3.5" />
                        <span>Show all dates</span>
                    </a>
                </p>
            @endif
        </div>
    @endforelse
    </div>
</div>
@endsection
