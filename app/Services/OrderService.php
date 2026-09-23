<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
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
        return $this->executeOrderCreation($data, $this->requireStaffUser($user));
    }

    /**
     * Execute transactional order creation.
     */
    private function executeOrderCreation(array $data, ?User $user): Order
    {
        return DB::transaction(function () use ($data, $user) {
            if (empty($data['items']) || !is_array($data['items'])) {
                throw ValidationException::withMessages([
                    'items' => ['At least one product line item is required.'],
                ]);
            }

            // Pickup date cannot be in the past
            $pickupDate = Carbon::parse($data['pickup_date'])->startOfDay();
            if ($pickupDate->isPast() && !$pickupDate->isToday()) {
                throw ValidationException::withMessages([
                    'pickup_date' => ['Pickup date cannot be in the past.'],
                ]);
            }

            $orderNumber = $this->generateOrderNumber();

            $order = Order::create([
                'order_number' => $orderNumber,
                'customer_id' => $data['customer_id'],
                'user_id' => $user?->id,
                'status' => 'pending',
                'pickup_date' => $data['pickup_date'],
                'pickup_time' => $data['pickup_time'],
                'notes_text' => $data['notes_text'] ?? null,
            ]);

            foreach ($data['items'] as $item) {
                if (!isset($item['quantity']) || (int) $item['quantity'] <= 0) {
                    throw ValidationException::withMessages([
                        'quantity' => ['Each selected product must have a quantity greater than zero.'],
                    ]);
                }

                $product = Product::findOrFail($item['product_id']);
                if ($user === null && !$product->is_active) {
                    throw ValidationException::withMessages([
                        'items' => ['Public orders may only contain active products.'],
                    ]);
                }
                $unitPrice = isset($item['unit_price']) && is_numeric($item['unit_price'])
                    ? (float) $item['unit_price']
                    : (float) $product->price;

                $orderDetail = OrderDetail::create([
                    'order_id' => $order->id,
                    'product_id' => $product->id,
                    'quantity' => (int) $item['quantity'],
                    'unit_price' => $unitPrice,
                    'layers' => isset($item['layers']) && $item['layers'] !== '' ? (int) $item['layers'] : null,
                    'themes' => $item['themes'] ?? null,
                    'special_request' => $item['special_request'] ?? null,
                ]);

                if (!empty($item['images']) && is_array($item['images'])) {
                    foreach ($item['images'] as $imageFile) {
                        $this->saveOrderDetailImage($order, $orderDetail, $imageFile, $user);
                    }
                }
            }

            if (!empty($data['images']) && is_array($data['images'])) {
                foreach ($data['images'] as $imageFile) {
                    $this->saveOrderDetailImage($order, null, $imageFile, $user);
                }
            }

            return $order->load(['customer', 'orderDetails.product', 'orderDetails.images', 'images', 'payments']);
        });
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
    ): \App\Models\OrderImage {
        if ($orderDetail !== null && (int) $orderDetail->order_id !== (int) $order->id) {
            throw ValidationException::withMessages([
                'order_detail_id' => ['The specified order detail does not belong to this order.'],
            ]);
        }

        return \App\Models\OrderImage::create([
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
    protected function saveOrderDetailImage(Order $order, ?OrderDetail $orderDetail, $imageFile, ?User $user): \App\Models\OrderImage
    {
        if ($imageFile instanceof \Illuminate\Http\UploadedFile) {
            $path = $imageFile->store("order_images/{$order->id}", 'public');
            $filename = $imageFile->getClientOriginalName();
        } elseif (is_array($imageFile)) {
            $path = $imageFile['file_path'];
            $filename = $imageFile['original_filename'];
        } else {
            $path = (string) $imageFile;
            $filename = basename($path);
        }

        return $this->attachImage($order, $path, $filename, $orderDetail, $user);
    }

    /**
     * Staff can revise unit_price on a pending order.
     * Locked once confirmed.
     */
    public function updateOrderDetailPrice(Order $order, OrderDetail $orderDetail, float $newPrice, ?User $user): OrderDetail
    {
        $this->requireStaffUser($user);

        return DB::transaction(function () use ($order, $orderDetail, $newPrice) {
            if ((int) $orderDetail->order_id !== (int) $order->id) {
                throw ValidationException::withMessages([
                    'order_detail' => ['The specified order detail does not belong to this order.'],
                ]);
            }

            if ($order->status !== 'pending') {
                throw ValidationException::withMessages([
                    'status' => ['Unit prices can only be adjusted while the order is in pending status.'],
                ]);
            }

            if ($newPrice < 0) {
                throw ValidationException::withMessages([
                    'unit_price' => ['Unit price cannot be negative.'],
                ]);
            }

            $orderDetail->unit_price = round($newPrice, 2);
            $orderDetail->save();

            $order->load(['orderDetails', 'payments']);

            return $orderDetail;
        });
    }

    /**
     * Record exactly 50% down payment and transition order to 'confirmed'.
     */
    public function recordDownPayment(
        Order $order,
        float $amount,
        string $paymentMethod,
        ?string $referenceNumber,
        ?User $user,
        ?Carbon $paymentDate = null
    ): Payment {
        $user = $this->requireStaffUser($user);

        return DB::transaction(function () use ($order, $amount, $paymentMethod, $referenceNumber, $user, $paymentDate) {
            // Reload relationships to ensure fresh totals
            $order->load(['orderDetails', 'payments']);

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

            if ($order->status !== 'pending') {
                throw ValidationException::withMessages([
                    'status' => ['A down payment can only be recorded while an order is pending review.'],
                ]);
            }

            if ($order->hasDownPayment()) {
                throw ValidationException::withMessages([
                    'payment_type' => ['A down payment has already been recorded for this order.'],
                ]);
            }

            $requiredDeposit = $order->required_down_payment;
            if (!$this->sameAmount($amount, $requiredDeposit)) {
                throw ValidationException::withMessages([
                    'amount' => ["Down payment must be exactly 50% of order total (₱" . number_format($requiredDeposit, 2) . ")."],
                ]);
            }

            if ($paymentMethod === 'gcash' && empty($referenceNumber)) {
                throw ValidationException::withMessages([
                    'reference_number' => ['Reference number is required for GCash payments.'],
                ]);
            }

            $payment = Payment::create([
                'order_id' => $order->id,
                'user_id' => $user->id,
                'amount' => $amount,
                'payment_type' => 'down_payment',
                'payment_method' => $paymentMethod,
                'reference_number' => $referenceNumber,
                'payment_date' => $paymentDate ?? now(),
            ]);

            // A verified 50% deposit is the only valid pending -> confirmed transition.
            $order->update(['status' => 'confirmed']);

            return $payment;
        });
    }

    /**
     * Record final payment to settle the remaining balance.
     */
    public function recordFinalPayment(
        Order $order,
        float $amount,
        string $paymentMethod,
        ?string $referenceNumber,
        ?User $user,
        ?Carbon $paymentDate = null
    ): Payment {
        $user = $this->requireStaffUser($user);

        return DB::transaction(function () use ($order, $amount, $paymentMethod, $referenceNumber, $user, $paymentDate) {
            $order->load(['orderDetails', 'payments']);

            if ($order->status === 'cancelled') {
                throw ValidationException::withMessages([
                    'payment' => ['Cannot accept payment for a cancelled order.'],
                ]);
            }

            if (!$this->hasVerifiedDownPayment($order)) {
                throw ValidationException::withMessages([
                    'payment_type' => ['The exact 50% down payment must be recorded before final payment.'],
                ]);
            }

            if (!in_array($order->status, ['confirmed', 'preparing', 'ready_for_pickup'], true)) {
                throw ValidationException::withMessages([
                    'status' => ['Final payment can only be recorded for an active confirmed order.'],
                ]);
            }

            $remaining = $order->remaining_balance;

            if ($remaining <= 0) {
                throw ValidationException::withMessages([
                    'amount' => ['Order is already fully paid.'],
                ]);
            }

            if (!$this->sameAmount($amount, $remaining)) {
                throw ValidationException::withMessages([
                    'amount' => ["Final payment must equal the exact remaining balance of ₱" . number_format($remaining, 2) . "."],
                ]);
            }

            if ($paymentMethod === 'gcash' && empty($referenceNumber)) {
                throw ValidationException::withMessages([
                    'reference_number' => ['Reference number is required for GCash payments.'],
                ]);
            }

            return Payment::create([
                'order_id' => $order->id,
                'user_id' => $user->id,
                'amount' => $amount,
                'payment_type' => 'final_payment',
                'payment_method' => $paymentMethod,
                'reference_number' => $referenceNumber,
                'payment_date' => $paymentDate ?? now(),
            ]);
        });
    }

    /**
     * Update order status with lifecycle validations.
     */
    public function updateStatus(Order $order, string $newStatus, ?User $user): Order
    {
        $user = $this->requireStaffUser($user);

        return DB::transaction(function () use ($order, $newStatus, $user) {
            $order->load(['orderDetails', 'payments']);

            if ($order->status === 'cancelled') {
                throw ValidationException::withMessages([
                    'status' => ['Cancelled orders cannot change status.'],
                ]);
            }

            if ($newStatus === 'cancelled') {
                return $this->cancelOrder($order, $user);
            }

            if (!in_array($newStatus, self::STATUS_TRANSITIONS[$order->status] ?? [], true)) {
                throw ValidationException::withMessages([
                    'status' => ["Order cannot transition directly from {$order->status} to {$newStatus}."],
                ]);
            }

            if ($newStatus === 'confirmed' && !$this->hasVerifiedDownPayment($order)) {
                throw ValidationException::withMessages([
                    'status' => ['Order cannot be confirmed without the exact verified 50% down payment.'],
                ]);
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

            $order->status = $newStatus;
            $order->save();

            return $order;
        });
    }

    /**
     * Cancel an order. Preserves payments as non-refundable deposit.
     */
    public function cancelOrder(Order $order, ?User $user): Order
    {
        $this->requireStaffUser($user);

        return DB::transaction(function () use ($order) {
            if ($order->status === 'completed') {
                throw ValidationException::withMessages([
                    'status' => ['Completed orders cannot be cancelled.'],
                ]);
            }

            if ($order->status !== 'cancelled') {
                // Preserve original cancelled_at timestamp
                if ($order->cancelled_at === null) {
                    $order->cancelled_at = now();
                }
                $order->status = 'cancelled';
                $order->save();
            }

            return $order;
        });
    }

    protected function generateOrderNumber(): string
    {
        $date = now()->format('Ymd');
        $random = strtoupper(substr(uniqid(), -4));
        return "ORD-{$date}-{$random}";
    }

    /**
     * A staff action must always be attributable to an authenticated Owner or
     * Assistant record. Public order creation is the only intentional null
     * creator path.
     */
    private function requireStaffUser(?User $user): User
    {
        if ($user === null || !$user->exists || !$user->id || !in_array($user->role, ['owner', 'assistant'], true)) {
            throw ValidationException::withMessages([
                'user' => ['This action requires an authenticated Owner or Assistant user.'],
            ]);
        }

        return $user;
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
