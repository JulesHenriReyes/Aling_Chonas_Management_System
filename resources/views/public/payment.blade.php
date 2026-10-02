@extends('public.layout')
@section('title', 'Your order · Aling Chona')
@section('content')
@php($proof = $order->paymentProofs->first())
<div class="max-w-5xl mx-auto space-y-6">
    {{-- Progress Navigation --}}
    <nav aria-label="Progress" class="mb-5">
        <div class="flex items-center text-xs tracking-wide">
            <span class="text-cocoa-700">Packages</span>
            <span class="text-cocoa-300 mx-2">/</span>
            <span class="text-cocoa-700">Contact & pickup</span>
            <span class="text-cocoa-300 mx-2">/</span>
            <span class="text-cocoa-700 font-semibold" aria-current="step">Payment</span>
        </div>
    </nav>

    <header class="space-y-1">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="font-bold text-cocoa-700 text-2xl sm:text-3xl">Your order {{ $order->order_number }}</h1>
            <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-cream-100 text-cocoa-700 border border-cocoa-200">
                <svg class="w-3.5 h-3.5 text-cocoa-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                <span>Pickup: {{ $order->pickup_date->format('F j, Y') }} at {{ \Carbon\Carbon::parse($order->pickup_time)->format('g:i A') }} ({{ config('bakery.pickup_timezone') }})</span>
            </div>
        </div>
        <p class="text-xs text-cocoa-400">This is the bakery’s deadline to have your order ready. Final payment and collection complete the order.</p>
    </header>

    @if (session('success'))
        <p role="status" class="p-4 bg-emerald-50 text-emerald-800 rounded-xl border border-emerald-200 text-sm font-medium">{{ session('success') }}</p>
    @endif

    {{-- Order Progress Card --}}
    <section class="checkout-card p-5 space-y-3" aria-labelledby="order-progress">
        <div class="flex items-center justify-between border-b border-cocoa-100 pb-3">
            <h2 id="order-progress" class="font-bold text-cocoa-700 text-sm">Order progress</h2>
            <x-status :value="$order->status" :label="$order->status === 'pending' ? 'Pending deposit verification' : null" />
        </div>
        <div class="text-sm text-cocoa-600">
            @if ($order->status === 'pending')
                @if (!$proof)
                    <p><strong>Awaiting receipt.</strong> {{ $settings?->isConfigured() ? 'Pay the exact 50% deposit using the business GCash QR below, then send your receipt.' : 'Your order is saved. The bakery needs to set up its GCash payment details before you send money.' }}</p>
                @elseif ($proof->status === 'awaiting_verification')
                    <p><strong>Awaiting verification.</strong> Your receipt is with our team. It does not count as a payment until verified. Please do not pay again.</p>
                @elseif ($proof->status === 'rejected')
                    <div class="receipt-rejected" role="alert">
                        <strong>Receipt rejected</strong>
                        <p>{{ $proof->rejection_reason }}</p>
                        <a href="#replacement-receipt" class="inline-block underline font-semibold mt-1">Upload a replacement receipt</a>
                    </div>
                    <p class="mt-2 text-xs text-cocoa-500">If the transfer succeeded, do not pay again. Contact the bakery if you need help resolving the rejection.</p>
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
        </div>
    </section>

    @if ($order->paymentProofs->count() > 1)
        <details class="checkout-card p-5"><summary class="cursor-pointer font-semibold text-cocoa-700 text-sm">Earlier receipt history ({{ $order->paymentProofs->count() - 1 }})</summary>
            <ul class="mt-3 space-y-2 text-xs text-cocoa-600">@foreach ($order->paymentProofs->skip(1) as $earlier)<li>Submitted {{ $earlier->created_at->format('M j, Y g:i A') }} · {{ str_replace('_', ' ', $earlier->status) }} · Reference {{ $earlier->reference_number }}@if($earlier->rejection_reason) · {{ $earlier->rejection_reason }}@endif</li>@endforeach</ul>
        </details>
    @endif

    @if ($order->refund)
        <section class="checkout-card p-5 space-y-2" aria-labelledby="refund-status">
            <h2 id="refund-status" class="font-bold text-cocoa-700 text-sm">Full refund · {{ ucfirst($order->refund->status) }}</h2>
            <p class="text-xl font-extrabold text-cocoa-700">₱{{ number_format($order->refund->amount, 2) }}</p>
            <p class="text-xs text-cocoa-600">{{ $order->refund->reason }}</p>
            @if ($order->refund->status === 'pending')
                <p class="text-xs text-cocoa-500">The bakery will return all verified payments. GCash transfers are handled manually; the refund has not yet been marked sent.</p>
            @else
                <p class="text-xs text-cocoa-500">Transferred by {{ strtoupper($order->refund->method) }} on {{ $order->refund->completed_at->format('F j, Y g:i A') }}.</p>
                <p class="text-xs text-cocoa-500">Refund reference: {{ $order->refund->reference_number }}</p>
            @endif
        </section>
    @endif

    @php($needsPayment = $order->status === 'pending' && (!$proof || $proof->status === 'rejected'))

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
        {{-- Left Column: Order Items & Pricing Summary + Bookmark Link --}}
        <div class="lg:col-span-7 space-y-6">
            {{-- Order Items & Total Summary --}}
            <section class="checkout-card p-6 space-y-4" aria-labelledby="fixed-total">
                <div class="flex items-center justify-between border-b border-cocoa-100 pb-3">
                    <h2 id="fixed-total" class="font-bold text-cocoa-700 text-base">{{ $order->fixed_catalog_pricing ? 'Your fixed-price order' : 'Your saved order' }}</h2>
                    <span class="text-xs font-semibold px-2.5 py-0.5 rounded-full bg-cream-100 text-cocoa-600 border border-cocoa-100">
                        {{ $order->orderDetails->sum('quantity') }} {{ $order->orderDetails->sum('quantity') === 1 ? 'package' : 'packages' }}
                    </span>
                </div>

                @include('partials.order-item-summary')

                <div class="border-t border-cocoa-100 pt-4 space-y-3">
                    <div class="flex justify-between items-center text-sm">
                        <span class="text-cocoa-500 font-medium">Order Total</span>
                        <span class="font-bold text-cocoa-700 text-lg">₱{{ number_format($order->total_amount, 2) }}</span>
                    </div>

                    {{-- 50% Deposit & Payment Details Box --}}
                    <div class="p-3.5 rounded-xl bg-cocoa-50/80 border border-cocoa-200/80 space-y-2">
                        <div class="flex justify-between items-baseline">
                            <span class="text-xs font-bold text-cocoa-700 uppercase tracking-wide">Exact 50% Deposit</span>
                            <strong class="text-base font-extrabold text-cocoa-700">₱{{ number_format($order->required_down_payment, 2) }}</strong>
                        </div>
                        <div class="flex justify-between text-xs text-cocoa-500 pt-1.5 border-t border-cocoa-200/60">
                            <span>Verified Payments Received:</span>
                            <span class="font-semibold text-cocoa-700">₱{{ number_format($order->amount_paid, 2) }}</span>
                        </div>
                        @if ($order->status !== 'cancelled')
                            <div class="flex justify-between text-xs text-cocoa-500">
                                <span>Remaining Balance (at Pickup):</span>
                                <span class="font-semibold text-cocoa-700">₱{{ number_format($order->remaining_balance, 2) }}</span>
                            </div>
                        @endif
                    </div>

                    @if ($order->refund)
                        <div class="flex justify-between gap-4 text-xs font-semibold text-amber-800 bg-amber-50 p-2.5 rounded-lg border border-amber-200">
                            <span>Refund {{ $order->refund->status }}:</span>
                            <span>₱{{ number_format($order->refund->amount, 2) }}</span>
                        </div>
                    @endif

                    <p class="text-xs text-cocoa-400 pt-2 border-t border-cocoa-100">
                        Customer cancellations retain the deposit. If the bakery misses the pickup deadline, all verified payments are refundable.
                    </p>
                </div>
            </section>

            @if ($needsPayment)
                {{-- Order Link Card (placed under summary when payment is active) --}}
                <section class="checkout-card p-5 space-y-3" x-data="{ copied: false, failed: false }" aria-labelledby="save-order">
                    <div class="flex items-center gap-2.5 pb-2.5 border-b border-cocoa-100">
                        <div class="w-7 h-7 rounded-lg bg-cocoa-50 text-cocoa-600 flex items-center justify-center shrink-0 border border-cocoa-100">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" /></svg>
                        </div>
                        <h2 id="save-order" class="font-bold text-cocoa-700 text-sm">Keep your order link</h2>
                    </div>
                    <p class="text-xs text-cocoa-400">Save or bookmark this private link to check your order progress at any time.</p>
                    <div>
                        <label for="private-order-link" class="block text-xs font-semibold text-cocoa-700 uppercase tracking-wider mb-1.5">Private order link</label>
                        <input id="private-order-link" type="text" readonly value="{{ route('public.order.payment', $order->private_token) }}" class="form-input-custom font-mono text-xs bg-cream-50" x-ref="orderLink">
                    </div>
                    <div class="flex flex-wrap items-center gap-2.5 pt-1">
                        <button type="button" class="px-3.5 py-1.5 text-xs font-semibold bg-white border border-cocoa-200 hover:bg-cream-100 text-cocoa-700 rounded-lg transition" @click="navigator.clipboard ? navigator.clipboard.writeText($refs.orderLink.value).then(() => { copied = true; failed = false }).catch(() => { failed = true }) : failed = true">Copy order link</button>
                        <a href="{{ route('public.order.saveLink', $order->private_token) }}" class="px-3.5 py-1.5 text-xs font-semibold text-cocoa-600 hover:text-cocoa-800 underline transition inline-flex items-center">Save order link</a>
                    </div>
                    <p role="status" x-show="copied" x-cloak class="text-xs text-emerald-700 font-medium">Order link copied to clipboard.</p>
                    <p role="status" x-show="failed" x-cloak class="text-xs text-amber-700">Copy is unavailable. Select the link above or use Save order link.</p>
                </section>
            @endif
        </div>

        {{-- Right Column: Payment Actions (or Order Link when no payment needed) --}}
        <div class="lg:col-span-5 space-y-6">
            @if ($needsPayment)
                {{-- Unified 2-Step Deposit Payment Card --}}
                <div class="checkout-card p-6 space-y-5">
                    <div class="flex items-center justify-between pb-3 border-b border-cocoa-100">
                        <div class="flex items-center gap-2.5">
                            <div class="w-8 h-8 rounded-lg bg-cocoa-50 text-cocoa-700 flex items-center justify-center shrink-0 border border-cocoa-200">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" /></svg>
                            </div>
                            <h2 class="font-bold text-cocoa-700 text-base">Pay your GCash deposit</h2>
                        </div>
                        <span class="text-xs font-extrabold text-cocoa-700 px-2.5 py-1 rounded-full bg-cream-100 border border-cocoa-200">
                            ₱{{ number_format($order->required_down_payment, 2) }}
                        </span>
                    </div>

                    {{-- Step 1: Scan & Transfer --}}
                    <section class="space-y-3" aria-labelledby="gcash-payment">
                        <div class="flex items-center gap-2">
                            <span class="w-5 h-5 rounded-full bg-cocoa-700 text-white text-[11px] font-bold flex items-center justify-center">1</span>
                            <h3 id="gcash-payment" class="text-xs font-bold text-cocoa-700 uppercase tracking-wider">Step 1: Scan & Send via GCash</h3>
                        </div>

                        @if ($settings?->isConfigured())
                            <p class="text-xs text-cocoa-600">
                                Send exactly <strong class="font-bold text-cocoa-700">₱{{ number_format($order->required_down_payment, 2) }}</strong> using the business GCash QR.
                            </p>
                            <div class="flex flex-col items-center gap-2.5 p-3 rounded-xl bg-cream-50/60 border border-cocoa-100">
                                <img src="{{ asset('storage/'.$settings->qr_path) }}" width="240" height="240" alt="Business GCash payment QR" class="w-full max-w-[220px] h-auto rounded-lg border border-cocoa-100 bg-white p-2 shadow-xs">
                                <a href="{{ route('public.order.qr', $order->private_token) }}" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-white hover:bg-cream-100 text-cocoa-700 text-xs font-semibold border border-cocoa-200 transition shadow-2xs">
                                    <svg class="w-3.5 h-3.5 text-cocoa-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" /></svg>
                                    <span>Save QR image</span>
                                </a>
                            </div>
                        @else
                            <p class="text-amber-800 text-xs bg-amber-50 p-3 rounded-xl border border-amber-200">The bakery’s GCash QR is not configured yet. Your order is saved. Contact the bakery before sending money and return to this private link once payment details are available.</p>
                        @endif
                    </section>

                    {{-- Visual Divider --}}
                    <div class="relative py-1">
                        <div class="absolute inset-0 flex items-center"><div class="w-full border-t border-cocoa-100"></div></div>
                        <div class="relative flex justify-center text-xs uppercase"><span class="bg-white px-2.5 text-cocoa-400 font-semibold tracking-wider">Then</span></div>
                    </div>

                    {{-- Step 2: Submit Payment Receipt --}}
                    <section id="replacement-receipt" class="space-y-3" aria-labelledby="receipt-submission">
                        <div class="flex items-center gap-2">
                            <span class="w-5 h-5 rounded-full bg-cocoa-700 text-white text-[11px] font-bold flex items-center justify-center">2</span>
                            <h3 id="receipt-submission" class="text-xs font-bold text-cocoa-700 uppercase tracking-wider">
                                {{ $proof?->status === 'rejected' ? 'Step 2: Upload replacement receipt' : 'Step 2: Send your payment receipt' }}
                            </h3>
                        </div>

                        <form action="{{ route('public.order.receipt', $order->private_token) }}" method="POST" enctype="multipart/form-data" class="space-y-3.5">
                            @csrf
                            <p class="text-xs text-cocoa-500">Upload the successful transaction screenshot. Staff will check it against the business account.</p>

                            <div>
                                <label for="reference-number" class="block text-xs font-semibold text-cocoa-700 uppercase tracking-wider mb-1.5">GCash transaction reference <span class="text-red-500">*</span></label>
                                <input id="reference-number" name="reference_number" value="{{ old('reference_number') }}" maxlength="100" required spellcheck="false" class="form-input-custom" placeholder="Reference number">
                            </div>

                            <div>
                                <label for="receipt" class="block text-xs font-semibold text-cocoa-700 uppercase tracking-wider mb-1.5">Payment screenshot <span class="text-red-500">*</span></label>
                                <input id="receipt" name="receipt" type="file" accept="image/jpeg,image/png,image/webp" required aria-describedby="receipt-help" class="form-input-custom text-xs">
                                <p id="receipt-help" class="text-[11px] text-cocoa-400 mt-1">JPG, PNG or WebP, up to 5 MB. Receipts are stored privately for staff review.</p>
                            </div>

                            <button type="submit" class="w-full py-2.5 px-4 bg-cocoa-600 hover:bg-cocoa-700 active:scale-[0.99] text-white font-semibold rounded-xl shadow-xs transition flex items-center justify-center gap-2 text-sm">
                                <span>{{ $proof?->status === 'rejected' ? 'Submit replacement receipt' : 'Submit receipt for verification' }}</span>
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" /></svg>
                            </button>
                        </form>
                    </section>
                </div>
            @else
                {{-- If no payment is needed, Order Link card sits cleanly in the right column --}}
                <section class="checkout-card p-6 space-y-3" x-data="{ copied: false, failed: false }" aria-labelledby="save-order">
                    <div class="flex items-center gap-2.5 pb-3 border-b border-cocoa-100">
                        <div class="w-8 h-8 rounded-lg bg-cocoa-50 text-cocoa-600 flex items-center justify-center shrink-0 border border-cocoa-100">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" /></svg>
                        </div>
                        <h2 id="save-order" class="font-bold text-cocoa-700 text-base">Keep your order link</h2>
                    </div>
                    <p class="text-xs text-cocoa-400">Anyone with this link can view your order and send a receipt. Keep it private. Returning to this link will reopen the order status.</p>
                    <div>
                        <label for="private-order-link" class="block text-xs font-semibold text-cocoa-700 uppercase tracking-wider mb-1.5">Private order link</label>
                        <input id="private-order-link" type="text" readonly value="{{ route('public.order.payment', $order->private_token) }}" class="form-input-custom font-mono text-xs bg-cream-50" x-ref="orderLink">
                    </div>
                    <div class="flex flex-wrap gap-2.5 pt-1">
                        <button type="button" class="px-3.5 py-2 text-xs font-semibold bg-white border border-cocoa-200 hover:bg-cream-100 text-cocoa-700 rounded-lg transition" @click="navigator.clipboard ? navigator.clipboard.writeText($refs.orderLink.value).then(() => { copied = true; failed = false }).catch(() => { failed = true }) : failed = true">Copy order link</button>
                        <a href="{{ route('public.order.saveLink', $order->private_token) }}" class="px-3.5 py-2 text-xs font-semibold text-cocoa-600 hover:text-cocoa-800 underline transition inline-flex items-center">Save order link</a>
                    </div>
                    <p role="status" x-show="copied" x-cloak class="text-xs text-emerald-700 font-medium">Order link copied to clipboard.</p>
                    <p role="status" x-show="failed" x-cloak class="text-xs text-amber-700">Copy is unavailable. Select the link above or use Save order link.</p>
                </section>
            @endif
        </div>
    </div>
</div>
@endsection
