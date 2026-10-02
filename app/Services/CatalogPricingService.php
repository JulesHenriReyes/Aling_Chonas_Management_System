<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Validation\ValidationException;

class CatalogPricingService
{
    /**
     * Both order-entry paths use the same catalog quote. Client prices, names,
     * layer counts and included contents are never used to price an order.
     * Call within a transaction when saving so catalog edits cannot race it.
     */
    public function quote(array $items): array
    {
        if (!$items || count($items) > 50) {
            $this->invalid('items', 'Select between 1 and 50 package lines.');
        }

        $lines = [];
        $totalCentavos = 0;
        foreach ($items as $index => $item) {
            $key = "items.{$index}";
            $quantity = $this->quantity($item['quantity'] ?? null, "{$key}.quantity");
            $product = Product::whereKey($item['product_id'] ?? null)->lockForUpdate()->first();
            if (!$product || !$product->is_active) {
                $this->invalid("{$key}.product_id", 'Choose an available cake package.');
            }
            $option = $product->options()->whereKey($item['package_option_id'] ?? null)->lockForUpdate()->first();
            if (!$option || !$option->is_active) {
                $this->invalid("{$key}.package_option_id", 'Choose an available layer option for this package.');
            }

            $priceCentavos = (int) round((float) $option->price * 100);
            if ($priceCentavos <= 0) {
                $this->invalid("{$key}.package_option_id", 'This package option needs a fixed price before ordering.');
            }
            $line = [
                'product_id' => $product->id,
                'package_option_id' => $option->id,
                'product_name_snapshot' => $product->product_name,
                'included_contents_snapshot' => $option->included_contents,
                'included_items_snapshot' => $option->includedItems()->lockForUpdate()->get()->map(fn ($included) => [
                    'add_on_id' => $included->id,
                    'name' => $included->name,
                    'description' => $included->description,
                    'quantity' => (int) $included->pivot->quantity,
                ])->all(),
                'quantity' => $quantity,
                'unit_price' => $priceCentavos / 100,
                'layers' => $option->layers,
                'themes' => $item['themes'] ?? null,
                'special_request' => $item['special_request'] ?? null,
                'add_ons' => [],
            ];
            $totalCentavos += $quantity * $priceCentavos;
            $extras = $item['add_ons'] ?? [];
            if (!is_array($extras) || count($extras) > 50) {
                $this->invalid("{$key}.add_ons", 'Choose up to 50 applicable add-ons.');
            }
            $seen = [];
            foreach ($extras as $extraIndex => $extra) {
                $extraKey = "{$key}.add_ons.{$extraIndex}";
                $extraQuantity = $this->quantity($extra['quantity'] ?? null, "{$extraKey}.quantity");
                $extraId = $extra['add_on_id'] ?? null;
                $addOn = $product->addOns()->where('add_ons.id', $extraId)->lockForUpdate()->first();
                if (!$addOn || !$addOn->is_active || in_array($addOn->id, $seen, true)) {
                    $this->invalid("{$extraKey}.add_on_id", 'Choose each available, applicable add-on once.');
                }
                $seen[] = $addOn->id;
                $extraPriceCentavos = (int) round((float) $addOn->price * 100);
                if ($extraPriceCentavos <= 0) {
                    $this->invalid("{$extraKey}.add_on_id", 'This add-on needs a fixed price before ordering.');
                }
                $line['add_ons'][] = [
                    'add_on_id' => $addOn->id,
                    'name_snapshot' => $addOn->name,
                    'description_snapshot' => $addOn->description,
                    'quantity' => $extraQuantity,
                    'unit_price' => $extraPriceCentavos / 100,
                ];
                $totalCentavos += $extraQuantity * $extraPriceCentavos;
            }
            $lines[] = $line;
        }
        if ($totalCentavos > 9999999999) {
            $this->invalid('items', 'The order total is too large. Reduce the quantities.');
        }
        // Currency has no half-centavo. Reject odd-centavo totals rather than
        // silently charging a deposit other than an exact half of the total.
        if ($totalCentavos % 2 !== 0) {
            $this->invalid('items', 'The total must allow an exact 50% deposit in centavos. Please contact the bakery to correct the catalog price.');
        }

        return ['lines' => $lines, 'total' => $totalCentavos / 100, 'deposit' => $totalCentavos / 200];
    }

    private function quantity(mixed $value, string $key): int
    {
        if (filter_var($value, FILTER_VALIDATE_INT) === false || $value < 1 || $value > 999) {
            $this->invalid($key, 'Quantity must be a whole number from 1 to 999.');
        }

        return (int) $value;
    }

    private function invalid(string $key, string $message): never
    {
        throw ValidationException::withMessages([$key => $message]);
    }
}
