<?php

namespace App\Models;

use App\Support\InventoryCalendar;
use Illuminate\Database\Eloquent\Model;

class StockEntry extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['stock_in_date' => 'date', 'expiry_date' => 'date', 'opening_quantity' => 'decimal:2', 'remaining_quantity' => 'decimal:2'];
    }

    public function supply() { return $this->belongsTo(Supply::class); }
    public function allocations() { return $this->hasMany(StockAllocation::class); }
    public function parentEntry() { return $this->belongsTo(self::class, 'parent_entry_id'); }

    public function getExpiryStatusAttribute(): string
    {
        if ($this->supply->category === 'packaging') return 'not_applicable';
        if (!$this->expiry_date) return 'unknown';
        if ($this->expiry_date->toDateString() < InventoryCalendar::date()) return 'expired';
        if ($this->expiry_date->toDateString() <= InventoryCalendar::today()->addDays(config('inventory.expiry_warning_days', 7))->toDateString()) return 'expiring';
        return 'available';
    }

    protected static function booted(): void
    {
        static::updating(function ($entry) {
            if ($entry->isDirty(['supply_id', 'source', 'stock_in_date', 'expiry_date', 'opening_quantity', 'parent_entry_id'])) {
                throw new \LogicException('Stock entry dates and origins are immutable. Post a linked correction.');
            }
        });
        static::deleting(fn () => throw new \LogicException('Stock entry history cannot be deleted.'));
    }
}
