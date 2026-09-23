@extends('public.layout')

@section('title', 'Order Request Received - Aling Chona Cakes and Cupcakes')

@section('content')
<div class="max-w-2xl mx-auto">
    <!-- Success Card -->
    <div class="bg-white rounded-2xl shadow-md border border-stone-200 overflow-hidden">
        <!-- Header -->
        <div class="bg-emerald-700 text-white p-6 sm:p-8 text-center">
            <div class="w-16 h-16 bg-white/20 rounded-full flex items-center justify-center mx-auto mb-4 text-3xl">
                ✓
            </div>
            <h2 class="text-2xl font-extrabold text-white">Order Request Submitted!</h2>
            <p class="text-emerald-100 text-sm mt-1">
                Thank you, <strong>{{ $orderData['customer_name'] }}</strong>. Your request has been recorded.
            </p>
            <div class="mt-4 inline-block bg-white text-emerald-900 font-mono font-bold text-sm px-4 py-1.5 rounded-full shadow-inner">
                Order Reference: {{ $orderData['order_number'] }}
            </div>
        </div>

        <!-- Body Details -->
        <div class="p-6 sm:p-8 space-y-6">
            <!-- Current Status Alert -->
            <div class="bg-amber-50 border border-amber-200 rounded-xl p-4 text-amber-900 text-sm">
                <div class="flex items-center gap-2 font-bold text-amber-950">
                    <span class="w-3 h-3 rounded-full bg-amber-500 animate-pulse"></span>
                    Current Order Status: <span class="uppercase tracking-wide">{{ ucfirst($orderData['status']) }}</span>
                </div>
                <p class="text-xs text-amber-800 mt-1 leading-relaxed">
                    Our team is currently reviewing your per-product customization specifications and reference images.
                </p>
            </div>

            <!-- Next Steps & 50% Down Payment Instructions -->
            <div class="border border-stone-200 rounded-xl p-5 bg-stone-50/50 space-y-3">
                <h3 class="font-bold text-stone-900 text-sm flex items-center gap-2 text-rose-950">
                    <span>💵</span> Next Steps & 50% Down Payment Policy:
                </h3>
                <ol class="list-decimal list-inside text-xs text-stone-700 space-y-2 leading-relaxed">
                    <li>
                        <strong>Staff Price Review:</strong> Displayed prices are initial estimates. Staff will review your custom layers, theme, and decoration requests to confirm the final order price.
                    </li>
                    <li>
                        <strong>50% Down Payment:</strong> Once the price is finalized, an <strong>exact 50% down payment</strong> is required to confirm your order and schedule preparation.
                    </li>
                    <li>
                        <strong>Payment Methods:</strong> We accept <strong>Cash</strong> or <strong>GCash</strong>. If paying via GCash, please provide the transaction reference number to our staff.
                    </li>
                    <li>
                        <strong>Remaining Balance:</strong> The remaining 50% balance will be settled upon pickup of your cake.
                    </li>
                </ol>
            </div>

            <!-- Scheduled Pickup Details -->
            <div class="grid grid-cols-2 gap-4 border-t border-b border-stone-200 py-4">
                <div>
                    <span class="text-xs text-stone-500 uppercase font-semibold">Scheduled Pickup Date</span>
                    <p class="text-sm font-bold text-stone-900 mt-0.5">{{ $orderData['pickup_date'] }}</p>
                </div>
                <div>
                    <span class="text-xs text-stone-500 uppercase font-semibold">Pickup Time</span>
                    <p class="text-sm font-bold text-stone-900 mt-0.5">{{ $orderData['pickup_time'] }}</p>
                </div>
            </div>

            <!-- Submitted Items Summary -->
            <div>
                <h4 class="font-bold text-stone-900 text-sm uppercase tracking-wide mb-3">Submitted Custom Items:</h4>
                <div class="divide-y divide-stone-200 border border-stone-200 rounded-xl overflow-hidden text-sm">
                    @foreach ($orderData['items'] as $item)
                        <div class="p-4 bg-white space-y-1.5">
                            <div class="flex items-center justify-between">
                                <div class="font-bold text-stone-900">
                                    {{ $item['quantity'] }}x {{ $item['product_name'] }}
                                </div>
                                <div class="text-stone-700 text-xs font-semibold">
                                    ₱{{ number_format($item['subtotal'], 2) }}
                                </div>
                            </div>
                            @if (!empty($item['layers']))
                                <div class="text-xs text-stone-500">
                                    <strong>Layers:</strong> {{ $item['layers'] }}
                                </div>
                            @endif
                            @if (!empty($item['themes']))
                                <div class="text-xs text-stone-500">
                                    <strong>Theme:</strong> {{ $item['themes'] }}
                                </div>
                            @endif
                            @if (!empty($item['special_request']))
                                <div class="text-xs text-stone-500">
                                    <strong>Special Requests:</strong> {{ $item['special_request'] }}
                                </div>
                            @endif
                        </div>
                    @endforeach
                    <div class="p-4 bg-stone-50 flex items-center justify-between font-bold text-stone-900">
                        <span>Provisional Base Total:</span>
                        <span class="text-base text-rose-950">₱{{ number_format($orderData['provisional_total'], 2) }}</span>
                    </div>
                </div>
                <p class="text-[11px] text-stone-400 mt-1 italic text-right">
                    *Subject to staff review and final confirmation based on custom design.
                </p>
            </div>

            <!-- Return Actions -->
            <div class="pt-2 text-center">
                <a href="{{ route('public.order.index') }}" 
                   class="inline-block px-6 py-2.5 bg-rose-900 hover:bg-rose-800 text-white text-xs font-bold rounded-xl transition shadow">
                    Place Another Order
                </a>
            </div>
        </div>
    </div>
</div>
@endsection
