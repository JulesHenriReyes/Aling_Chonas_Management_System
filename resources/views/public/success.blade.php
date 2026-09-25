@extends('public.layout')

@section('title', 'Order Request Received - Aling Chona Cakes and Cupcakes')

@section('content')
<div class="max-w-2xl mx-auto">
    {{-- Success Card --}}
    <div class="bg-white rounded-xl border border-cocoa-100 overflow-hidden">
        {{-- Header --}}
        <div class="bg-cream-100 border-b border-cocoa-100 p-6 sm:p-8 text-center">
            <div class="w-12 h-12 bg-emerald-100 text-emerald-800 rounded-full flex items-center justify-center mx-auto mb-3 text-xl font-bold">
                <x-icon name="check" />
            </div>
            <h1 class="text-2xl font-bold text-cocoa-600">Request received: {{ $orderData['order_number'] }}</h1>
            <p class="text-cocoa-400 text-sm mt-1">
                Thank you, <strong class="text-cocoa-600">{{ $orderData['customer_name'] }}</strong>. Your request has been recorded.
            </p>

        </div>

        {{-- Body Details --}}
        <div class="p-6 sm:p-8 space-y-6">
            <div>
                <x-status :value="$orderData['status']" :label="'Pending review'" />
                <p class="text-sm text-cocoa-500 mt-2">Your request has been submitted, but is not yet confirmed. Our team will contact you after reviewing your design.</p>
            </div>

            {{-- Next Steps & 50% Down Payment Instructions --}}
            <div class="space-y-3">
                <h2 class="font-bold text-cocoa-600 text-sm">
                    What happens next
                </h2>
                <ol class="list-decimal list-inside text-sm text-cocoa-500 space-y-2 leading-relaxed">
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

            {{-- Scheduled Pickup Details --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 border-t border-b border-cocoa-100 py-4">
                <div>
                    <span class="text-xs text-cocoa-400 font-semibold">Scheduled Pickup Date</span>
                    <p class="text-sm font-bold text-cocoa-600 mt-0.5">{{ $orderData['pickup_date'] }}</p>
                </div>
                <div>
                    <span class="text-xs text-cocoa-400 font-semibold">Pickup Time</span>
                    <p class="text-sm font-bold text-cocoa-600 mt-0.5">{{ $orderData['pickup_time'] }}</p>
                </div>
            </div>

            {{-- Submitted Items Summary --}}
            <div>
                <h2 class="font-bold text-cocoa-600 text-sm mb-3">Submitted Custom Items</h2>
                <div class="divide-y divide-cocoa-100 text-sm">
                    @foreach ($orderData['items'] as $item)
                        <div class="p-4 bg-white space-y-1.5">
                            <div class="page-heading">
                                <div class="font-bold text-cocoa-600">
                                    {{ $item['quantity'] }}x {{ $item['product_name'] }}
                                </div>
                                <div class="text-cocoa-500 text-xs font-semibold">
                                    ₱{{ number_format($item['subtotal'], 2) }}
                                </div>
                            </div>
                            @if (!empty($item['layers']))
                                <div class="text-xs text-cocoa-400">
                                    <strong class="text-cocoa-500">Layers:</strong> {{ $item['layers'] }}
                                </div>
                            @endif
                            @if (!empty($item['themes']))
                                <div class="text-xs text-cocoa-400">
                                    <strong class="text-cocoa-500">Theme:</strong> {{ $item['themes'] }}
                                </div>
                            @endif
                            @if (!empty($item['special_request']))
                                <div class="text-xs text-cocoa-400">
                                    <strong class="text-cocoa-500">Special Requests:</strong> {{ $item['special_request'] }}
                                </div>
                            @endif
                        </div>
                    @endforeach
                    <div class="p-4 bg-cream-50 flex flex-wrap items-start justify-between gap-2 font-bold text-cocoa-600">
                        <span>Provisional Base Total:</span>
                        <span class="text-base text-cocoa-600">₱{{ number_format($orderData['provisional_total'], 2) }}</span>
                    </div>
                </div>
                <p class="text-xs text-cocoa-400 mt-1 italic text-right">
                    *Subject to staff review and final confirmation based on custom design.
                </p>
            </div>

            {{-- Return Actions --}}
            <div class="pt-2 text-center">
                <a href="{{ route('public.order.index') }}" 
                   class="inline-block px-6 py-2.5 bg-cocoa-600 hover:bg-cocoa-700 text-white text-xs font-semibold rounded-lg transition ">
                    Place Another Order
                </a>
            </div>
        </div>
    </div>
</div>
@endsection
