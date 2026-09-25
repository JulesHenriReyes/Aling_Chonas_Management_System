<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrderDetail extends Model
{
    use HasFactory;

    protected $with = ['addOns'];

    protected $fillable = [
        'order_id',
        'product_id',
        'quantity',
        'unit_price',
        'layers',
        'themes',
        'special_request',
        'package_option_id',
        'product_name_snapshot',
        'included_contents_snapshot',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price' => 'decimal:2',
            'layers' => 'integer',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function images(): HasMany
    {
        return $this->hasMany(OrderImage::class, 'order_detail_id');
    }

    public function addOns(): HasMany
    {
        return $this->hasMany(OrderAddOn::class);
    }

    /**
     * Subtotal calculated from quantity * unit_price.
     */
    protected function subtotal(): Attribute
    {
        return Attribute::make(
            get: fn () => round((float) ($this->quantity * $this->unit_price) + $this->addOns->sum('subtotal'), 2)
        );
    }
}
