<?php

namespace App\Services;

use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class OrderReviewService
{
    public function confirm(Order $order, ?User $user, bool $feasibilityConfirmed = false): Order
    {
        $user = StaffAccess::require($user);
        if (! $feasibilityConfirmed) {
            throw ValidationException::withMessages(['feasibility_confirmed' => 'Confirm you reviewed the request and the bakery can fulfil it.']);
        }

        return DB::transaction(function () use ($order, $user) {
            $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            if ($order->hasApprovedReview() && $order->status === 'confirmed') {
                return $order; // Preserve the first reviewer and decision time on replay.
            }
            if (! $order->needsStaffReview() || $order->hasDownPayment()) {
                throw ValidationException::withMessages(['review' => 'Only an unpaid request awaiting staff review can be confirmed. Existing funds require Owner reconciliation.']);
            }
            if ($order->paymentProofs()->exists()) {
                StaffAccess::requireOwner($user);
            }
            if ($order->pickupDeadline()->isPast()) {
                throw ValidationException::withMessages(['review' => 'The requested pickup has passed. Review the request with the customer before confirming.']);
            }
            if ($order->total_amount <= 0 || ! $order->orderDetails()->exists()) {
                throw ValidationException::withMessages(['review' => 'A request needs saved items and a valid total before confirmation.']);
            }
            $order->forceFill(['status' => 'confirmed', 'review_status' => 'approved', 'reviewed_by' => $user->id, 'reviewed_at' => now()])->save();

            return $order;
        });
    }

    public function decline(Order $order, string $reason, ?User $user, bool $noFundsChecked = false): Order
    {
        $user = StaffAccess::requireOwner($user);
        $reason = trim($reason);
        Validator::make(['decline_reason' => $reason], ['decline_reason' => ['required', 'string', 'max:1000']])->validate();

        return DB::transaction(function () use ($order, $reason, $user, $noFundsChecked) {
            $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            if ($order->status === 'cancelled' && $order->cancellation_kind === 'staff_rejected') {
                return $order;
            }
            if (! $order->needsStaffReview() || $order->hasDownPayment()) {
                throw ValidationException::withMessages(['review' => 'Only an unpaid request awaiting review can be declined. Use the existing cancellation or bakery-failure workflow for approved or paid orders.']);
            }
            if ($order->paymentProofs()->exists() && (! $noFundsChecked || $order->review_status !== null)) {
                throw ValidationException::withMessages(['no_funds_checked' => 'Check every reported transfer in the business account first. If funds were received, reconcile them for a full refund instead.']);
            }
            $order->forceFill(['status' => 'cancelled', 'review_status' => 'rejected', 'reviewed_by' => $user->id,
                'reviewed_at' => now(), 'cancelled_at' => now(), 'cancellation_kind' => 'staff_rejected', 'cancellation_reason' => $reason])->save();

            return $order;
        });
    }
}
