@extends('public.layout')
@section('title', 'Your order · Aling Chona')
@section('content')
@php($proof = $order->paymentProofs->first())
<div class="public-order-status max-w-5xl mx-auto space-y-4">
    {{-- Progress Navigation --}}
    <nav class="store-progress mb-3 sm:mb-5" aria-label="Order progress">
        <a href="{{ route('public.order.index') }}">1. Choose package</a>
        <span>2. Customize</span>
        <span>3. Contact & pickup</span>
        <span aria-current="step">4. Staff review & status</span>
    </nav>

    <header class="space-y-2">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
            <div>
                <h1 class="font-bold text-cocoa-700 text-lg sm:text-2xl leading-tight flex flex-wrap items-center gap-1.5">
                    <span>Your order</span>
                    <span class="font-mono text-xs sm:text-sm font-semibold text-cocoa-600 bg-cream-100 px-2 py-0.5 rounded border border-cocoa-200/80 inline-block align-middle">{{ $order->order_number }}</span>
                </h1>
            </div>
            <div class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] sm:text-xs font-semibold bg-cream-100 text-cocoa-700 border border-cocoa-200 self-start sm:self-auto">
                <svg class="w-3.5 h-3.5 text-cocoa-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                <span>Pickup: {{ $order->pickup_date->format('M j, Y') }} at {{ \Carbon\Carbon::parse($order->pickup_time)->format('g:i A') }}</span>
            </div>
        </div>
    </header>

    {{-- Automatic Order Link Clipboard Notification --}}
    @include('partials.order-private-link')

    @if (session('success'))
        <div role="status" class="px-3.5 py-2 bg-emerald-50 text-emerald-800 rounded-xl border border-emerald-200 text-xs font-medium flex items-center justify-between gap-2" x-data="{ show: true }" x-show="show">
            <span>{{ session('success') }}</span>
            <button type="button" @click="show = false" class="text-emerald-600 hover:text-emerald-900 shrink-0 p-0.5 rounded hover:bg-emerald-100 transition cursor-pointer" aria-label="Dismiss message">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
    @endif

    {{-- Order Progress Card --}}
    <section class="checkout-card p-4 sm:p-5 space-y-3" aria-labelledby="order-progress">
        <div class="flex items-center justify-between border-b border-cocoa-100 pb-3">
            <h2 id="order-progress" class="font-bold text-cocoa-700 text-sm">Order progress</h2>
            <x-status :value="$order->status" :label="$order->workflowLabel()" class="order-workflow-status" />
        </div>
        <div class="text-sm text-cocoa-600">
            @if ($order->needsStaffReview() || $order->status === 'pending')
                <p><strong>Awaiting staff confirmation.</strong> The bakery will check your design, quantity, pickup schedule and capacity before opening payment. Keep this private link to check the decision.</p>
                @if ($proof || $order->amount_paid > 0)
                    <p class="mt-2 text-amber-800">A payment or receipt was reported under the previous flow. The Owner needs to check it. Please do not transfer again; contact the bakery for reconciliation.</p>
                @endif
            @elseif ($order->status === 'confirmed' && $order->amount_paid === 0.0)
                @if ($proof?->status === 'awaiting_verification')
                    <p><strong>Awaiting verification.</strong> Your receipt is with our team. It does not count as a payment until verified. Please do not pay again.</p>
                @elseif ($proof?->status === 'rejected')
                    <div class="receipt-rejected" role="alert">
                        <strong>Receipt rejected</strong>
                        <p>{{ $proof->rejection_reason }}</p>
                        <a href="#replacement-receipt" class="inline-block underline font-semibold mt-1">Upload a replacement receipt</a>
                    </div>
                    <p class="mt-2 text-xs text-cocoa-500">If the transfer succeeded, do not pay again. Contact the bakery if you need help resolving the rejection.</p>
                @else
                    <p><strong>Confirmed — awaiting deposit.</strong> The bakery has confirmed it can fulfil your request. {{ $settings?->isConfigured() ? 'Pay the exact 50% deposit below to secure your booking. Preparation starts after the deposit is verified.' : 'Payment details are not configured yet. Contact the bakery before sending money.' }}</p>
                @endif
            @elseif ($order->status === 'confirmed')
                <p>Your deposit is verified and your booking is secured. We’ll mark it Preparing when baking begins.</p>
            @elseif ($order->status === 'preparing')
                <p>We’re preparing your cakes. Check this link for Ready for pickup.</p>
            @elseif ($order->status === 'ready_for_pickup')
                <p>Your order is ready for pickup. {{ $order->remaining_balance > 0 ? 'The remaining balance is due when you collect it.' : 'Your order is paid in full; no further payment is due.' }}</p>
            @elseif ($order->status === 'completed')
                <p>Your order has been collected and paid in full. No further payment is due. Thank you for ordering with Aling Chona.</p>
            @elseif ($order->cancellation_kind === 'staff_rejected')
                <p><strong>Request declined.</strong> The bakery cannot accept this request: {{ $order->cancellation_reason }}</p>
                <p class="mt-2">No verified payment is recorded. @if($proof)The Owner checked the reported transfer before declining; contact the bakery if your account shows a successful transfer.@else No payment was requested for this request.@endif You may submit a revised request.</p>
            @elseif ($order->cancellation_kind === 'bakery_failure')
                <p>The bakery could not fulfil your order. {{ $order->cancellation_reason }}</p>
            @else
                <p>Your order was cancelled. @if($order->hasVerifiedPayment())The deposit was retained under the policy in effect when this order was cancelled.@else No verified payment is recorded.@endif</p>
            @endif
        </div>
    </section>

    @if ($order->paymentProofs->count() > 1)
        <details class="checkout-card p-5"><summary class="cursor-pointer font-semibold text-cocoa-700 text-sm">Earlier receipt history ({{ $order->paymentProofs->count() - 1 }})</summary>
            <ul class="mt-3 space-y-2 text-xs text-cocoa-600">@foreach ($order->paymentProofs->skip(1) as $earlier)<li>Submitted {{ $earlier->created_at->format('M j, Y g:i A') }} · {{ str_replace('_', ' ', $earlier->status) }} · Reference {{ $earlier->reference_number }}@if($earlier->rejection_reason) · {{ $earlier->rejection_reason }}@endif</li>@endforeach</ul>
        </details>
    @endif

    @php($needsPayment = $order->canSubmitReceipt() && ($settings?->isConfigured() || $proof?->status === 'rejected' || filled(old('reference_number'))))

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
        {{-- Left Column: Order Items & Pricing Summary + Bookmark Link --}}
        <div class="lg:col-span-7 space-y-4">
            {{-- Order Items & Total Summary --}}
            <section class="checkout-card p-4 sm:p-6 space-y-3.5 sm:space-y-4" aria-labelledby="fixed-total">
                <div class="flex items-center justify-between border-b border-cocoa-100 pb-3">
                    <h2 id="fixed-total" class="font-bold text-cocoa-700 text-sm sm:text-base">{{ $order->fixed_catalog_pricing ? 'Your fixed-price order' : 'Your saved order' }}</h2>
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
                    <div class="p-3 sm:p-3.5 rounded-xl bg-cocoa-50/80 border border-cocoa-200/80 space-y-2">
                        <div class="flex justify-between items-baseline">
                            <span class="text-xs font-bold text-cocoa-700 uppercase tracking-wide inline-flex items-center gap-1">
                                Exact 50% Deposit
                                <x-tooltip text="The 50% deposit locks in your baking schedule. Only verified deposits secure your booking; remaining balance is paid at pickup." />
                            </span>
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


                    <p class="text-xs text-cocoa-400 pt-2 border-t border-cocoa-100">
                        The exact 50% deposit secures your booking after verification. If you cancel an eligible order after paying only this deposit, the deposit is retained. For orders going ahead, the remaining balance is collected at actual pickup after the order is ready.
                    </p>
                </div>
            </section>

        </div>

        {{-- Right Column: Payment Actions (or Order Link when no payment needed) --}}
        <div class="lg:col-span-5 sidebar-column space-y-4 lg:sticky lg:top-20">
            @if ($needsPayment)
                {{-- Unified 2-Step Deposit Payment Card --}}
                <div class="checkout-card p-4 sm:p-6 space-y-4 sm:space-y-5">
                    <div class="flex items-center justify-between pb-3 border-b border-cocoa-100">
                        <div class="flex items-center gap-2.5">
                            <div class="w-8 h-8 rounded-lg bg-cocoa-50 text-cocoa-700 flex items-center justify-center shrink-0 border border-cocoa-200">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.75" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" /></svg>
                            </div>
                            <h2 class="font-bold text-cocoa-700 text-sm sm:text-base">{{ $proof?->status === 'rejected' ? 'Correct your payment receipt' : ($settings?->isConfigured() ? 'Pay your GCash deposit' : 'Submit your reported transfer receipt') }}</h2>
                        </div>
                        <span class="text-xs font-extrabold text-cocoa-700 px-2.5 py-1 rounded-full bg-cream-100 border border-cocoa-200">
                            ₱{{ number_format($order->required_down_payment, 2) }}
                        </span>
                    </div>

                    {{-- Step 1: Scan & Transfer --}}
                    <section class="space-y-3" aria-labelledby="gcash-payment">
                        @if ($settings?->isConfigured())
                        <div class="flex items-center gap-2">
                            <span class="w-5 h-5 rounded-full bg-cocoa-700 text-white text-[11px] font-bold flex items-center justify-center">1</span>
                            <h3 id="gcash-payment" class="text-xs font-bold text-cocoa-700 uppercase tracking-wider">Step 1: Scan & Send via GCash</h3>
                        </div>
                        @else
                            <h3 id="gcash-payment" class="text-xs font-bold text-cocoa-700">Payment details unavailable</h3>
                        @endif

                        @if ($settings?->isConfigured())
                            <p class="text-xs text-cocoa-600">
                                @if ($proof?->status === 'rejected')If you have already transferred money, do not pay again. Correct your receipt below or contact the bakery. Only send money if you have not already made a transfer.@else Send exactly <strong class="font-bold text-cocoa-700">₱{{ number_format($order->required_down_payment, 2) }}</strong> using the business GCash QR.@endif
                            </p>
                            <div class="flex flex-col items-center gap-2.5 p-3 rounded-xl bg-cream-50/60 border border-cocoa-100">
                                <img src="{{ route('public.order.qr', ['token' => $order->private_token, 'inline' => 1]) }}" width="240" height="240" alt="Business GCash payment QR" class="w-full max-w-[180px] sm:max-w-[220px] h-auto rounded-lg border border-cocoa-100 bg-white p-2 shadow-xs">
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

                        <form action="{{ route('public.order.receipt', $order->private_token) }}" method="POST" enctype="multipart/form-data" class="space-y-3.5" x-data="{ previewUrl: null }">
                            @csrf
                            <p class="text-xs text-cocoa-500">Upload the successful transaction screenshot. Staff will check it against the business account.</p>

                            <div>
                                <label for="reference-number" class="block text-xs font-semibold text-cocoa-700 uppercase tracking-wider mb-1.5">GCash transaction reference <span class="text-red-500">*</span></label>
                                <input id="reference-number" name="reference_number" value="{{ old('reference_number') }}" maxlength="100" required spellcheck="false" class="form-input-custom" placeholder="Reference number">
                            </div>

                            <div>
                                <label for="receipt" class="block text-xs font-semibold text-cocoa-700 uppercase tracking-wider mb-1.5">Payment screenshot <span class="text-red-500">*</span></label>
                                <input id="receipt" name="receipt" type="file" accept="image/jpeg,image/png,image/webp" required aria-describedby="receipt-help" class="form-input-custom text-xs" @change="previewUrl = $event.target.files[0] ? URL.createObjectURL($event.target.files[0]) : null">
                                <p id="receipt-help" class="text-[11px] text-cocoa-400 mt-1">JPG, PNG or WebP, up to 5 MB. Receipts are stored privately for staff review.</p>
                                <template x-if="previewUrl">
                                    <div class="mt-2 p-2 bg-cream-50 rounded-lg border border-cocoa-100 flex items-center gap-3">
                                        <img :src="previewUrl" alt="Receipt preview" class="w-16 h-16 object-cover rounded border border-cocoa-200">
                                        <span class="text-xs text-cocoa-600 font-medium">Preview of selected receipt</span>
                                    </div>
                                </template>
                            </div>

                            <button type="submit" class="w-full py-2.5 px-4 bg-cocoa-600 hover:bg-cocoa-700 active:scale-[0.99] text-white font-semibold rounded-xl shadow-xs transition flex items-center justify-center gap-2 text-sm">
                                <span>{{ $proof?->status === 'rejected' ? 'Submit replacement receipt' : 'Submit receipt for verification' }}</span>
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" /></svg>
                            </button>
                        </form>
                    </section>
                </div>
            @else
                {{-- Order Status Summary Card --}}
                <div class="checkout-card p-4 sm:p-5 space-y-3 bg-cream-50/70 border border-cocoa-100">
                    <div class="flex items-center gap-2.5 pb-2.5 border-b border-cocoa-100">
                        <div class="w-7 h-7 rounded-lg bg-emerald-100 text-emerald-800 flex items-center justify-center shrink-0 font-bold text-xs">
                            ✓
                        </div>
                        <h2 class="font-bold text-cocoa-700 text-sm">Order Status Summary</h2>
                    </div>
                    <div class="space-y-1.5 text-xs text-cocoa-600">
                        <p><span class="font-semibold text-cocoa-700">Status:</span> {{ ucfirst(str_replace('_', ' ', $order->status)) }}</p>
                        <p><span class="font-semibold text-cocoa-700">Scheduled Pickup:</span> {{ $order->pickup_date->format('M j, Y') }} at {{ \Carbon\Carbon::parse($order->pickup_time)->format('g:i A') }}</p>
                        @if($order->status === 'completed')
                            <p class="text-emerald-700 font-medium pt-1">This order has been completed and picked up. Thank you for choosing Aling Chona!</p>
                        @elseif($order->status === 'ready_for_pickup')
                            <p class="text-emerald-700 font-medium pt-1">Your cake is ready! Please proceed to the bakery for pickup.</p>
                        @elseif($order->status === 'preparing')
                            <p class="text-cocoa-500 pt-1">Our bakers and decorators are actively preparing your order.</p>
                        @endif
                    </div>
                </div>
            @endif

            @if (!in_array($order->status, ['completed', 'cancelled'], true))
                <section class="checkout-card p-4 sm:p-5 space-y-2" aria-label="Order cancellation">
                    @if ($order->canCustomerCancel())
                        <details data-order-cancellation class="group" @if($errors->hasAny(['cancellation', 'confirm_cancellation'])) open @endif>
                            <summary class="flex items-center justify-between cursor-pointer list-none [&::-webkit-details-marker]:hidden select-none">
                                <div class="flex items-center gap-2.5">
                                    <div class="w-7 h-7 rounded-lg bg-rose-50 text-rose-700 flex items-center justify-center shrink-0 border border-rose-100/80">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                    </div>
                                    <div>
                                        <span class="font-bold text-cocoa-700 text-sm block">Cancel order</span>
                                        <span class="text-[11px] text-cocoa-400 block group-open:hidden">Changed your plans? Tap to review options</span>
                                    </div>
                                </div>
                                <div class="flex items-center gap-1.5 text-xs font-semibold text-cocoa-500 group-hover:text-red-700 transition">
                                    <span class="group-open:hidden">Manage</span>
                                    <svg class="w-4 h-4 text-cocoa-400 transition-transform duration-200 group-open:rotate-180" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                    </svg>
                                </div>
                            </summary>
                            <form action="{{ route('public.order.cancel', $order->private_token) }}" method="POST" class="mt-3.5 pt-3.5 border-t border-cocoa-100 space-y-3">
                                @csrf
                                <div class="p-3 rounded-lg bg-rose-50/70 border border-rose-200/70 text-xs text-rose-900 leading-relaxed space-y-1.5">
                                    <div class="flex items-start gap-2">
                                        <svg class="w-4 h-4 text-rose-700 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                                        <div class="space-y-1">
                                            <p class="font-semibold text-rose-950">Cancellation is immediate and final.</p>
                                            <p class="text-rose-800">Your order will no longer be prepared or available for pickup.</p>
                                            @if ($order->hasVerifiedPayment())
                                                <p class="font-medium text-rose-950 pt-0.5">Your verified 50% deposit of <strong>₱{{ number_format($order->amount_paid, 2) }}</strong> will be retained. It will not be refunded.</p>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                                <label class="flex items-start gap-2.5 text-xs text-cocoa-700 leading-relaxed cursor-pointer select-none">
                                    <input type="checkbox" name="confirm_cancellation" value="1" required class="mt-0.5 rounded border-cocoa-300 text-red-600 focus:ring-red-500">
                                    <span class="font-medium">I understand and want to cancel this order.</span>
                                </label>
                                <div class="flex items-center gap-2.5 pt-1">
                                    <button type="submit" class="px-4 py-2.5 rounded-lg bg-red-700 hover:bg-red-800 text-white text-xs font-semibold shadow-xs transition focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-red-700">
                                        Yes, cancel order
                                    </button>
                                    <button type="button" onclick="this.closest('details').removeAttribute('open')" class="px-3.5 py-2.5 rounded-lg border border-cocoa-200 bg-white hover:bg-cream-100 text-cocoa-600 text-xs font-semibold transition">
                                        Never mind, keep order
                                    </button>
                                </div>
                            </form>
                        </details>
                    @else
                        <div class="flex items-center gap-2.5 pb-2 border-b border-cocoa-100">
                            <div class="w-7 h-7 rounded-lg bg-cocoa-50 text-cocoa-500 flex items-center justify-center shrink-0 border border-cocoa-100">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            </div>
                            <h2 class="font-bold text-cocoa-700 text-sm">Need to cancel?</h2>
                        </div>
                        <p class="text-xs text-cocoa-600 leading-relaxed">To cancel, <a href="#contact-bakery-heading" class="font-semibold underline text-cocoa-700 hover:text-cocoa-900">contact the bakery</a>. The Owner must check your reported transfer or payment record first.</p>
                    @endif
                    @foreach (['cancellation', 'confirm_cancellation'] as $field)
                        @error($field)<p role="alert" class="text-xs text-red-700">{{ $message }}</p>@enderror
                    @endforeach
                </section>
            @endif

            {{-- Need Help / Contact Bakery Card --}}
            <section class="checkout-card p-4 sm:p-5 space-y-3" aria-labelledby="contact-bakery-heading">
                <div class="flex items-center gap-2.5 pb-2.5 border-b border-cocoa-100">
                    <div class="w-7 h-7 rounded-lg bg-cocoa-50 text-cocoa-600 flex items-center justify-center shrink-0 border border-cocoa-100">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" /></svg>
                    </div>
                    <h2 id="contact-bakery-heading" class="font-bold text-cocoa-700 text-sm">Need help or have questions?</h2>
                </div>
                <p class="text-xs text-cocoa-500">
                    For questions about your order, payment receipts, or pickup schedule, message or call us:
                </p>
                <div class="space-y-2.5 text-xs">
                    <div class="flex items-center gap-2.5 text-cocoa-600">
                        <svg class="w-4 h-4 text-cocoa-400 shrink-0" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2C6.477 2 2 6.145 2 11.258c0 2.908 1.455 5.503 3.735 7.151V22l3.435-1.886c.905.251 1.861.387 2.83.387 5.523 0 10-4.145 10-9.258C22 6.145 17.523 2 12 2zm1.053 12.443l-2.618-2.793-5.111 2.793 5.623-5.967 2.684 2.793 5.045-2.793-5.623 5.967z"/></svg>
                        <div class="min-w-0">
                            <span class="text-cocoa-400 block text-[11px] font-medium">Facebook Messenger</span>
                            <a href="{{ config('bakery.facebook_url', 'https://m.me/alingchonacakes') }}" target="_blank" rel="noopener noreferrer" class="font-semibold text-cocoa-700 hover:text-cocoa-900 underline inline-flex items-center gap-1">
                                {{ config('bakery.facebook_name', 'Aling Chona Cake & Cupcake') }}
                                <svg class="w-3 h-3 text-cocoa-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                            </a>
                        </div>
                    </div>
                    <div class="flex items-center gap-2.5 text-cocoa-600">
                        <svg class="w-4 h-4 text-cocoa-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                        <div>
                            <span class="text-cocoa-400 block text-[11px] font-medium">Phone / SMS</span>
                            <span class="font-semibold text-cocoa-700">{{ config('bakery.contact_phone', '0917 123 4567') }}</span>
                        </div>
                    </div>
                    <div class="flex items-center gap-2.5 text-cocoa-600">
                        <svg class="w-4 h-4 text-cocoa-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                        <div>
                            <span class="text-cocoa-400 block text-[11px] font-medium">Store & Pickup</span>
                            <span class="text-cocoa-700 font-medium">{{ config('bakery.location', 'Aling Chona Store & Residence') }}</span>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </div>
</div>

<script>
(() => {
    let timer = null;
    const checkStatus = async () => {
        if (document.hidden || document.querySelector('[data-order-cancellation][open]')) return;
        try {
            const res = await fetch(window.location.href, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            if (res.ok) {
                const html = await res.text();
                const parser = new DOMParser();
                const doc = parser.parseFromString(html, 'text/html');
                const newStatus = doc.querySelector('.order-workflow-status')?.textContent;
                const currentStatus = document.querySelector('.order-workflow-status')?.textContent;
                if (newStatus && currentStatus && newStatus.trim() !== currentStatus.trim()) {
                    window.location.reload();
                }
            }
        } catch {}
    };
    timer = setInterval(checkStatus, 30000);
    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) checkStatus();
    });
})();
</script>
@endsection
