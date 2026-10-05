<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentProof extends Model
{
    protected $attributes = ['status' => 'awaiting_verification'];
    protected $fillable = ['order_id', 'file_path', 'reference_number', 'status', 'reviewed_by', 'reviewed_at', 'rejection_reason', 'payment_id'];
    protected $hidden = ['file_path', 'order_receipt_key'];

    protected static function booted(): void
    {
        // Covers lazy/eager relations, queue queries, route binding and review actions.
        static::addGlobalScope('current_order', function (Builder $query) {
            $query->whereHas('order', fn (Builder $orders) => $orders
                ->whereColumn('orders.receipt_key', 'payment_proofs.order_receipt_key'));
        });

        static::creating(function (PaymentProof $proof) {
            $proof->order_receipt_key = Order::findOrFail($proof->order_id)->receipt_key;
            if (! $proof->order_receipt_key) {
                throw new \LogicException('The order needs a receipt identity before accepting a receipt.');
            }
        });
    }

    protected function casts(): array
    {
        return ['reviewed_at' => 'datetime'];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
