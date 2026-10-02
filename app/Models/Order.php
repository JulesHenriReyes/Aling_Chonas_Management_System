<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

    protected $hidden = ['private_token'];

    protected function casts(): array
    {
        return [
            'pickup_date' => 'date',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'ready_at' => 'datetime',
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
        return $this->payments()->where('payment_type', 'down_payment')->exists();
    }
}
