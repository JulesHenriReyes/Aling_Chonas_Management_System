@extends('layouts.admin')

@section('title', 'Pickup Schedule')

@section('content')
<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-stone-900">Pickup Schedule</h1>
            <p class="text-xs text-stone-500 mt-1">Active customer pickups organized chronologically by date and time.</p>
        </div>
        <form method="GET" action="{{ route('schedule.index') }}" class="flex items-center gap-2">
            <input type="date" name="pickup_date" value="{{ request('pickup_date') }}" class="text-xs rounded-xl border-stone-300 focus:border-rose-700 focus:ring-rose-700 shadow-sm">
            <button type="submit" class="px-4 py-2 bg-stone-800 hover:bg-stone-700 text-white text-xs font-bold rounded-xl shadow transition">
                Filter Date
            </button>
            @if(request('pickup_date'))
                <a href="{{ route('schedule.index') }}" class="px-3 py-2 text-stone-500 hover:text-stone-800 text-xs">
                    Reset
                </a>
            @endif
        </form>
    </div>

    @forelse ($orders as $date => $dailyOrders)
        <section class="bg-white rounded-2xl border border-stone-200 shadow-sm overflow-hidden">
            <div class="p-4 border-b bg-stone-50 flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <span class="text-base">📅</span>
                    <h2 class="font-bold text-sm text-stone-900">{{ \Carbon\Carbon::parse($date)->format('l, F d, Y') }}</h2>
                </div>
                <span class="text-xs font-bold px-2.5 py-0.5 rounded-full bg-rose-100 text-rose-900">
                    {{ $dailyOrders->count() }} pickup(s)
                </span>
            </div>
            <div class="divide-y divide-stone-100">
                @foreach ($dailyOrders as $order)
                    <div class="p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-4 hover:bg-stone-50/60 transition">
                        <div class="space-y-1">
                            <div class="flex items-center gap-2 flex-wrap">
                                <a href="{{ route('orders.show', $order) }}" class="font-mono font-bold text-rose-950 hover:underline">
                                    {{ $order->order_number }}
                                </a>
                                <span class="text-stone-300">•</span>
                                <a href="{{ route('customers.show', $order->customer) }}" class="font-bold text-xs text-stone-800 hover:text-rose-900 hover:underline">
                                    {{ $order->customer->full_name }}
                                </a>
                                <span class="text-[11px] text-stone-500 font-mono">({{ $order->customer->phone_number }})</span>
                            </div>
                            <div class="text-xs text-stone-600">
                                <strong>Items:</strong> {{ $order->orderDetails->map(fn($d) => $d->quantity . 'x ' . $d->product->product_name)->join(', ') }}
                            </div>
                            @if ($order->notes_text)
                                <div class="text-[11px] text-stone-500 italic">
                                    "{{ $order->notes_text }}"
                                </div>
                            @endif
                        </div>
                        <div class="flex items-center sm:items-end flex-row sm:flex-col justify-between sm:justify-center gap-2 text-right">
                            <div class="font-mono text-sm font-black text-stone-900">
                                {{ \Carbon\Carbon::parse($order->pickup_time)->format('h:i A') }}
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider
                                    @if($order->status === 'ready_for_pickup') bg-teal-100 text-teal-800
                                    @elseif($order->status === 'preparing') bg-indigo-100 text-indigo-800
                                    @elseif($order->status === 'confirmed') bg-blue-100 text-blue-800
                                    @else bg-amber-100 text-amber-800 @endif">
                                    {{ str_replace('_', ' ', $order->status) }}
                                </span>
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider
                                    @if($order->payment_status === 'fully_paid') bg-emerald-100 text-emerald-800
                                    @elseif($order->payment_status === 'partially_paid') bg-blue-100 text-blue-800
                                    @else bg-red-100 text-red-800 @endif">
                                    {{ str_replace('_', ' ', $order->payment_status) }}
                                </span>
                                <a href="{{ route('orders.show', $order) }}" class="ml-2 px-2.5 py-1 bg-rose-50 hover:bg-rose-100 text-rose-900 font-bold rounded-lg text-xs">
                                    View
                                </a>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </section>
    @empty
        <div class="bg-white border border-stone-200 rounded-2xl p-12 text-center text-xs text-stone-400">
            No active pickups scheduled.
        </div>
    @endforelse
</div>
@endsection
