<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Refund;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class RefundService
{
    public function markBakeryFailure(Order $order, string $reason, ?User $user, bool $failureConfirmed = false, bool $noFundsChecked = false): ?Refund
    {
        $user = StaffAccess::requireOwner($user);
        Validator::make(['reason' => trim($reason)], ['reason' => ['required', 'string', 'max:1000']])->validate();
        if (! $failureConfirmed) {
            throw ValidationException::withMessages(['bakery_failure_confirmed' => 'Confirm the bakery cannot fulfil the order. Late customer collection alone is not a bakery failure.']);
        }

        return DB::transaction(function () use ($order, $reason, $user, $noFundsChecked) {
            $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            // Readiness does not rule out a later genuine inability to fulfil.
            // The Owner must declare an actual bakery failure, not late collection.
            if (in_array($order->status, ['cancelled', 'completed'], true)) {
                throw ValidationException::withMessages(['reason' => 'Completed or cancelled orders cannot be declared a bakery failure.']);
            }
            if ($order->amount_paid === 0.0 && $order->paymentProofs()->exists() && ! $noFundsChecked) {
                throw ValidationException::withMessages(['no_funds_checked' => 'Check the reported transfer first. Verify received money before requesting its refund; do not close a reported transfer as unpaid.']);
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
        $user = StaffAccess::requireOwner($user);
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
