<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'supply_id',
        'user_id',
        'transaction_type',
        'quantity',
        'transaction_date',
        'notes',
        'inventory_operation_id', 'quantity_before', 'quantity_after', 'unit', 'reversal_of_id',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'quantity_before' => 'decimal:2',
            'quantity_after' => 'decimal:2',
            'transaction_date' => 'datetime',
        ];
    }

    public function operation(): BelongsTo
    {
        return $this->belongsTo(InventoryOperation::class, 'inventory_operation_id');
    }

    public function reversal() { return $this->hasOne(self::class, 'reversal_of_id'); }

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Post a linked reversal to correct a movement.'));
        static::deleting(fn () => throw new \LogicException('Movement history cannot be deleted.'));
    }

    public function supply(): BelongsTo
    {
        return $this->belongsTo(Supply::class, 'supply_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
