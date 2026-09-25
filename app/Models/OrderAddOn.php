<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

class OrderAddOn extends Model
{
    protected $fillable = ['order_detail_id', 'add_on_id', 'name_snapshot', 'description_snapshot', 'quantity', 'unit_price'];

    protected function casts(): array
    {
        return ['quantity' => 'integer', 'unit_price' => 'decimal:2'];
    }

    protected function subtotal(): Attribute
    {
        return Attribute::make(get: fn () => round($this->quantity * (float) $this->unit_price, 2));
    }
}
