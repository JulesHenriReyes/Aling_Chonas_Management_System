@if ($order->user_id === null)
<section class="section-form space-y-4" aria-labelledby="proof-review">
    <h2 id="proof-review" class="font-semibold text-cocoa-600">GCash receipt review</h2>
    <p class="text-sm">Check the incoming transaction, exact deposit and reference in the business GCash account before accepting. A screenshot alone is not payment.</p>
    @forelse ($order->paymentProofs as $proof)
        <article class="border-t border-cocoa-100 pt-4 space-y-3">
            <div class="page-heading"><h3 class="font-semibold">Receipt {{ $proof->id }}</h3><x-status :value="$proof->status" /></div>
            <p class="text-sm">Submitted {{ $proof->created_at->format('M j, Y g:i A') }} · Customer reference: <strong>{{ $proof->reference_number }}</strong></p>
            <a href="{{ route('proofs.receipt', $proof) }}" target="_blank" rel="noopener noreferrer" class="inline-block underline">View screenshot</a>
            <img src="{{ route('proofs.receipt', $proof) }}" alt="GCash screenshot for receipt {{ $proof->id }}" loading="lazy" class="receipt-preview">
            @if ($proof->reviewed_at)<p class="text-sm">Reviewed by {{ $proof->reviewer?->full_name }} on {{ $proof->reviewed_at->format('M j, Y g:i A') }}.</p>@endif
            @if ($proof->rejection_reason)<p class="text-sm"><strong>Rejection reason:</strong> {{ $proof->rejection_reason }}</p>@endif
            @if ($proof->status === 'awaiting_verification' && $order->status === 'pending')
                <form action="{{ route('proofs.accept', $proof) }}" method="POST" class="space-y-4">
                    @csrf
                    <p class="text-sm">Exact deposit required: <strong>₱{{ number_format($order->required_down_payment, 2) }}</strong></p>
                    <div class="grid sm:grid-cols-2 gap-4">
                        <div><label for="verified-amount-{{ $proof->id }}">Amount received in GCash (₱)</label><input id="verified-amount-{{ $proof->id }}" name="amount" type="number" min="0.01" step="0.01" required class="w-full"></div>
                        <div><label for="verified-reference-{{ $proof->id }}">Reference in business account</label><input id="verified-reference-{{ $proof->id }}" name="reference_number" value="{{ old('reference_number', $proof->reference_number) }}" maxlength="100" required spellcheck="false" class="w-full"></div>
                    </div>
                    <label class="flex items-start gap-3"><input type="checkbox" name="account_checked" value="1" required><span>I checked the successful incoming transaction in the business GCash account, including the exact amount and reference.</span></label>
                    <button class="px-4 py-3 bg-cocoa-600 text-white rounded-lg hover:bg-cocoa-700">Accept deposit and confirm order</button>
                </form>
                <details class="border-t border-red-200 pt-3"><summary class="cursor-pointer py-2 font-semibold text-red-800">Reject this receipt</summary>
                    <form action="{{ route('proofs.reject', $proof) }}" method="POST" class="space-y-3 mt-3">@csrf
                        <div><label for="reject-reason-{{ $proof->id }}">Reason shown to the buyer</label><textarea id="reject-reason-{{ $proof->id }}" name="reason" rows="2" maxlength="1000" required class="w-full"></textarea></div>
                        <button class="px-4 py-2 bg-red-700 hover:bg-red-800 text-white font-semibold rounded-lg">Reject receipt</button>
                    </form>
                </details>
            @endif
        </article>
    @empty<p>No receipt submitted yet. The buyer can upload one through their private link.</p>@endforelse
</section>
@endif
