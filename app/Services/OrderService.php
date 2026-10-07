<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\OrderImage;
use App\Models\Payment;
use App\Models\PaymentProof;
use App\Models\User;
use App\Support\PickupCalendar;
use App\Support\PickupHours;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OrderService
{
    /**
     * Valid forward-only transitions. Cancellation is handled separately so it
     * remains available from every non-terminal state.
     *
     * @var array<string, list<string>>
     */
    private const STATUS_TRANSITIONS = [
        'pending' => ['confirmed'],
        'confirmed' => ['preparing'],
        'preparing' => ['ready_for_pickup'],
        'ready_for_pickup' => ['completed'],
        'completed' => [],
        'cancelled' => [],
    ];

    /**
     * Create a public buyer-submitted order (user_id strictly null).
     */
    public function createPublicOrder(array $data): Order
    {
        return $this->executeOrderCreation($data, null);
    }

    /**
     * Create an internal staff-created order (user_id strictly required).
     */
    public function createInternalOrder(array $data, ?User $user): Order
    {
        return $this->executeOrderCreation($data, StaffAccess::requireOwner($user));
    }

    /**
     * Execute transactional order creation.
     */
    private function executeOrderCreation(array $data, ?User $user): Order
    {
        $writtenImages = [];
        try {
            return DB::transaction(function () use ($data, $user, &$writtenImages) {
            if (! empty($data['submission_key'])) {
                $existing = Order::where('submission_key', $data['submission_key'])->where('user_id', $user?->id)->first();
                if ($existing) {
                    return $existing;
                }
            }
            if (empty($data['items']) || ! is_array($data['items'])) {
                throw ValidationException::withMessages([
                    'items' => ['At least one product line item is required.'],
                ]);
            }

            // Pickup date cannot be in the past
            $pickupDate = Carbon::parse($data['pickup_date'], config('bakery.pickup_timezone'))->startOfDay();
            if ($pickupDate->lt(PickupCalendar::today())) {
                throw ValidationException::withMessages([
                    'pickup_date' => ['Pickup date cannot be in the past.'],
                ]);
            }

            PickupHours::assertAllowed($data['pickup_time'] ?? null);

            $quote = app(CatalogPricingService::class)->quote($data['items']);
            if (isset($data['expected_total']) && ! $this->sameAmount((float) $data['expected_total'], (float) $quote['total'])) {
                throw ValidationException::withMessages(['items' => 'A catalog price changed. Review the current itemized total before submitting again.']);
            }
            $orderNumber = $this->generateOrderNumber();

            $order = new Order([
                'order_number' => $orderNumber,
                'customer_id' => $data['customer_id'],
                'user_id' => $user?->id,
                'status' => $user ? 'confirmed' : 'pending',
                'pickup_date' => $data['pickup_date'],
                'pickup_time' => $data['pickup_time'],
                'notes_text' => $data['notes_text'] ?? null,
                'private_token' => $user === null ? bin2hex(random_bytes(32)) : null,
                'submission_key' => $data['submission_key'] ?? null,
                'fixed_catalog_pricing' => true,
            ]);
            $order->review_status = $user ? 'approved' : 'pending';
            if ($user) {
                $order->reviewed_by = $user->id;
                $order->reviewed_at = now();
            }
            $order->save();

            foreach (array_values($data['items']) as $index => $item) {
                $line = $quote['lines'][$index];
                $extras = array_map(function ($extra) {
                    unset($extra['photo_path']);
                    return $extra;
                }, $line['add_ons']);
                unset($line['add_ons'], $line['photo_path']);
                $orderDetail = $order->orderDetails()->create($line);
                $orderDetail->addOns()->createMany($extras);

                if (! empty($item['images']) && is_array($item['images'])) {
                    foreach ($item['images'] as $imageFile) {
                        $this->saveOrderDetailImage($order, $orderDetail, $imageFile, $user, $writtenImages);
                    }
                }
            }

            if (! empty($data['images']) && is_array($data['images'])) {
                foreach ($data['images'] as $imageFile) {
                    $this->saveOrderDetailImage($order, null, $imageFile, $user, $writtenImages);
                }
            }

            return $order->load(['customer', 'orderDetails.product', 'orderDetails.addOns', 'orderDetails.images', 'images', 'payments']);
            });
        } catch (\Throwable $exception) {
            foreach ($writtenImages as [$disk, $path]) Storage::disk($disk)->delete($path);
            if ($exception instanceof \Illuminate\Database\UniqueConstraintViolationException && !empty($data['submission_key'])) {
                $original = Order::where('submission_key', $data['submission_key'])->where('user_id', $user?->id)->first();
                if ($original) return $original;
            }
            throw $exception;
        }
    }

    /**
     * Attach an image to an order and optionally an order_detail.
     * Enforces the data integrity rule:
     * order_details.order_id == order_images.order_id
     */
    public function attachImage(
        Order $order,
        string $filePath,
        string $originalFilename,
        ?OrderDetail $orderDetail = null,
        ?User $uploader = null
    ): OrderImage {
        $uploader = StaffAccess::requireOwner($uploader);

        return $this->persistImage($order, $filePath, $originalFilename, $orderDetail, $uploader);
    }

    private function persistImage(Order $order, string $filePath, string $originalFilename, ?OrderDetail $orderDetail, ?User $uploader): OrderImage
    {
        if ($orderDetail !== null && (int) $orderDetail->order_id !== (int) $order->id) {
            throw ValidationException::withMessages([
                'order_detail_id' => ['The specified order detail does not belong to this order.'],
            ]);
        }

        return OrderImage::create([
            'order_id' => $order->id,
            'order_detail_id' => $orderDetail?->id,
            'file_path' => $filePath,
            'original_filename' => $originalFilename,
            'uploaded_by' => $uploader?->id,
        ]);
    }

    /**
     * Helper to save an image file or representation.
     */
    protected function saveOrderDetailImage(Order $order, ?OrderDetail $orderDetail, $imageFile, ?User $user, array &$writtenImages = []): OrderImage
    {
        $disk = $user ? 'staff_references' : 'public';
        $directory = $user ? "staff/{$order->id}" : "order_images/{$order->id}";
        if ($imageFile instanceof UploadedFile) {
            $path = $imageFile->store($directory, $disk);
            if (!$path) throw ValidationException::withMessages(['images' => 'Could not save the design reference. Your draft is kept.']);
            $writtenImages[] = [$disk, $path];
            $filename = $imageFile->getClientOriginalName();
        } elseif (is_array($imageFile)) {
            $filename = $imageFile['original_filename'];
            if (isset($imageFile['staged_path'])) {
                $extension = pathinfo($imageFile['staged_path'], PATHINFO_EXTENSION);
                $path = $directory.'/'.Str::uuid().'.'.$extension;
                $writtenImages[] = [$disk, $path];
                if (!Storage::disk($disk)->put($path, Storage::disk($imageFile['staged_disk'] ?? 'local')->get($imageFile['staged_path']))) {
                    throw ValidationException::withMessages(['images' => 'Could not save the design reference. Your draft is kept.']);
                }
            } else {
                $path = $imageFile['file_path'];
            }
        } else {
            $path = (string) $imageFile;
            $filename = basename($path);
        }

        return $this->persistImage($order, $path, $filename, $orderDetail, $user);
    }

    /**
     * Kept as a rejecting boundary for callers of the old service API.
     */
    public function updateOrderDetailPrice(Order $order, OrderDetail $orderDetail, float $newPrice, ?User $user): OrderDetail
    {
        StaffAccess::requireOwner($user);

        throw ValidationException::withMessages([
            'unit_price' => ['Order prices are fixed catalog snapshots and cannot be adjusted.'],
        ]);
    }

    /**
     * Record the exact deposit for an explicitly confirmed request.
     */
    public function recordDownPayment(
        Order $order,
        float $amount,
        string $paymentMethod,
        ?string $referenceNumber,
        ?User $user,
        ?Carbon $paymentDate = null,
        ?PaymentProof $verifiedProof = null
    ): Payment {
        return $this->persistDownPayment($order, $amount, $paymentMethod, $referenceNumber, $user, $paymentDate, $verifiedProof);
    }

    private function persistDownPayment(Order $order, float $amount, string $paymentMethod, ?string $referenceNumber,
        ?User $user, ?Carbon $paymentDate = null, ?PaymentProof $verifiedProof = null): Payment
    {
        $user = StaffAccess::requireOwner($user);

        return DB::transaction(function () use ($order, $amount, $paymentMethod, $referenceNumber, $user, $paymentDate, $verifiedProof) {
            $order = $this->lockOrder($order);
            $order->load(['orderDetails.addOns', 'payments']);

            if ($order->user_id === null) {
                $proof = $verifiedProof ? PaymentProof::whereKey($verifiedProof->id)->lockForUpdate()->first() : null;
                if ($paymentMethod !== 'gcash' || ! $proof || $proof->order_id !== $order->id
                    || $proof->status !== 'awaiting_verification'
                    || $proof->reference_number !== $this->normalizeReference($referenceNumber)) {
                    throw ValidationException::withMessages(['payment' => 'Review the buyer’s GCash proof to verify a public deposit.']);
                }
            }

            if ($order->status === 'cancelled') {
                throw ValidationException::withMessages([
                    'payment' => ['Cannot accept payment for a cancelled order.'],
                ]);
            }

            if ($order->status === 'completed') {
                throw ValidationException::withMessages([
                    'payment' => ['Order is already completed.'],
                ]);
            }

            if (! $order->canRecordDeposit()) {
                throw ValidationException::withMessages([
                    'status' => ['Staff must explicitly confirm this request before its deposit can be recorded.'],
                ]);
            }

            if ($order->hasDownPayment() || $order->amount_paid > 0) {
                throw ValidationException::withMessages([
                    'payment_type' => ['A down payment has already been recorded for this order.'],
                ]);
            }

            $requiredDeposit = $order->required_down_payment;
            if (! $this->sameAmount($amount, $requiredDeposit)) {
                throw ValidationException::withMessages([
                    'amount' => ['Down payment must be exactly 50% of order total (₱'.number_format($requiredDeposit, 2).').'],
                ]);
            }

            if ($paymentMethod === 'gcash' && empty($referenceNumber)) {
                throw ValidationException::withMessages([
                    'reference_number' => ['Reference number is required for GCash payments.'],
                ]);
            }

            $referenceNumber = $this->reserveReference($paymentMethod, $referenceNumber);
            $payment = Payment::create([
                'order_id' => $order->id,
                'user_id' => $user->id,
                'amount' => $amount,
                'payment_type' => 'down_payment',
                'payment_method' => $paymentMethod,
                'reference_number' => $referenceNumber,
                'payment_date' => $paymentDate ?? now(),
            ]);

            $this->assignReference($paymentMethod, $referenceNumber, $payment);
            if (isset($proof)) {
                $proof->update(['status' => 'verified', 'reviewed_by' => $user->id, 'reviewed_at' => now(), 'payment_id' => $payment->id]);
            }

            // Verification records money; the earlier feasibility decision is unchanged.
            $order->unsetRelation('payments');

            return $payment;
        });
    }

    /**
     * Settle the exact balance at actual pickup and complete in one transaction.
     */
    public function recordFinalPayment(
        Order $order,
        float $amount,
        string $paymentMethod,
        ?string $referenceNumber,
        ?User $user,
        ?Carbon $paymentDate = null,
        bool $pickupConfirmed = false
    ): Payment {
        $user = StaffAccess::requireOwner($user);

        return DB::transaction(function () use ($order, $amount, $paymentMethod, $referenceNumber, $user, $paymentDate, $pickupConfirmed) {
            $order = $this->lockOrder($order);
            $order->load(['orderDetails.addOns', 'payments']);

            if ($order->status === 'cancelled') {
                throw ValidationException::withMessages([
                    'payment' => ['Cannot accept payment for a cancelled order.'],
                ]);
            }

            if (! $this->hasVerifiedDownPayment($order)) {
                throw ValidationException::withMessages([
                    'payment_type' => ['The exact 50% down payment must be recorded before final payment.'],
                ]);
            }

            if ($order->status !== 'ready_for_pickup') {
                throw ValidationException::withMessages([
                    'status' => ['The remaining balance is collected only at actual pickup after Ready for pickup.'],
                ]);
            }
            if (! $pickupConfirmed) {
                throw ValidationException::withMessages(['pickup_confirmed' => 'Confirm actual collection and verification of the remaining payment.']);
            }

            $remaining = $order->remaining_balance;

            if ($remaining <= 0) {
                throw ValidationException::withMessages([
                    'amount' => ['Order is already fully paid.'],
                ]);
            }

            if (! $this->sameAmount($amount, $remaining)) {
                throw ValidationException::withMessages([
                    'amount' => ['Final payment must equal the exact remaining balance of ₱'.number_format($remaining, 2).'.'],
                ]);
            }

            if ($paymentMethod === 'gcash' && empty($referenceNumber)) {
                throw ValidationException::withMessages([
                    'reference_number' => ['Reference number is required for GCash payments.'],
                ]);
            }

            $referenceNumber = $this->reserveReference($paymentMethod, $referenceNumber);
            $payment = Payment::create([
                'order_id' => $order->id,
                'user_id' => $user->id,
                'amount' => $amount,
                'payment_type' => 'final_payment',
                'payment_method' => $paymentMethod,
                'reference_number' => $referenceNumber,
                'payment_date' => $paymentDate ?? now(),
            ]);
            $this->assignReference($paymentMethod, $referenceNumber, $payment);

            $order->unsetRelation('payments');
            $order->update(['status' => 'completed', 'completed_at' => $order->completed_at ?? now()]);

            return $payment;
        });
    }

    public function completePickup(Order $order, string $paymentMethod, ?string $referenceNumber, ?User $user, bool $pickupConfirmed = false): Order
    {
        $user = StaffAccess::requireOwner($user);
        if (! $pickupConfirmed) {
            throw ValidationException::withMessages(['pickup_confirmed' => 'Confirm the customer is collecting the order and the payment has been verified.']);
        }

        return DB::transaction(function () use ($order, $paymentMethod, $referenceNumber, $user) {
            $order = $this->lockOrder($order);
            $order->load(['orderDetails.addOns', 'payments']);
            if ($order->status !== 'ready_for_pickup') {
                throw ValidationException::withMessages(['status' => 'Only a Ready for pickup order can be collected.']);
            }
            if ($order->remaining_balance > 0) {
                $this->recordFinalPayment($order, $order->remaining_balance, $paymentMethod, $referenceNumber, $user, null, true);
            } else {
                // A fully paid legacy order must never be charged a second time.
                $this->updateStatus($order, 'completed', $user);
            }

            return $order->fresh(['payments']);
        });
    }

    /**
     * Update order status with lifecycle validations.
     */
    public function updateStatus(Order $order, string $newStatus, ?User $user): Order
    {
        $user = $this->requireStaffUser($user);

        return DB::transaction(function () use ($order, $newStatus, $user) {
            $order = $this->lockOrder($order);
            $order->load(['orderDetails.addOns', 'payments']);

            if ($order->status === 'cancelled') {
                throw ValidationException::withMessages([
                    'status' => ['Cancelled orders cannot change status.'],
                ]);
            }

            if ($newStatus === 'cancelled') {
                return $this->cancelOrder($order, $user);
            }

            if (! in_array($newStatus, self::STATUS_TRANSITIONS[$order->status] ?? [], true)) {
                throw ValidationException::withMessages([
                    'status' => ["Order cannot transition directly from {$order->status} to {$newStatus}."],
                ]);
            }

            if ($newStatus === 'confirmed') {
                throw ValidationException::withMessages([
                    'status' => ['Use Confirm request to review feasibility before payment.'],
                ]);
            }

            if ($newStatus === 'preparing' && ! $order->canStartPreparation()) {
                throw ValidationException::withMessages(['status' => 'Preparation requires staff confirmation and the verified exact 50% deposit.']);
            }

            if ($newStatus === 'completed') {
                if ($order->remaining_balance > 0) {
                    throw ValidationException::withMessages([
                        'status' => ['Order cannot be completed until the remaining balance is fully settled.'],
                    ]);
                }

                // Preserve original completed_at timestamp
                if ($order->completed_at === null) {
                    $order->completed_at = now();
                }
            }

            if ($newStatus === 'ready_for_pickup' && $order->ready_at === null) {
                $order->ready_at = now();
            }
            $order->status = $newStatus;
            $order->save();

            return $order;
        });
    }

    /**
     * Customer cancellation retains the verified booking deposit and its ledger.
     */
    public function cancelOrder(Order $order, ?User $user, bool $noFundsChecked = false): Order
    {
        StaffAccess::requireOwner($user);

        return DB::transaction(function () use ($order, $noFundsChecked) {
            $order = $this->lockOrder($order);
            if ($order->status === 'completed') {
                throw ValidationException::withMessages([
                    'status' => ['Completed orders cannot be cancelled.'],
                ]);
            }

            if ($order->status !== 'cancelled') {
                if ($order->hasVerifiedPayment() && (!$order->hasVerifiedDeposit() || $order->amount_paid !== $order->required_down_payment)) {
                    throw ValidationException::withMessages(['status' => 'Only unpaid orders or orders with the verified exact 50% deposit can be cancelled. Fully paid or irregular payment records require Owner reconciliation.']);
                }
                if ($order->amount_paid === 0.0 && $order->paymentProofs()->exists() && ! $noFundsChecked) {
                    throw ValidationException::withMessages(['no_funds_checked' => 'Investigate the reported transfer in the business account before closing an unpaid request.']);
                }
                // Preserve original cancelled_at timestamp
                if ($order->cancelled_at === null) {
                    $order->cancelled_at = now();
                }
                $order->status = 'cancelled';
                $order->cancellation_kind = 'customer';
                $order->save();
            }

            return $order;
        });
    }

    protected function generateOrderNumber(): string
    {
        $date = now()->format('Ymd');
        $random = strtoupper(bin2hex(random_bytes(5)));

        return "ORD-{$date}-{$random}";
    }

    /**
     * A staff action must always be attributable to an authenticated Owner or
     * Assistant record. Public order creation is the only intentional null
     * creator path.
     */
    private function requireStaffUser(?User $user): User
    {
        return StaffAccess::require($user);
    }

    private function lockOrder(Order $order): Order
    {
        $locked = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
        $order->setRawAttributes($locked->getAttributes(), true);
        $order->unsetRelations();

        return $order;
    }

    public function normalizeReference(?string $reference): string
    {
        return strtoupper(preg_replace('/\s+/', '', trim($reference ?? '')));
    }

    private function reserveReference(string $method, ?string $reference): ?string
    {
        if (! in_array($method, ['cash', 'gcash'], true)) {
            throw ValidationException::withMessages(['payment_method' => 'Choose Cash or GCash.']);
        }
        if ($method === 'cash') {
            return null;
        }
        $reference = $this->normalizeReference($reference);
        if ($reference === '' || strlen($reference) > 100) {
            throw ValidationException::withMessages(['reference_number' => 'Enter the GCash transaction reference (up to 100 characters).']);
        }
        if (! DB::table('gcash_references')->insertOrIgnore(['reference_number' => $reference])) {
            throw ValidationException::withMessages(['reference_number' => 'This GCash transaction reference has already been recorded.']);
        }

        return $reference;
    }

    private function assignReference(string $method, ?string $reference, Payment $payment): void
    {
        if ($method === 'gcash') {
            DB::table('gcash_references')->where('reference_number', $reference)->update(['payment_id' => $payment->id]);
        }
    }

    /**
     * Verify that exactly one recorded down payment equals half of the current
     * reviewed order total. This protects the status transition even if a
     * caller attempts to bypass payment recording methods.
     */
    private function hasVerifiedDownPayment(Order $order): bool
    {
        $downPayments = $order->payments->where('payment_type', 'down_payment');

        return $downPayments->count() === 1
            && $this->sameAmount((float) $downPayments->first()->amount, $order->required_down_payment);
    }

    /**
     * Compare currency values as whole centavos, rejecting fractional-centavo
     * input rather than accepting an amount that the database would round.
     */
    private function sameAmount(float $actual, float $expected): bool
    {
        $actualCentavos = round($actual * 100);
        $expectedCentavos = round($expected * 100);

        return abs(($actual * 100) - $actualCentavos) < 0.00001
            && abs(($expected * 100) - $expectedCentavos) < 0.00001
            && (int) $actualCentavos === (int) $expectedCentavos;
    }
}
