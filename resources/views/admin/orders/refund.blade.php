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
            <form action="{{ route('refunds.complete', $refund) }}" method="POST" class="space-y-3">@csrf
                <div><label for="refund-method">Refund method</label><select id="refund-method" name="method" required class="w-full"><option value="gcash">GCash</option><option value="cash">Cash</option></select></div>
                <div><label for="refund-reference">Transfer reference or cash receipt reference</label><input id="refund-reference" name="reference_number" maxlength="100" required class="w-full"></div>
                <label class="flex items-start gap-3"><input type="checkbox" name="transfer_confirmed" value="1" required><span>I confirm the full refund has actually been transferred or returned to the buyer.</span></label>
                <button class="w-full px-4 py-3 bg-cocoa-600 text-white rounded-lg">Confirm completed refund</button>
            </form>
        @endif
    @elseif ($order->cancellation_kind === 'bakery_failure')
        <p class="text-sm">Bakery failure: {{ $order->cancellation_reason }}. No verified payments were received, so no refund is due.</p>
    @elseif ($order->status !== 'cancelled' && (!$order->ready_at || $order->ready_at->gt($deadline)))
        <details><summary class="cursor-pointer py-2 font-medium text-red-800">Cannot fulfill by the pickup deadline</summary>
            <form action="{{ route('orders.bakeryFailure', $order) }}" method="POST" class="space-y-3 mt-3" onsubmit="return confirm('Cancel for bakery failure and request a full refund of all verified payments?');">@csrf
                <p class="text-sm">This cancels the order for bakery failure. All verified payments (₱{{ number_format($order->amount_paid, 2) }}) must be returned, including any final payment.</p>
                <div><label for="failure-reason">Reason shown to the buyer</label><textarea id="failure-reason" name="reason" rows="3" maxlength="1000" required class="w-full"></textarea></div>
                <button class="w-full px-4 py-2 border border-red-200 text-red-800 rounded-lg">Mark bakery failure</button>
            </form>
        </details>
    @endif
</section>
