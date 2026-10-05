<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_number',
        'customer_id',
        'user_id',
        'status',
        'pickup_date',
        'pickup_time',
        'completed_at',
        'cancelled_at',
        'notes_text',
        'private_token',
        'submission_key',
        'fixed_catalog_pricing',
        'ready_at',
        'cancellation_kind',
        'cancellation_reason',
    ];

    protected $hidden = ['private_token', 'receipt_key'];

    protected static function booted(): void
    {
        static::creating(function (Order $order) {
            // A reset can recycle numeric IDs; receipt ownership must not recycle.
            $order->receipt_key = (string) Str::uuid();
        });
    }

    protected function casts(): array
    {
        return [
            'pickup_date' => 'date',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'ready_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'fixed_catalog_pricing' => 'boolean',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function hasApprovedReview(): bool
    {
        return $this->review_status === 'approved' && $this->reviewed_by !== null && $this->reviewed_at !== null;
    }

    public function canRecordDeposit(): bool
    {
        return $this->status === 'confirmed' && $this->hasApprovedReview()
            && $this->amount_paid === 0.0 && ! $this->hasDownPayment();
    }

    public function canSubmitReceipt(): bool
    {
        return $this->user_id === null && $this->canRecordDeposit()
            && ! $this->hasAwaitingReceipt();
    }

    public function needsStaffReview(): bool
    {
        return $this->amount_paid === 0.0 && ($this->status === 'pending'
            || ($this->status === 'confirmed' && $this->review_status === null));
    }

    public function hasVerifiedDeposit(): bool
    {
        $deposits = $this->relationLoaded('payments') ? $this->payments->where('payment_type', 'down_payment')
            : $this->payments()->where('payment_type', 'down_payment')->get();

        return $deposits->count() === 1 && $this->required_down_payment > 0
            && (int) round((float) $deposits->first()->amount * 100) === (int) round($this->required_down_payment * 100);
    }

    public function canStartPreparation(): bool
    {
        return $this->status === 'confirmed'
            && ($this->hasApprovedReview() || $this->review_status === null)
            && ($this->hasVerifiedDeposit() || ($this->review_status === null && $this->total_amount > 0
                && $this->payment_status === 'fully_paid'));
    }

    public function workflowLabel(): string
    {
        if ($this->status === 'cancelled' && $this->cancellation_kind === 'staff_rejected') {
            return 'Request declined';
        }
        if ($this->needsStaffReview()) {
            return $this->hasReportedTransfer() ? 'Staff review — reported payment needs checking' : 'Awaiting staff confirmation';
        }
        if ($this->status === 'confirmed' && $this->amount_paid === 0.0) {
            return $this->hasAwaitingReceipt()
                ? 'Receipt awaiting verification' : 'Confirmed — awaiting deposit';
        }
        if ($this->status === 'confirmed') {
            return $this->review_status === null ? 'Previously confirmed' : 'Deposit verified — booking secured';
        }

        return ucfirst(str_replace('_', ' ', $this->status));
    }

    public function scopeWorkflowQueue(Builder $query, string $queue): Builder
    {
        $unpaid = fn (Builder $q) => $q->whereDoesntHave('payments', fn (Builder $p) => $p->where('amount', '>', 0));
        return match ($queue) {
            'review' => $query->where(fn (Builder $q) => $q->where('status', 'pending')->orWhere(fn (Builder $legacy) =>
                $legacy->where('status', 'confirmed')->whereNull('review_status')->where($unpaid))),
            'deposit' => $query->where('status', 'confirmed')->where('review_status', 'approved')->where($unpaid)
                ->whereDoesntHave('paymentProofs', fn (Builder $p) => $p->where('status', 'awaiting_verification')),
            'receipts' => $query->where('status', 'confirmed')->where('review_status', 'approved')->where($unpaid)
                ->whereHas('paymentProofs', fn (Builder $p) => $p->where('status', 'awaiting_verification')),
            'booked' => $query->whereIn('status', ['confirmed', 'preparing', 'ready_for_pickup'])
                ->whereHas('payments', fn (Builder $p) => $p->where('amount', '>', 0)),
            default => $query,
        };
    }

    public function orderDetails(): HasMany
    {
        return $this->hasMany(OrderDetail::class, 'order_id');
    }

    public function images(): HasMany
    {
        return $this->hasMany(OrderImage::class, 'order_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'order_id');
    }

    public function paymentProofs(): HasMany
    {
        return $this->hasMany(PaymentProof::class)->latest('id');
    }

    public function refund(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Refund::class);
    }

    public function pickupDeadline(): \Carbon\Carbon
    {
        return \Carbon\Carbon::parse($this->pickup_date->toDateString().' '.$this->pickup_time, config('bakery.pickup_timezone'));
    }

    /**
     * Calculate total order amount dynamically from order_details.
     */
    protected function totalAmount(): Attribute
    {
        return Attribute::make(
            get: function () {
                if ($this->relationLoaded('orderDetails')) {
                    return round((float) $this->orderDetails->sum(fn ($detail) => $detail->subtotal), 2);
                }

                $total = $this->orderDetails()
                    ->selectRaw('COALESCE(SUM(quantity * unit_price), 0) as total')
                    ->value('total');

                $extras = OrderAddOn::whereIn('order_detail_id', $this->orderDetails()->select('id'))
                    ->selectRaw('COALESCE(SUM(quantity * unit_price), 0) as total')->value('total');

                return round((float) $total + (float) $extras, 2);
            }
        );
    }

    /**
     * Calculate amount paid dynamically from payments.
     */
    protected function amountPaid(): Attribute
    {
        return Attribute::make(
            get: function () {
                if ($this->relationLoaded('payments')) {
                    return round((float) $this->payments->sum('amount'), 2);
                }

                $paid = $this->payments()->sum('amount');

                return round((float) $paid, 2);
            }
        );
    }

    /**
     * Exact 50% required down payment.
     */
    protected function requiredDownPayment(): Attribute
    {
        return Attribute::make(
            get: fn () => round($this->total_amount * 0.50, 2)
        );
    }

    /**
     * Calculate remaining balance.
     */
    protected function remainingBalance(): Attribute
    {
        return Attribute::make(
            get: fn () => max(0.00, round($this->total_amount - $this->amount_paid, 2))
        );
    }

    /**
     * Derive payment status dynamically (unpaid, partially_paid, fully_paid).
     */
    protected function paymentStatus(): Attribute
    {
        return Attribute::make(
            get: function () {
                $paid = $this->amount_paid;
                $total = $this->total_amount;

                if ($paid <= 0) {
                    return 'unpaid';
                }

                if ($total > 0 && $paid >= $total) {
                    return 'fully_paid';
                }

                return 'partially_paid';
            }
        );
    }

    /**
     * Check if a down payment transaction already exists.
     */
    public function hasDownPayment(): bool
    {
        return $this->relationLoaded('payments') ? $this->payments->contains('payment_type', 'down_payment')
            : $this->payments()->where('payment_type', 'down_payment')->exists();
    }

    public function hasReportedTransfer(): bool
    {
        return $this->relationLoaded('paymentProofs') ? $this->paymentProofs->isNotEmpty() : $this->paymentProofs()->exists();
    }

    public function hasAwaitingReceipt(): bool
    {
        return $this->relationLoaded('paymentProofs') ? $this->paymentProofs->contains('status', 'awaiting_verification')
            : $this->paymentProofs()->where('status', 'awaiting_verification')->exists();
    }
}
