<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Supply extends Model
{
    use HasFactory;

    protected $fillable = [
        'supply_name',
        'category',
        'unit',
        'current_quantity',
        'reorder_level',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'current_quantity' => 'decimal:2',
            'reorder_level' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function inventoryTransactions(): HasMany
    {
        return $this->hasMany(InventoryTransaction::class, 'supply_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeLowStock(Builder $query): Builder
    {
        return $query->whereRaw(self::usableSql().' <= supplies.reorder_level', [\App\Support\InventoryCalendar::date()]);
    }

    public function stockEntries(): HasMany { return $this->hasMany(StockEntry::class); }

    public static function usableSql(): string
    {
        return "COALESCE((SELECT SUM(se.remaining_quantity) FROM stock_entries se WHERE se.supply_id = supplies.id AND (supplies.category = 'packaging' OR se.expiry_date >= ?)), 0)";
    }

    public function scopeWithUsableQuantity(Builder $query): Builder
    {
        return $query->addSelect('supplies.*')->selectRaw(self::usableSql().' AS available_quantity', [\App\Support\InventoryCalendar::date()]);
    }

    public function getUsableQuantityAttribute(): float
    {
        if (array_key_exists('available_quantity', $this->attributes)) return (float)$this->attributes['available_quantity'];
        if ($this->relationLoaded('stockEntries')) return (float)$this->stockEntries->filter(fn ($e) => $this->category === 'packaging' || ($e->expiry_date && $e->expiry_date->toDateString() >= \App\Support\InventoryCalendar::date()))->sum('remaining_quantity');
        $query = $this->stockEntries();
        if ($this->category === 'ingredients') $query->where('expiry_date', '>=', \App\Support\InventoryCalendar::date());
        return (float)$query->sum('remaining_quantity');
    }

    /**
     * Low stock accessor.
     */
    protected function isLowStock(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->usable_quantity <= (float) $this->reorder_level
        );
    }
}
