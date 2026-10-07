@if ($order->user_id === null && ($order->paymentProofs->isNotEmpty() || $order->canSubmitReceipt()))
@php
    $needsReceiptReview = $order->hasAwaitingReceipt() && !in_array($order->status, ['completed', 'cancelled'], true);
@endphp
<section class="section-form" aria-labelledby="proof-review">
    <details class="group" {{ $needsReceiptReview || $order->canSubmitReceipt() ? 'open' : '' }}>
        <summary class="flex justify-between items-center cursor-pointer list-none [&::-webkit-details-marker]:hidden">
            <h2 id="proof-review" class="font-semibold text-cocoa-600 m-0">{{ $needsReceiptReview ? 'GCash receipt review' : ($order->paymentProofs->isEmpty() ? 'Deposit receipt' : 'GCash receipt history') }}</h2>
            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 transition-transform group-open:-rotate-180 text-cocoa-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
            </svg>
        </summary>
        <div class="mt-4 space-y-4">
            @if ($needsReceiptReview)
                <p class="text-sm">Check the incoming transaction, exact deposit and reference in the business GCash account before accepting. A screenshot alone is not payment.</p>
            @endif
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
                                <div>
                                    <label for="verified-amount-{{ $proof->id }}" class="block text-xs font-bold text-cocoa-600 mb-1">
                                        Amount received in GCash (₱)
                                        <span class="text-[11px] font-normal text-cocoa-400 block">Locked to required 50% deposit</span>
                                    </label>
                                    <input id="verified-amount-{{ $proof->id }}" name="amount" type="number" step="0.01"
                                           value="{{ number_format($order->required_down_payment, 2, '.', '') }}" readonly
                                           class="w-full text-xs rounded-lg border-cocoa-100 bg-cream-100 font-semibold text-cocoa-700 cursor-not-allowed">
                                </div>
                                <div>
                                    <label for="verified-reference-{{ $proof->id }}" class="block text-xs font-bold text-cocoa-600 mb-1">
                                        Reference in business account
                                        <span class="text-[11px] font-normal text-cocoa-400 block">From customer receipt (locked for safety)</span>
                                    </label>
                                    <input id="verified-reference-{{ $proof->id }}" name="reference_number"
                                           value="{{ $proof->reference_number }}" readonly spellcheck="false"
                                           class="w-full text-xs rounded-lg border-cocoa-100 bg-cream-100 font-mono font-semibold text-cocoa-700 cursor-not-allowed">
                                </div>
                            </div>
                            <label class="flex items-start gap-3"><input type="checkbox" name="account_checked" value="1" required><span class="inline-flex items-center gap-1 flex-wrap">I checked the successful incoming transaction in the business GCash account, including the exact amount and reference. <x-tooltip text="Staff must independently log in to the merchant GCash wallet app to verify fund arrival and exact reference ID before checking this box. Screenshots alone can be manipulated." /></span></label>
                            <button class="px-4 py-3 bg-cocoa-600 text-white rounded-lg hover:bg-cocoa-700">Verify deposit and secure booking</button>
                        </form>
                        <details class="border-t border-red-200 pt-3"><summary class="cursor-pointer py-2 font-semibold text-red-800">Reject this receipt</summary>
                            <form action="{{ route('proofs.reject', $proof) }}" method="POST"
                                  x-data="{
                                      selectedReasons: [],
                                      otherChecked: false,
                                      customReason: '',
                                      get compiledReason() {
                                          let parts = [...this.selectedReasons];
                                          if (this.otherChecked && this.customReason.trim()) {
                                              parts.push(this.customReason.trim());
                                          }
                                          return parts.join('. ');
                                      }
                                  }"
                                  class="space-y-3 mt-3">
                                @csrf
                                <input type="hidden" name="reason" :value="compiledReason">

                                <div class="space-y-2 bg-red-50/40 border border-red-100 rounded-xl p-3.5">
                                    <span class="block text-[11px] font-bold text-red-800 uppercase tracking-wider mb-2">
                                        Select Rejection Reason(s)
                                    </span>
                                    <div class="space-y-2 text-xs">
                                        <label class="flex items-start gap-2.5 cursor-pointer hover:text-red-900">
                                            <input type="checkbox" value="Reference number not found in merchant GCash transaction history"
                                                   x-model="selectedReasons"
                                                   class="mt-0.5 rounded border-red-300 text-red-700 focus:ring-red-500">
                                            <span class="text-cocoa-700">Reference number not found in merchant GCash transaction history</span>
                                        </label>
                                        <label class="flex items-start gap-2.5 cursor-pointer hover:text-red-900">
                                            <input type="checkbox" value="Receipt screenshot is blurry, cropped, incomplete, or unreadable"
                                                   x-model="selectedReasons"
                                                   class="mt-0.5 rounded border-red-300 text-red-700 focus:ring-red-500">
                                            <span class="text-cocoa-700">Receipt screenshot is blurry, cropped, or unreadable</span>
                                        </label>
                                        <label class="flex items-start gap-2.5 cursor-pointer hover:text-red-900">
                                            <input type="checkbox" value="Screenshot does not show a completed or successful transaction"
                                                   x-model="selectedReasons"
                                                   class="mt-0.5 rounded border-red-300 text-red-700 focus:ring-red-500">
                                            <span class="text-cocoa-700">Screenshot does not show a completed or successful transaction</span>
                                        </label>
                                        <label class="flex items-start gap-2.5 cursor-pointer hover:text-red-900">
                                            <input type="checkbox" value="Duplicate receipt or reference number already used for a previous order"
                                                   x-model="selectedReasons"
                                                   class="mt-0.5 rounded border-red-300 text-red-700 focus:ring-red-500">
                                            <span class="text-cocoa-700">Duplicate receipt or reference already used for another order</span>
                                        </label>
                                        <label class="flex items-start gap-2.5 cursor-pointer hover:text-red-900">
                                            <input type="checkbox" value="Payment sent to wrong recipient number or incorrect QR code"
                                                   x-model="selectedReasons"
                                                   class="mt-0.5 rounded border-red-300 text-red-700 focus:ring-red-500">
                                            <span class="text-cocoa-700">Payment sent to wrong mobile number or incorrect QR code</span>
                                        </label>
                                        <div class="pt-2 border-t border-red-200/60">
                                            <label class="flex items-start gap-2.5 cursor-pointer font-medium text-cocoa-800">
                                                <input type="checkbox" x-model="otherChecked"
                                                       class="mt-0.5 rounded border-red-300 text-red-700 focus:ring-red-500">
                                                <span>Others (specify custom reason below)</span>
                                            </label>
                                            <div x-show="otherChecked" x-cloak class="mt-2 pl-6">
                                                <input type="text" x-model="customReason"
                                                       placeholder="Type custom rejection explanation..."
                                                       class="w-full text-xs rounded-lg border-cocoa-100 bg-white focus:border-red-500 focus:ring-red-500 placeholder-cocoa-400/60">
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <button type="submit"
                                        :disabled="!compiledReason.trim()"
                                        class="px-4 py-2 bg-red-700 hover:bg-red-800 disabled:opacity-50 disabled:cursor-not-allowed text-white font-semibold text-xs rounded-lg transition">
                                    Reject receipt
                                </button>
                            </form>
                        </details>
                    @endif
                </article>
            @empty<p>The buyer can upload their deposit receipt through their private order link.</p>@endforelse
        </div>
    </details>
</section>
@endif
