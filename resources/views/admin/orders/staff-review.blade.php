<section class="section-form space-y-4" aria-labelledby="staff-review-heading">
    <h2 id="staff-review-heading" class="font-semibold text-cocoa-700">Staff confirmation</h2>
    <p class="text-sm">{{ $order->workflowLabel() }}. Confirmation checks whether the bakery can fulfil the saved request; it does not record a payment.</p>
    @if ($order->reviewed_at)
        <p class="text-sm">{{ ucfirst($order->review_status) }} by {{ $order->reviewer?->full_name }} on {{ $order->reviewed_at->copy()->timezone(config('bakery.pickup_timezone'))->format('M j, Y g:i A') }}.</p>
        @if ($order->cancellation_kind === 'staff_rejected')<p class="text-sm">Reason: {{ $order->cancellation_reason }}</p>@endif
    @elseif ($order->amount_paid > 0)
        <p class="text-sm">Historical payment records are preserved. This request has no recorded staff-review timestamp; the Owner should reconcile any inconsistent lifecycle before requesting more money.</p>
    @endif
    @if ($order->needsStaffReview())
        <p class="text-sm">Review the saved items, quantities, specifications and images above, the requested pickup, and available capacity. Payment remains locked until confirmation.</p>
        <p class="text-sm">Other requests on {{ $order->pickup_date->format('M j, Y') }}: <strong>{{ $pickupContext['booked'] }} paid bookings</strong> and <strong>{{ $pickupContext['awaitingDeposit'] }} confirmed requests awaiting payment</strong>.</p>
        @if ($order->paymentProofs->isNotEmpty())
            <p class="p-3 bg-amber-50 text-amber-900 rounded-lg text-sm">A transfer was reported under the previous flow. The Owner must check it. Do not ask the customer to pay again or approve an impossible request just to accept its receipt.</p>
        @endif
        @if (Gate::allows('confirm-orders') && ($order->paymentProofs->isEmpty() || Gate::allows('review-proofs')))
            <form action="{{ route('orders.confirm', $order) }}" method="POST" data-action-form data-validation-active="{{ old('_workflow') === 'review' ? 'true' : 'false' }}" class="space-y-3">
                @csrf
                <input type="hidden" name="_workflow" value="review">
                <label class="flex items-start gap-3 text-sm"><input type="checkbox" name="feasibility_confirmed" value="1" required {{ old('feasibility_confirmed') ? 'checked' : '' }}><span>I reviewed the design, quantities, pickup schedule and capacity. The bakery can fulfil this request.</span></label>
                @error('feasibility_confirmed')<p role="alert" data-error-for="feasibility_confirmed" class="text-sm text-red-700">{{ $message }}</p>@enderror
                <button type="submit" class="w-full px-4 py-3 bg-cocoa-600 hover:bg-cocoa-700 text-white font-semibold rounded-lg">Confirm request</button>
                <span role="status" data-submit-status></span>
            </form>
        @else
            <p class="text-sm">The Owner must reconcile this reported payment before a review decision.</p>
        @endif
        @can('decline-orders')
            <details><summary class="cursor-pointer py-3 font-semibold text-red-800">Decline request</summary>
                <form action="{{ route('orders.decline', $order) }}" method="POST" data-action-form data-validation-active="{{ old('_workflow') === 'decline' ? 'true' : 'false' }}" class="space-y-3 mt-3">
                    @csrf
                    <input type="hidden" name="_workflow" value="decline">
                    <label for="decline-reason">Reason shown to the customer</label>
                    <textarea id="decline-reason" name="decline_reason" rows="3" maxlength="1000" required class="w-full">{{ old('decline_reason') }}</textarea>
                    @error('decline_reason')<p role="alert" data-error-for="decline_reason" class="text-sm text-red-700">{{ $message }}</p>@enderror
                    @if ($order->paymentProofs->isNotEmpty())
                        <label class="flex items-start gap-3 text-sm"><input type="checkbox" name="no_funds_checked" value="1" required {{ old('no_funds_checked') ? 'checked' : '' }}><span>I checked every reported transfer in the business account and confirmed no funds were received. If money arrived, use legacy reconciliation and full refund below.</span></label>
                        @error('no_funds_checked')<p role="alert" data-error-for="no_funds_checked" class="text-sm text-red-700">{{ $message }}</p>@enderror
                    @endif
                    <button type="submit" class="w-full px-4 py-3 bg-red-700 hover:bg-red-800 text-white font-semibold rounded-lg">Decline request with reason</button>
                    <span role="status" data-submit-status></span>
                </form>
            </details>
        @endcan
    @endif
</section>
