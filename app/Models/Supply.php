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
        return $query->whereColumn('current_quantity', '<=', 'reorder_level');
    }

    /**
     * Low stock accessor.
     */
    protected function isLowStock(): Attribute
    {
        return Attribute::make(
            get: fn () => (float) $this->current_quantity <= (float) $this->reorder_level
        );
    }
}
