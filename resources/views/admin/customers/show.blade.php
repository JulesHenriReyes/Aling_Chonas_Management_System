@extends('layouts.admin')

@section('title', 'Customer: ' . $customer->full_name)

@section('content')
<div class="space-y-6">
    <div class="page-heading">
        <a href="{{ route('customers.index') }}" class="text-cocoa-500 hover:text-cocoa-600 font-medium text-sm transition">
            <x-icon name="arrow-left" class="mr-1" /> Back to Customers
        </a>
        @can('manage-customers')
<a href="{{ route('customers.edit', $customer) }}" class="bg-white border border-cocoa-100 text-cocoa-500 hover:bg-cream-100 font-medium text-sm px-4 py-2 rounded-lg transition">
            Edit Details
        </a>
@endcan
    </div>

    <!-- Info Card -->
    <div class="bg-white rounded-xl border border-cocoa-100 p-6">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-cocoa-600 mt-1">{{ $customer->full_name }}</h1>
                <p class="text-sm font-mono text-cocoa-500 font-medium mt-1">Phone: {{ $customer->phone_number }}</p>
            </div>
            <div class="text-left sm:text-right text-sm text-cocoa-500">
                <div>Client Since: <strong class="text-cocoa-600">{{ $customer->created_at->format('M d, Y') }}</strong></div>
                <div>Total Lifetime Orders: <strong class="text-cocoa-600">{{ $customer->orders->count() }}</strong></div>
            </div>
        </div>
    </div>

    <!-- Orders History -->
    <div class="space-y-4">
        <div class="flex items-center justify-between">
            <h2 class="text-sm font-semibold text-cocoa-600">Order history ({{ $customer->orders->count() }})</h2>
            @if ($customer->orders->count() > 10)
                <a href="{{ route('orders.index', ['search' => $customer->phone_number]) }}" class="text-xs text-cocoa-500 hover:text-cocoa-700 underline font-medium">View all {{ $customer->orders->count() }} orders →</a>
            @endif
        </div>

        @if ($customer->orders->isEmpty())
            <p class="text-sm text-cocoa-400 py-6 text-center">No orders placed by this customer yet.</p>
        @else
            <div class="table-scroll" role="region" aria-label="Scrollable data table" tabindex="0">
                <table class="w-full text-left">
                    <thead class="bg-cream-100 text-cocoa-400 text-xs  font-semibold">
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
                    <tbody class="divide-y divide-cocoa-100/60">
                        @foreach ($customer->orders->take(10) as $order)
                            <tr class="text-sm text-cocoa-500 hover:bg-cream-50 transition">
                                <td class="p-3 font-mono font-semibold text-cocoa-600">
                                    <a href="{{ route('orders.show', $order) }}" class="hover:underline">
                                        {{ $order->order_number }}
                                    </a>
                                </td>
                                <td class="p-3">{{ $order->created_at->format('M d, Y') }}</td>
                                <td class="p-3 font-medium text-cocoa-600">
                                    {{ $order->pickup_date->format('M d, Y') }} {{ \Carbon\Carbon::parse($order->pickup_time)->format('h:i A') }}
                                </td>
                                <td class="p-3 font-semibold text-cocoa-600">₱{{ number_format($order->total_amount, 2) }}</td>
                                <td class="p-3 font-semibold text-xs">
                                    @if ($order->status === 'cancelled')
                                        @if ($order->amount_paid > 0)
                                            <span class="inline-flex items-center text-xs font-semibold px-2 py-0.5 rounded text-amber-800 bg-amber-50 border border-amber-200">
                                                ₱{{ number_format($order->amount_paid, 2) }} paid
                                            </span>
                                        @else
                                            <span class="text-xs text-cocoa-400 font-medium">—</span>
                                        @endif
                                    @else
                                        <x-status :value="$order->payment_status" />
                                    @endif
                                </td>
                                <td class="p-3 font-semibold text-xs">
                                    <x-status :value="$order->status" />
                                </td>
                                <td class="p-3 text-right">
                                    <a href="{{ route('orders.show', $order) }}" class="bg-white border border-cocoa-100 text-cocoa-500 hover:bg-cream-100 font-medium text-xs px-2.5 py-1.5 rounded-lg transition inline-block">
                                        View
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if ($customer->orders->count() > 10)
                <div class="p-3 text-center bg-cream-50 rounded-lg border border-cocoa-100 text-xs text-cocoa-500">
                    Showing 10 most recent orders. <a href="{{ route('orders.index', ['search' => $customer->phone_number]) }}" class="font-semibold text-cocoa-700 underline">View all {{ $customer->orders->count() }} orders in order management →</a>
                </div>
            @endif
        @endif
    </div>
</div>
@endsection
