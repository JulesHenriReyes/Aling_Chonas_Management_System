<?php

namespace Tests\Concerns;

use App\Models\Product;

trait CreatesCatalogFixtures
{
    /** Explicit, priced package option for regression fixtures. */
    private function catalogProduct(array $attributes): Product
    {
        $product = Product::create($attributes);
        $product->options()->create([
            'layers' => 2,
            'included_contents' => 'Two-layer cake and 8 included cupcakes',
            'price' => $attributes['price'],
            'is_active' => true,
        ]);

        return $product;
    }
}
