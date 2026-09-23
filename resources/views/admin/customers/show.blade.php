@extends('layouts.admin')

@section('title', 'Customer: ' . $customer->full_name)

@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <a href="{{ route('customers.index') }}" class="text-xs text-rose-900 font-bold hover:underline">
            ← Back to Customers
        </a>
        <a href="{{ route('customers.edit', $customer) }}" class="px-3 py-1.5 bg-stone-800 text-white rounded-lg text-xs font-bold shadow hover:bg-stone-700">
            Edit Details
        </a>
    </div>

    <!-- Info Card -->
    <div class="bg-white rounded-2xl border border-stone-200 p-6 shadow-sm">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <span class="text-xs text-stone-400 uppercase font-bold tracking-wider">Customer Profile</span>
                <h1 class="text-2xl font-black text-stone-900 mt-1">{{ $customer->full_name }}</h1>
                <p class="text-sm font-mono text-rose-950 font-bold mt-1">📞 {{ $customer->phone_number }}</p>
            </div>
            <div class="text-left sm:text-right text-xs text-stone-500">
                <div>Client Since: <strong>{{ $customer->created_at->format('M d, Y') }}</strong></div>
                <div>Total Lifetime Orders: <strong>{{ $customer->orders->count() }}</strong></div>
            </div>
        </div>
    </div>

    <!-- Orders History -->
    <div class="bg-white rounded-2xl border border-stone-200 p-6 shadow-sm space-y-4">
        <h2 class="text-sm font-bold text-stone-900">Order History for {{ $customer->first_name }}</h2>

        @if ($customer->orders->isEmpty())
            <p class="text-xs text-stone-400 py-6 text-center">No orders placed by this customer yet.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-stone-50 border-b text-stone-500 uppercase text-[10px]">
                        <tr>
                            <th class="p-3">Order #</th>
                            <th class="p-3">Order Date</th>
                            <th class="p-3">Pickup Schedule</th>
                            <th class="p-3">Total Amount</th>
                            <th class="p-3">Payment</th>
                            <th class="p-3">Status</th>
                            <th class="p-3 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-stone-100">
                        @foreach ($customer->orders as $order)
                            <tr>
                                <td class="p-3 font-mono font-bold text-rose-950">
                                    <a href="{{ route('orders.show', $order) }}" class="hover:underline">
                                        {{ $order->order_number }}
                                    </a>
                                </td>
                                <td class="p-3 text-stone-600">{{ $order->created_at->format('M d, Y') }}</td>
                                <td class="p-3 font-medium text-stone-800">
                                    {{ $order->pickup_date->format('M d, Y') }} {{ \Carbon\Carbon::parse($order->pickup_time)->format('h:i A') }}
                                </td>
                                <td class="p-3 font-bold text-stone-900">₱{{ number_format($order->total_amount, 2) }}</td>
                                <td class="p-3 uppercase font-bold text-[10px]">
                                    <span class="px-2 py-0.5 rounded
                                        {{ $order->payment_status === 'fully_paid' ? 'bg-emerald-100 text-emerald-800' : ($order->payment_status === 'partially_paid' ? 'bg-blue-100 text-blue-800' : 'bg-red-100 text-red-800') }}">
                                        {{ str_replace('_', ' ', $order->payment_status) }}
                                    </span>
                                </td>
                                <td class="p-3 uppercase font-bold text-[10px]">
                                    <span class="px-2 py-0.5 rounded
                                        @if($order->status === 'completed') bg-emerald-100 text-emerald-800
                                        @elseif($order->status === 'cancelled') bg-red-100 text-red-800
                                        @elseif($order->status === 'confirmed') bg-blue-100 text-blue-800
                                        @else bg-amber-100 text-amber-800 @endif">
                                        {{ str_replace('_', ' ', $order->status) }}
                                    </span>
                                </td>
                                <td class="p-3 text-right">
                                    <a href="{{ route('orders.show', $order) }}" class="px-2.5 py-1 bg-rose-50 text-rose-900 rounded font-bold hover:bg-rose-100">
                                        View
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
@endsection
