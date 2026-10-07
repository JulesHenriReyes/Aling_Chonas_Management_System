@if ($order->user_id === null)
@php
    $isVerified = $order->hasVerifiedDeposit() || $order->payment_status === 'fully_paid';
@endphp
<section class="section-form" aria-labelledby="proof-review">
    <details class="group" {{ $isVerified ? '' : 'open' }}>
        <summary class="flex justify-between items-center cursor-pointer list-none [&::-webkit-details-marker]:hidden">
            <h2 id="proof-review" class="font-semibold text-cocoa-600 m-0">GCash receipt review</h2>
            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 transition-transform group-open:-rotate-180 text-cocoa-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
            </svg>
        </summary>
        <div class="mt-4 space-y-4">
            <p class="text-sm">Check the incoming transaction, exact deposit and reference in the business GCash account before accepting. A screenshot alone is not payment.</p>
            @forelse ($order->paymentProofs as $proof)
                <article class="border-t border-cocoa-100 pt-4 space-y-3">
                    <div class="page-heading"><h3 class="font-semibold">Receipt {{ $proof->id }}</h3><x-status :value="$proof->status" /></div>
                    <p class="text-sm">Submitted {{ $proof->created_at->format('M j, Y g:i A') }} · Customer reference: <strong>{{ $proof->reference_number }}</strong></p>
                    <div x-data="{ expanded: false }">
                        <button type="button" @click="expanded = true" class="block text-left focus:outline-none">
                            <img src="{{ route('proofs.receipt', $proof) }}" alt="GCash screenshot for receipt {{ $proof->id }}" loading="lazy" class="receipt-preview cursor-pointer hover:opacity-90 transition-opacity">
                            <span class="inline-flex items-center gap-1 text-xs font-semibold text-cocoa-600 underline mt-1.5 hover:text-cocoa-700">View screenshot</span>
                        </button>
                        <template x-teleport="body">
                            <div x-show="expanded" style="display: none;" class="fixed inset-0 z-[100] flex items-center justify-center bg-black bg-opacity-80 p-4 backdrop-blur-sm" @keydown.escape.window="expanded = false" data-dialog role="dialog" aria-modal="true" aria-label="Receipt screenshot preview">
                                <div class="relative w-full h-full flex justify-center items-center" @click.outside="expanded = false">
                                    <button @click="expanded = false" class="absolute top-4 right-4 text-white hover:text-gray-300 focus:outline-none z-[110]" aria-label="Close screenshot preview">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 drop-shadow-md" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                                    </button>
                                    <img src="{{ route('proofs.receipt', $proof) }}" alt="Expanded GCash screenshot" class="max-w-full max-h-full object-contain rounded drop-shadow-2xl">
                                </div>
                            </div>
                        </template>
                    </div>
                    @if ($proof->reviewed_at)<p class="text-sm">Reviewed by {{ $proof->reviewer?->full_name }} on {{ $proof->reviewed_at->format('M j, Y g:i A') }}.</p>@endif
                    @if ($proof->rejection_reason)<p class="text-sm"><strong>Rejection reason:</strong> {{ $proof->rejection_reason }}</p>@endif
                    @if (Gate::allows('review-proofs') && $proof->status === 'awaiting_verification' && $order->canRecordDeposit())
                        <form action="{{ route('proofs.accept', $proof) }}" method="POST" class="space-y-4">
                            @csrf
                            <p class="text-sm">Exact deposit required: <strong>₱{{ number_format($order->required_down_payment, 2) }}</strong></p>
                            <div class="grid sm:grid-cols-2 gap-4">
                                <div><label for="verified-amount-{{ $proof->id }}">Amount received in GCash (₱)</label><input id="verified-amount-{{ $proof->id }}" name="amount" type="number" min="0.01" step="0.01" required class="w-full"></div>
                                <div><label for="verified-reference-{{ $proof->id }}">Reference in business account</label><input id="verified-reference-{{ $proof->id }}" name="reference_number" value="{{ old('reference_number', $proof->reference_number) }}" maxlength="100" required spellcheck="false" class="w-full"></div>
                            </div>
                            <label class="flex items-start gap-3"><input type="checkbox" name="account_checked" value="1" required><span class="inline-flex items-center gap-1 flex-wrap">I checked the successful incoming transaction in the business GCash account, including the exact amount and reference. <x-tooltip text="Staff must independently log in to the merchant GCash wallet app to verify fund arrival and exact reference ID before checking this box. Screenshots alone can be manipulated." /></span></label>
                            <button class="px-4 py-3 bg-cocoa-600 text-white rounded-lg hover:bg-cocoa-700">Verify deposit and secure booking</button>
                        </form>
                        <details class="border-t border-red-200 pt-3"><summary class="cursor-pointer py-2 font-semibold text-red-800">Reject this receipt</summary>
                            <form action="{{ route('proofs.reject', $proof) }}" method="POST" class="space-y-3 mt-3">@csrf
                                <div><label for="reject-reason-{{ $proof->id }}">Reason shown to the buyer</label><textarea id="reject-reason-{{ $proof->id }}" name="reason" rows="2" maxlength="1000" required class="w-full resize-y"></textarea></div>
                                <button class="px-4 py-2 bg-red-700 hover:bg-red-800 text-white font-semibold rounded-lg">Reject receipt</button>
                            </form>
                        </details>
                    @endif
                    @if (Gate::allows('manage-refunds') && $order->review_status === null && $order->needsStaffReview() && in_array($proof->status, ['awaiting_verification', 'rejected'], true))
                        <details><summary class="cursor-pointer py-3 text-red-800 font-semibold">Legacy transfer received, but request cannot be fulfilled</summary>
                            <form action="{{ route('proofs.reconcileRefund', $proof) }}" method="POST" data-action-form data-validation-active="{{ old('_workflow') === 'legacy-refund' ? 'true' : 'false' }}" class="space-y-3 mt-3">
                                @csrf
                                <input type="hidden" name="_workflow" value="legacy-refund">
                                <p class="text-sm">Check the actual successful incoming transfer first. This records the exact pre-change deposit and requests its full refund without approving the order. Mismatched amounts need separate Owner reconciliation; do not discard funds.</p>
                                <div><label for="legacy-amount-{{ $proof->id }}">Amount actually received (₱)</label><input id="legacy-amount-{{ $proof->id }}" type="number" name="amount" min="0.01" step="0.01" required value="{{ old('amount') }}" class="w-full"></div>
                                <div><label for="legacy-reference-{{ $proof->id }}">Reference in business account</label><input id="legacy-reference-{{ $proof->id }}" name="reference_number" maxlength="100" required value="{{ old('reference_number', $proof->reference_number) }}" class="w-full"></div>
                                <div><label for="legacy-reason-{{ $proof->id }}">Why the bakery cannot fulfil the request</label><textarea id="legacy-reason-{{ $proof->id }}" name="reason" maxlength="1000" rows="3" required class="w-full">{{ old('reason') }}</textarea></div>
                                <label class="flex items-start gap-3 text-sm"><input type="checkbox" name="account_checked" value="1" required><span class="inline-flex items-center gap-1 flex-wrap">I verified the successful incoming amount and matching reference in the business account. <x-tooltip text="Mandatory verification: staff must confirm the incoming transfer in the merchant business account before issuing a refund." /></span></label>
                                <label class="flex items-start gap-3 text-sm"><input type="checkbox" name="bakery_failure_confirmed" value="1" required><span>The bakery cannot fulfil this request. All verified money must be returned.</span></label>
                                <button type="submit" class="w-full py-3 px-4 bg-red-700 text-white font-semibold rounded-lg">Verify legacy deposit and request full refund</button>
                                <span role="status" data-submit-status></span>
                            </form>
                        </details>
                    @endif
                </article>
            @empty<p>No receipt submitted yet. The buyer can upload one through their private link after staff confirmation.</p>@endforelse
        </div>
    </details>
</section>
@endif
