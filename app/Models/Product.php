<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        // Keep the existing pivot-based pricing and public catalog paths intact.
        static::created(function (Product $product) {
            if (!\Illuminate\Support\Facades\Schema::hasColumn('add_ons', 'all_packages')) {
                return; // Also support historical migration rehearsals.
            }
            $product->addOns()->syncWithoutDetaching(AddOn::where('all_packages', true)->pluck('id')->all());
        });
    }

    protected $fillable = [
        'product_name',
        'price',
        'is_active',
        'description',
        'photo_path',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function orderDetails(): HasMany
    {
        return $this->hasMany(OrderDetail::class, 'product_id');
    }

    public function options(): HasMany
    {
        return $this->hasMany(PackageOption::class)->orderBy('layers');
    }

    public function addOns(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(AddOn::class);
    }
}
