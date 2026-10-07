<?php

namespace App\Http\Requests;

use App\Support\PickupCalendar;
use App\Rules\PickupTime;

class CatalogOrderRules
{
    public static function items(): array
    {
        return [
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.package_option_id' => ['required', 'integer', 'exists:package_options,id'],
            'items.*.quantity' => ['required', 'integer', 'between:1,999'],
            'items.*.themes' => ['nullable', 'string', 'max:255'],
            'items.*.special_request' => ['nullable', 'string', 'max:1000'],
            'items.*.add_ons' => ['nullable', 'array', 'max:50'],
            'items.*.add_ons.*.add_on_id' => ['required', 'integer', 'exists:add_ons,id'],
            'items.*.add_ons.*.quantity' => ['required', 'integer', 'between:1,999'],
            'items.*.images' => ['nullable', 'array', 'max:5'],
            'items.*.images.*' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ];
    }

    public static function order(): array
    {
        return self::items() + [
            'expected_total' => ['required', 'numeric', 'min:0.02'],
            'pickup_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:'.PickupCalendar::todayString()],
            'pickup_time' => ['required', new PickupTime],
            'notes_text' => ['nullable', 'string', 'max:1000'],
            'images' => ['nullable', 'array', 'max:5'],
            'images.*' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ];
    }
}
