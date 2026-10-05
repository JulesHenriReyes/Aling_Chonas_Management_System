<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentProof;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class PaymentReviewService
{
    public function submit(Order $order, UploadedFile $receipt, string $reference): PaymentProof
    {
        Validator::make(['receipt' => $receipt, 'reference_number' => $reference], [
            'receipt' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'reference_number' => ['required', 'string', 'max:100'],
        ])->validate();
        $path = null;
        try {
            return DB::transaction(function () use ($order, $receipt, $reference, &$path) {
                $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
                if ($order->user_id !== null || $order->status !== 'pending' || $order->hasDownPayment()
                    || $order->paymentProofs()->where('status', 'awaiting_verification')->exists()) {
                    throw ValidationException::withMessages(['receipt' => 'A receipt can be submitted only for a pending public order without a receipt awaiting review.']);
                }
                $reference = app(OrderService::class)->normalizeReference($reference);
                if ($reference === '' || DB::table('gcash_references')->where('reference_number', $reference)->exists()) {
                    throw ValidationException::withMessages(['reference_number' => 'Enter an unused GCash transaction reference.']);
                }
                $path = $receipt->store("orders/{$order->id}", 'receipts');

                return $order->paymentProofs()->create(['file_path' => $path, 'reference_number' => $reference]);
            });
        } catch (\Throwable $exception) {
            if ($path) {
                Storage::disk('receipts')->delete($path);
            }
            throw $exception;
        }
    }

    public function accept(PaymentProof $proof, float $observedAmount, string $observedReference, ?User $user): Payment
    {
        $user = StaffAccess::requireOwner($user);

        return DB::transaction(function () use ($proof, $observedAmount, $observedReference, $user) {
            $order = Order::whereKey($proof->order_id)->lockForUpdate()->firstOrFail();
            $proof = PaymentProof::whereKey($proof->id)->lockForUpdate()->firstOrFail();
            $orders = app(OrderService::class);
            if ($proof->status !== 'awaiting_verification'
                || $proof->reference_number !== $orders->normalizeReference($observedReference)) {
                throw ValidationException::withMessages(['reference_number' => 'The proof must be awaiting review and match the transaction reference in the business GCash account.']);
            }

            return $orders->recordDownPayment($order, $observedAmount, 'gcash', $observedReference, $user, null, $proof);
        });
    }

    public function reject(PaymentProof $proof, string $reason, ?User $user): void
    {
        $user = StaffAccess::requireOwner($user);
        Validator::make(['reason' => trim($reason)], ['reason' => ['required', 'string', 'max:1000']])->validate();
        DB::transaction(function () use ($proof, $reason, $user) {
            $order = Order::whereKey($proof->order_id)->lockForUpdate()->firstOrFail();
            $proof = PaymentProof::whereKey($proof->id)->lockForUpdate()->firstOrFail();
            if ($order->status !== 'pending' || $proof->status !== 'awaiting_verification') {
                throw ValidationException::withMessages(['receipt' => 'Only an awaiting receipt on a pending order can be rejected.']);
            }
            $proof->update(['status' => 'rejected', 'rejection_reason' => trim($reason), 'reviewed_by' => $user->id, 'reviewed_at' => now()]);
        });
    }
}
