<?php

return [
    'expiry_warning_days' => 7,
    // Suggestions for new ingredients; existing stock keeps its recorded unit.
    'ingredient_presets' => [
        ['supply_name' => 'Flour', 'unit' => 'kg'],
        ['supply_name' => 'Sugar', 'unit' => 'kg'],
        ['supply_name' => 'Cocoa', 'unit' => 'kg'],
        ['supply_name' => 'Egg', 'unit' => 'piece'],
        ['supply_name' => 'Evaporated milk', 'unit' => 'can'],
        ['supply_name' => 'Vegetable oil', 'unit' => 'kg'],
        ['supply_name' => 'Baking powder', 'unit' => 'g'],
        ['supply_name' => 'Baking soda', 'unit' => 'g'],
    ],
    'stock_units' => ['kg', 'g', 'piece', 'pcs', 'can', 'ml', 'litre', 'pack', 'box', 'bottle'],
];
