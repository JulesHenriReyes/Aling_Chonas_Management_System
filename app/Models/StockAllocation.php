<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockAllocation extends Model
{
    protected $guarded = ['id'];
    protected function casts(): array { return ['quantity' => 'decimal:2', 'quantity_before' => 'decimal:2', 'quantity_after' => 'decimal:2']; }
    public function entry() { return $this->belongsTo(StockEntry::class, 'stock_entry_id'); }
    public function movement() { return $this->belongsTo(InventoryTransaction::class, 'inventory_transaction_id'); }
    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Stock allocation history is immutable.'));
        static::deleting(fn () => throw new \LogicException('Stock allocation history cannot be deleted.'));
    }
}
