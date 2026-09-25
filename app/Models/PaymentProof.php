<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentProof extends Model
{
    protected $attributes = ['status' => 'awaiting_verification'];
    protected $fillable = ['order_id', 'file_path', 'reference_number', 'status', 'reviewed_by', 'reviewed_at', 'rejection_reason', 'payment_id'];
    protected $hidden = ['file_path'];

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
