<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Refund;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class RefundService
{
    public function markBakeryFailure(Order $order, string $reason, ?User $user): ?Refund
    {
        $user = StaffAccess::require($user);
        Validator::make(['reason' => trim($reason)], ['reason' => ['required', 'string', 'max:1000']])->validate();

        return DB::transaction(function () use ($order, $reason, $user) {
            $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            $deadline = $order->pickupDeadline();
            // A customer collecting late is not a bakery failure. Historical
            // orders have no reliable readiness timestamp; the explicit staff
            // declaration and reason are required, never an inferred deadline.
            if ($order->status === 'cancelled'
                || ($order->ready_at && $order->ready_at->lte($deadline))) {
                throw ValidationException::withMessages(['reason' => 'This order is already cancelled or has a recorded on-time readiness timestamp.']);
            }
            $order->update([
                'status' => 'cancelled', 'cancelled_at' => now(),
                'cancellation_kind' => 'bakery_failure', 'cancellation_reason' => trim($reason),
            ]);
            $amount = $order->amount_paid;
            if ($amount <= 0) {
                return null;
            }

            return $order->refund()->create([
                'amount' => $amount, 'reason' => trim($reason), 'requested_by' => $user->id,
            ]);
        });
    }

    public function complete(Refund $refund, array $data, ?User $user): Refund
    {
        $user = StaffAccess::require($user);
        $data = Validator::make($data, [
            'method' => ['required', 'in:cash,gcash'],
            'reference_number' => ['required', 'string', 'max:100'],
            'transfer_confirmed' => ['accepted'],
        ])->validate();

        return DB::transaction(function () use ($refund, $data, $user) {
            Order::whereKey($refund->order_id)->lockForUpdate()->firstOrFail();
            $refund = Refund::whereKey($refund->id)->lockForUpdate()->firstOrFail();
            if ($refund->status !== 'pending') {
                throw ValidationException::withMessages(['refund' => 'This refund has already been completed.']);
            }
            $refund->update([
                'method' => $data['method'], 'reference_number' => trim($data['reference_number']),
                'completed_by' => $user->id, 'completed_at' => now(), 'status' => 'completed',
            ]);

            return $refund;
        });
    }
}
