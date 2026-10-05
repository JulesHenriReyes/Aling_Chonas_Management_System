@php($deadline = $order->pickupDeadline())
<section class="section-form space-y-4" aria-labelledby="readiness-deadline">
    <h2 id="readiness-deadline" class="font-semibold text-cocoa-600">Readiness and refunds</h2>
    <p class="text-sm">Ready for pickup deadline: <strong>{{ $deadline->format('M j, Y g:i A') }}</strong> ({{ config('bakery.pickup_timezone') }}). Completion happens after collection and final payment.</p>
    @if ($order->ready_at)
        <p class="text-sm">Marked ready: {{ $order->ready_at->copy()->timezone(config('bakery.pickup_timezone'))->format('M j, Y g:i A') }} · {{ $order->ready_at->lte($deadline) ? 'On time' : 'After the deadline' }}</p>
    @elseif (in_array($order->status, ['ready_for_pickup', 'completed']))
        <p class="text-sm">This historical order has no recorded readiness timestamp. Check the bakery’s records before declaring a missed deadline; a late customer collection alone is not a bakery failure.</p>
    @elseif (now()->gt($deadline) && $order->status !== 'cancelled')
        <p class="text-amber-800 text-sm">The pickup deadline has passed and this order has not been marked ready.</p>
    @endif
    @if ($order->refund)
        @php($refund = $order->refund)
        <h3 class="font-semibold">Full refund · {{ ucfirst($refund->status) }}</h3>
        <p class="font-bold text-xl">₱{{ number_format($refund->amount, 2) }}</p>
        <p class="text-sm">{{ $refund->reason }}</p>
        <p class="text-sm">Requested by {{ $refund->requestedBy?->full_name }} on {{ $refund->created_at->format('M j, Y g:i A') }}.</p>
        @if ($refund->status === 'completed')
            <p class="text-sm">Transferred via {{ strtoupper($refund->method) }} · Reference: {{ $refund->reference_number }}</p>
            <p class="text-sm">Confirmed by {{ $refund->completedBy?->full_name }} on {{ $refund->completed_at->format('M j, Y g:i A') }}.</p>
        @else
            <p class="text-sm">Return the full amount outside this website. Keep the original payment records. Complete this form only after the transfer succeeds.</p>
            @can('manage-refunds')
            <form action="{{ route('refunds.complete', $refund) }}" method="POST" data-action-form data-validation-active="{{ old('_workflow') === 'refund' ? 'true' : 'false' }}" class="space-y-3">@csrf
                <input type="hidden" name="_workflow" value="refund">
                <div><label for="refund-method">Refund method</label><select id="refund-method" name="method" required class="w-full"><option value="gcash" @selected(old('method', 'gcash') === 'gcash')>GCash</option><option value="cash" @selected(old('method') === 'cash')>Cash</option></select></div>
                <div><label for="refund-reference">Transfer reference or cash receipt reference</label><input id="refund-reference" name="reference_number" maxlength="100" required value="{{ old('reference_number') }}" class="w-full"></div>
                <label class="flex items-start gap-3"><input type="checkbox" name="transfer_confirmed" value="1" required {{ old('transfer_confirmed') ? 'checked' : '' }}><span>I confirm the full refund has actually been transferred or returned to the buyer.</span></label>
                <button class="w-full px-4 py-3 bg-cocoa-600 text-white rounded-lg">Confirm completed refund</button>
                <span role="status" data-submit-status></span>
            </form>
            @endcan
        @endif
    @elseif ($order->cancellation_kind === 'bakery_failure')
        <p class="text-sm">Bakery failure: {{ $order->cancellation_reason }}. No verified payments were received, so no refund is due.</p>
    @elseif (Gate::allows('manage-refunds') && !in_array($order->status, ['cancelled', 'completed']))
        <details><summary class="bakery-failure-action cursor-pointer py-2 font-medium text-red-800">Bakery cannot fulfil this order</summary>
            <form action="{{ route('orders.bakeryFailure', $order) }}" method="POST" data-action-form data-validation-active="{{ old('_workflow') === 'failure' ? 'true' : 'false' }}" class="space-y-3 mt-3" onsubmit="return confirm('Cancel for bakery failure and request a full refund of all verified payments?');">@csrf
                <input type="hidden" name="_workflow" value="failure">
                <p class="text-sm">This cancels the order for bakery failure. All verified payments (₱{{ number_format($order->amount_paid, 2) }}) must be returned, including any final payment.</p>
                <p class="text-sm">Use this for an actual bakery failure, including one after the order was marked ready. A customer collecting late alone is not a bakery failure.</p>
                <div><label for="failure-reason">Reason shown to the buyer</label><textarea id="failure-reason" name="reason" rows="3" maxlength="1000" required class="w-full">{{ old('reason') }}</textarea>@error('reason')<p role="alert" data-error-for="reason" class="text-sm text-red-700">{{ $message }}</p>@enderror</div>
                <label class="flex items-start gap-3"><input type="checkbox" name="bakery_failure_confirmed" value="1" required {{ old('bakery_failure_confirmed') ? 'checked' : '' }}><span>I confirm the bakery cannot fulfil this order. This is not solely a late customer collection.</span></label>
                @error('bakery_failure_confirmed')<p role="alert" data-error-for="bakery_failure_confirmed" class="text-sm text-red-700">{{ $message }}</p>@enderror
                @if ($order->amount_paid === 0.0 && $order->paymentProofs->isNotEmpty())
                    <label class="flex items-start gap-3 text-sm"><input type="checkbox" name="no_funds_checked" value="1" required><span>I checked every reported transfer in the business account and no funds were received. Received money must be verified and reconciled first.</span></label>
                    @error('no_funds_checked')<p role="alert" data-error-for="no_funds_checked" class="text-sm text-red-700">{{ $message }}</p>@enderror
                @endif
                <button class="w-full px-4 py-2 border border-red-200 text-red-800 rounded-lg">Mark bakery failure</button>
                <span role="status" data-submit-status></span>
            </form>
        </details>
    @endif
</section>
