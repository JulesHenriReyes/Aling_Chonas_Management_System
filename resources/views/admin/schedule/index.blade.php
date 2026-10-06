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

    @forelse ($orders as $date => $dailyOrders)
        <section class="bg-white border border-cocoa-100 rounded-xl overflow-hidden">
            <div class="p-4 border-b border-cocoa-100/60 bg-cream-50 flex flex-wrap gap-2 items-center justify-between">
                <div class="flex flex-wrap items-center gap-2">
                    <x-icon path="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" class="w-5 h-5 text-cocoa-400" />
                    <h2 class="text-sm font-semibold text-cocoa-600">{{ \Carbon\Carbon::parse($date)->format('l, F d, Y') }}</h2>
                </div>
                <span class="text-sm text-cocoa-500">
                    {{ $dailyOrders->count() }} pickup(s)
                </span>
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
                            <div class="text-sm text-cocoa-500">
                                <span class="font-medium">Packages:</span> {{ $order->orderDetails->map(fn($d) => $d->quantity . 'x ' . ($d->product_name_snapshot ?? $d->product->product_name))->join(', ') }}
                                @foreach ($order->orderDetails as $detail)
                                    @foreach ($detail->addOns as $extra)<span class="block">Paid extra: {{ $extra->quantity }} × {{ $extra->name_snapshot }}</span>@endforeach
                                @endforeach
                            </div>
                            @if ($order->notes_text)
                                <div class="text-sm text-cocoa-500">
                                    "{{ $order->notes_text }}"
                                </div>
                            @endif
                        </div>
                        <div class="flex items-center sm:items-end flex-row sm:flex-col justify-between sm:justify-center gap-2 text-right">
                            <div class="flex items-center gap-2">
                                <x-status :value="$order->status" :label="$order->workflowLabel()" class="order-workflow-status" />
                                <x-status :value="$order->payment_status" />
                                <a href="{{ route('orders.show', $order) }}" class="ml-2 bg-white border border-cocoa-100 text-cocoa-500 hover:bg-cream-100 font-medium text-xs px-3 py-1 rounded-lg transition">
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
