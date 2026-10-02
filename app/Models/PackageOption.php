<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class PackageOption extends Model
{
    protected $fillable = ['product_id', 'layers', 'included_contents', 'price', 'is_active'];

    protected function casts(): array
    {
        return ['layers' => 'integer', 'price' => 'decimal:2', 'is_active' => 'boolean'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function includedItems(): BelongsToMany
    {
        return $this->belongsToMany(AddOn::class, 'package_option_inclusions')->withPivot('quantity')->orderBy('add_ons.id');
    }
}
