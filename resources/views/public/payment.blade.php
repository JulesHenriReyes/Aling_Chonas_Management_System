@extends('public.layout')
@section('title', 'Your order · Aling Chona')
@section('content')
@php($proof = $order->paymentProofs->first())
<div class="max-w-5xl mx-auto space-y-6">
    <header class="space-y-2">
        <h1 class="font-bold text-cocoa-600">Your order {{ $order->order_number }}</h1>
        <p>Pickup: {{ $order->pickup_date->format('F j, Y') }} at {{ \Carbon\Carbon::parse($order->pickup_time)->format('g:i A') }} ({{ config('bakery.pickup_timezone') }}).</p>
        <p class="text-sm">This is the bakery’s deadline to have your order ready. Final payment and collection complete the order.</p>
    </header>
    @if (session('success'))<p role="status" class="p-4 bg-emerald-50 text-emerald-800 rounded-lg">{{ session('success') }}</p>@endif
    <section class="section-form space-y-3" aria-labelledby="order-progress">
        <h2 id="order-progress" class="font-semibold text-cocoa-600">Order progress</h2>
        <x-status :value="$order->status" :label="$order->status === 'pending' ? 'Pending deposit verification' : null" />
        @if ($order->status === 'pending')
            @if (!$proof)
                <p><strong>Awaiting receipt.</strong> {{ $settings?->isConfigured() ? 'Pay the exact 50% deposit using the business GCash QR below, then send your receipt.' : 'Your order is saved. The bakery needs to set up its GCash payment details before you send money.' }}</p>
            @elseif ($proof->status === 'awaiting_verification')
                <p><strong>Awaiting verification.</strong> Your receipt is with our team. It does not count as a payment until verified. Please do not pay again.</p>
            @elseif ($proof->status === 'rejected')
                <div class="receipt-rejected" role="alert"><strong>Receipt rejected</strong><p>{{ $proof->rejection_reason }}</p><a href="#replacement-receipt" class="inline-block underline font-semibold">Upload a replacement receipt</a></div>
                <p>If the transfer succeeded, do not pay again. Contact the bakery if you need help resolving the rejection.</p>
            @endif
        @elseif ($order->status === 'confirmed')
            <p>Your deposit is verified and your order is confirmed. We’ll mark it Preparing when baking begins.</p>
        @elseif ($order->status === 'preparing')
            <p>We’re preparing your cakes. Check this link for Ready for pickup.</p>
        @elseif ($order->status === 'ready_for_pickup')
            <p>Your order is ready for pickup. The remaining balance is due when you collect it.</p>
        @elseif ($order->status === 'completed')
            <p>Your order has been collected and paid in full. Thank you for ordering with Aling Chona.</p>
        @elseif ($order->cancellation_kind === 'bakery_failure')
            <p>The bakery could not fulfill your order by the agreed pickup deadline. {{ $order->cancellation_reason }}</p>
            @unless ($order->refund)<p>No verified payment was recorded, so no refund is due.</p>@endunless
        @else
            <p>Your order was cancelled. The existing customer-cancellation policy retains the deposit.</p>
        @endif
    </section>
    @if ($order->paymentProofs->count() > 1)
        <details class="section-form"><summary class="cursor-pointer font-semibold">Earlier receipt history ({{ $order->paymentProofs->count() - 1 }})</summary>
            <ul class="mt-3 space-y-2 text-sm">@foreach ($order->paymentProofs->skip(1) as $earlier)<li>Submitted {{ $earlier->created_at->format('M j, Y g:i A') }} · {{ str_replace('_', ' ', $earlier->status) }} · Reference {{ $earlier->reference_number }}@if($earlier->rejection_reason) · {{ $earlier->rejection_reason }}@endif</li>@endforeach</ul>
        </details>
    @endif

    @if ($order->refund)
        <section class="section-form space-y-2" aria-labelledby="refund-status">
            <h2 id="refund-status" class="font-semibold text-cocoa-600">Full refund · {{ ucfirst($order->refund->status) }}</h2>
            <p class="text-xl font-bold">₱{{ number_format($order->refund->amount, 2) }}</p>
            <p>{{ $order->refund->reason }}</p>
            @if ($order->refund->status === 'pending')
                <p>The bakery will return all verified payments. GCash transfers are handled manually; the refund has not yet been marked sent.</p>
            @else
                <p>Transferred by {{ strtoupper($order->refund->method) }} on {{ $order->refund->completed_at->format('F j, Y g:i A') }}.</p>
                <p>Refund reference: {{ $order->refund->reference_number }}</p>
            @endif
        </section>
    @endif

    <div class="payment-grid {{ $order->status === 'pending' && (!$proof || $proof->status === 'rejected') ? '' : 'no-receipt-entry' }}">
        <section class="section-form space-y-3 payment-order" aria-labelledby="fixed-total">
            <h2 id="fixed-total" class="font-semibold text-cocoa-600">{{ $order->fixed_catalog_pricing ? 'Your fixed-price order' : 'Your saved order' }}</h2>
            @include('partials.order-item-summary')
            <dl class="space-y-3 border-t border-cocoa-100 pt-4">
                <div class="flex justify-between gap-4 font-bold text-lg"><dt>Total</dt><dd>₱{{ number_format($order->total_amount, 2) }}</dd></div>
                <div class="flex justify-between gap-4"><dt>Exact 50% deposit</dt><dd>₱{{ number_format($order->required_down_payment, 2) }}</dd></div>
                <div class="flex justify-between gap-4"><dt>Verified payments received</dt><dd>₱{{ number_format($order->amount_paid, 2) }}</dd></div>
                @if ($order->status !== 'cancelled')<div class="flex justify-between gap-4"><dt>Remaining balance</dt><dd>₱{{ number_format($order->remaining_balance, 2) }}</dd></div>@endif
                @if ($order->refund)<div class="flex justify-between gap-4"><dt>Refund {{ $order->refund->status }}</dt><dd>₱{{ number_format($order->refund->amount, 2) }}</dd></div>@endif
            </dl>
            <p class="text-sm">Themes and design requests do not change the saved price.</p>
        </section>
            @if ($order->status === 'pending' && (!$proof || $proof->status === 'rejected'))
                <section class="section-form space-y-4 payment-qr" aria-labelledby="gcash-payment">
                    <h2 id="gcash-payment" class="font-semibold text-cocoa-600">Pay your GCash deposit</h2>
                    @if ($settings?->isConfigured())
                        <p>Send exactly <strong>₱{{ number_format($order->required_down_payment, 2) }}</strong> using the business GCash QR.</p>
                        <img src="{{ asset('storage/'.$settings->qr_path) }}" width="280" height="280" alt="Business GCash payment QR" class="w-full max-w-[280px] h-auto mx-auto">
                        <a href="{{ route('public.order.qr', $order->private_token) }}" class="inline-block underline font-medium">Save QR image</a>
                    @else
                        <p class="text-amber-800">The bakery’s GCash QR is not configured yet. Your order is saved. Contact the bakery before sending money and return to this private link once payment details are available.</p>
                    @endif
                </section>
                <section id="replacement-receipt" class="section-form space-y-4 payment-receipt" aria-labelledby="receipt-submission">
                    <form action="{{ route('public.order.receipt', $order->private_token) }}" method="POST" enctype="multipart/form-data" class="space-y-4">
                        @csrf
                        <h2 id="receipt-submission" class="font-semibold text-cocoa-600">{{ $proof?->status === 'rejected' ? 'Upload a replacement receipt' : 'Send your payment receipt' }}</h2>
                        <p class="text-sm">Upload the successful transaction screenshot. Staff will check it against the business account.</p>
                        <div><label for="reference-number">GCash transaction reference</label><input id="reference-number" name="reference_number" value="{{ old('reference_number') }}" maxlength="100" required spellcheck="false" class="w-full"></div>
                        <div><label for="receipt">Payment screenshot</label><input id="receipt" name="receipt" type="file" accept="image/jpeg,image/png,image/webp" required aria-describedby="receipt-help">
                            <p id="receipt-help" class="text-sm mt-1">JPG, PNG or WebP, up to 5 MB. Receipts are stored privately for staff review.</p></div>
                        <button type="submit" class="bg-cocoa-600 text-white rounded-lg px-5 py-3 hover:bg-cocoa-700">{{ $proof?->status === 'rejected' ? 'Submit replacement receipt' : 'Submit receipt for verification' }}</button>
                    </form>
                </section>
            @endif
            <section class="section-form space-y-3 payment-link" x-data="{ copied: false, failed: false }" aria-labelledby="save-order">
                <h2 id="save-order" class="font-semibold text-cocoa-600">Keep your order link</h2>
                <p class="text-sm">Anyone with this link can view your order and send a receipt. Keep it private. Refreshing this page or returning from GCash will open the same order.</p>
                <label for="private-order-link" class="text-sm">Private order link</label>
                <input id="private-order-link" type="text" readonly value="{{ route('public.order.payment', $order->private_token) }}" class="w-full" x-ref="orderLink">
                <div class="flex flex-wrap gap-3">
                    <button type="button" class="px-4 py-2 border border-cocoa-200 rounded-lg" @click="navigator.clipboard ? navigator.clipboard.writeText($refs.orderLink.value).then(() => { copied = true; failed = false }).catch(() => { failed = true }) : failed = true">Copy order link</button>
                    <a href="{{ route('public.order.saveLink', $order->private_token) }}" class="px-4 py-2 underline">Save order link</a>
                </div>
                <p role="status" x-show="copied" x-cloak>Order link copied.</p>
                <p role="status" x-show="failed" x-cloak>Copy is unavailable here. Select the link above or use Save order link.</p>
            </section>
    </div>
</div>
@endsection
