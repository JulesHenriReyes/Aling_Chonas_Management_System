@extends('layouts.admin')

@section('title', 'Pickup Schedule')

@section('content')
<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-xl font-bold text-cocoa-600">Pickup Schedule</h1>
            <p class="text-sm text-cocoa-400 mt-1">Active customer pickups organized chronologically by date and time.</p>
        </div>
        <form method="GET" action="{{ route('schedule.index') }}" class="flex items-center gap-2 w-full sm:w-auto">
            <div class="relative flex-1 sm:w-48">
                <input aria-label="Pickup date" type="date" name="pickup_date" value="{{ request('pickup_date') }}" class="w-full h-10 text-sm rounded-lg border border-cocoa-100 bg-white px-3 text-cocoa-600 focus:border-cocoa-300 focus:ring-1 focus:ring-cocoa-300">
            </div>
            <button type="submit" class="h-10 px-4 bg-white border border-cocoa-100 text-cocoa-500 hover:bg-cream-100 hover:text-cocoa-700 font-medium text-sm rounded-lg transition inline-flex items-center justify-center whitespace-nowrap shrink-0">
                Filter Date
            </button>
            @if(request('pickup_date'))
                <a href="{{ route('schedule.index') }}" class="h-10 px-2 text-cocoa-500 hover:text-cocoa-600 font-medium text-sm inline-flex items-center shrink-0">
                    Reset
                </a>
            @endif
        </form>
    </div>

    {{-- Weekly Capacity Snapshot Bar --}}
    @if ($orders->isNotEmpty())
        <div class="bg-white border border-cocoa-100 rounded-xl p-4 space-y-3">
            <div class="flex items-center justify-between flex-wrap gap-2">
                <span class="text-xs font-bold text-cocoa-700 uppercase tracking-wider flex items-center gap-1.5">
                    <x-icon path="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" class="w-4 h-4 text-cocoa-500" />
                    Pickup Schedule Capacity Overview
                </span>
                <div class="flex items-center gap-3 text-xs">
                    <span class="text-cocoa-400 font-medium">{{ $orders->flatten()->count() }} active orders across {{ $orders->count() }} day(s)</span>
                    @if(request('pickup_date'))
                        <a href="{{ route('schedule.index') }}" class="text-cocoa-600 hover:text-cocoa-800 underline font-semibold inline-flex items-center gap-1">
                            Show all dates
                        </a>
                    @endif
                </div>
            </div>
            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-7 gap-2.5">
                @foreach ($orders->take(7) as $dateStr => $dayOrders)
                    @php
                        $count = $dayOrders->count();
                        $isSelected = request('pickup_date') === $dateStr;
                        $statusClass = $count >= 5 ? 'bg-amber-50 border-amber-200 text-amber-800' : ($count >= 3 ? 'bg-cocoa-50 border-cocoa-200 text-cocoa-700' : 'bg-cream-50 border-cocoa-100 text-cocoa-600');
                        $capacityLabel = $count >= 5 ? 'High volume' : ($count >= 3 ? 'Moderate' : 'Normal');
                    @endphp
                    <a href="{{ route('schedule.index', ['pickup_date' => $dateStr]) }}" class="p-2.5 rounded-lg border {{ $statusClass }} {{ $isSelected ? 'ring-2 ring-cocoa-600 shadow-xs' : 'hover:border-cocoa-300' }} transition text-center block">
                        <span class="block text-[11px] font-bold">{{ \Carbon\Carbon::parse($dateStr)->format('D, M j') }}</span>
                        <span class="block text-base font-bold my-0.5">{{ $count }}</span>
                        <span class="block text-[10px] font-medium opacity-80">{{ $capacityLabel }}</span>
                    </a>
                @endforeach
            </div>
        </div>
    @endif

    @forelse ($orders as $date => $dailyOrders)
        <section class="bg-white border border-cocoa-100 rounded-xl overflow-hidden">
            <div class="p-4 border-b border-cocoa-100/60 bg-cream-50 flex flex-wrap gap-2 items-center justify-between">
                <div class="flex flex-wrap items-center gap-2">
                    <x-icon path="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" class="w-5 h-5 text-cocoa-400" />
                    <h2 class="text-sm font-semibold text-cocoa-600">{{ \Carbon\Carbon::parse($date)->format('l, F d, Y') }}</h2>
                </div>
                <div class="flex items-center gap-2">
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-cream-100 text-cocoa-700 border border-cocoa-200">
                        {{ $dailyOrders->count() }} scheduled
                    </span>
                    <span class="text-sm text-cocoa-500">
                        {{ $dailyOrders->count() }} pickup(s)
                    </span>
                </div>
            </div>
            <div class="divide-y divide-cocoa-100/60">
                @foreach ($dailyOrders as $order)
                    <div class="p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-4 hover:bg-cream-50 transition">
                        <div class="space-y-2 min-w-0">
                            <a href="{{ route('customers.show', $order->customer) }}" class="block text-lg font-bold text-cocoa-600 hover:underline">{{ $order->customer->full_name }}</a>
                            <div class="flex items-center gap-3 flex-wrap text-sm">
                                <a href="{{ route('orders.show', $order) }}" class="text-cocoa-500 hover:underline">{{ $order->order_number }}</a>
                                <span class="font-semibold text-cocoa-600">{{ \Carbon\Carbon::parse($order->pickup_time)->format('h:i A') }}</span>
                                <a href="tel:{{ $order->customer->phone_number }}" class="text-cocoa-500 hover:underline">{{ $order->customer->phone_number }}</a>
                            </div>
                            <div class="text-sm text-cocoa-600 space-y-1">
                                <span class="font-semibold text-xs uppercase tracking-wider text-cocoa-400 block">Packages:</span>
                                @foreach ($order->orderDetails as $detail)
                                    <div>
                                        <span class="font-medium text-cocoa-700">{{ $detail->quantity }}× {{ $detail->product_name_snapshot ?? $detail->product->product_name }}</span>
                                        @if($detail->addOns->isNotEmpty())
                                            <ul class="text-xs text-cocoa-500 pl-3 border-l-2 border-cocoa-100 space-y-0.5 mt-0.5">
                                                @foreach ($detail->addOns as $extra)
                                                    <li>+ Paid extra: {{ $extra->quantity }} × {{ $extra->name_snapshot }}</li>
                                                @endforeach
                                            </ul>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                            @if ($order->notes_text)
                                <div class="text-sm text-cocoa-500">
                                    "{{ $order->notes_text }}"
                                </div>
                            @endif
                        </div>
                        <div class="flex items-center sm:items-end flex-row sm:flex-col justify-between sm:justify-center gap-2 text-right">
                            <div class="flex items-center gap-2 flex-wrap">
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
                                <a href="{{ route('orders.show', $order) }}" class="bg-white border border-cocoa-100 text-cocoa-500 hover:bg-cream-100 font-medium text-xs px-3 py-1 rounded-lg transition">
                                    View
                                </a>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </section>
    @empty
        <div class="bg-white border border-cocoa-100 rounded-xl p-12 text-center text-sm text-cocoa-400">
            No active pickups scheduled.
        </div>
    @endforelse
</div>
@endsection
