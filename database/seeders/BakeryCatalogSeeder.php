<?php

namespace Database\Seeders;

use App\Models\AddOn;
use App\Models\PackageOption;
use App\Models\Product;
use App\Models\Supply;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BakeryCatalogSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::transaction(function () {
            // 1. Ingredients for ~15 cakes
            $supplies = [
                [
                    'supply_name' => 'All-Purpose Flour',
                    'category' => 'ingredients',
                    'unit' => 'kg',
                    'current_quantity' => 7.50,
                    'reorder_level' => 2.00,
                    'is_active' => true,
                ],
                [
                    'supply_name' => 'Granulated Sugar',
                    'category' => 'ingredients',
                    'unit' => 'kg',
                    'current_quantity' => 7.50,
                    'reorder_level' => 2.00,
                    'is_active' => true,
                ],
                [
                    'supply_name' => 'Large Eggs',
                    'category' => 'ingredients',
                    'unit' => 'pcs',
                    'current_quantity' => 75.00,
                    'reorder_level' => 24.00,
                    'is_active' => true,
                ],
                [
                    'supply_name' => 'Unsalted Butter',
                    'category' => 'ingredients',
                    'unit' => 'kg',
                    'current_quantity' => 3.75,
                    'reorder_level' => 1.00,
                    'is_active' => true,
                ],
                [
                    'supply_name' => 'Cocoa Powder',
                    'category' => 'ingredients',
                    'unit' => 'kg',
                    'current_quantity' => 2.00,
                    'reorder_level' => 0.50,
                    'is_active' => true,
                ],
                [
                    'supply_name' => 'Vanilla Extract',
                    'category' => 'ingredients',
                    'unit' => 'ml',
                    'current_quantity' => 250.00,
                    'reorder_level' => 50.00,
                    'is_active' => true,
                ],
                [
                    'supply_name' => 'Baking Powder',
                    'category' => 'ingredients',
                    'unit' => 'g',
                    'current_quantity' => 500.00,
                    'reorder_level' => 100.00,
                    'is_active' => true,
                ],
            ];

            foreach ($supplies as $s) {
                Supply::create($s);
            }

            // 2. Products & Package Options
            $packages = [
                [
                    'product' => [
                        'product_name' => '1-Layer Chiffon Cake Package (with Free 6 pcs Cupcakes)',
                        'price' => 800.00,
                        'description' => 'A soft and fluffy 1-layer vanilla chiffon cake package that comes with 6 pieces of free cupcakes.',
                        'photo_path' => 'catalog/packages/simple_1_layer_chiffon.jpg',
                        'is_active' => true,
                    ],
                    'option' => [
                        'layers' => 1,
                        'included_contents' => 'Includes a 1-layer vanilla chiffon cake and 6 pieces of free cupcakes.',
                        'price' => 800.00,
                        'is_active' => true,
                    ],
                ],
                [
                    'product' => [
                        'product_name' => '2-Layer Chiffon Cake Package (with Free 12 pcs Cupcakes)',
                        'price' => 1500.00,
                        'description' => 'An elegant 2-layer vanilla chiffon cake package that comes with 12 pieces of free cupcakes.',
                        'photo_path' => 'catalog/packages/simple_2_layer_chiffon.jpg',
                        'is_active' => true,
                    ],
                    'option' => [
                        'layers' => 2,
                        'included_contents' => 'Includes a 2-layer vanilla chiffon cake and 12 pieces of free cupcakes.',
                        'price' => 1500.00,
                        'is_active' => true,
                    ],
                ],
                [
                    'product' => [
                        'product_name' => '1-Layer Chocolate Moist Cake Package (with Free 6 pcs Puto Cheese)',
                        'price' => 900.00,
                        'description' => 'A rich and moist 1-layer chocolate cake package that comes with 6 pieces of free puto cheese.',
                        'photo_path' => 'catalog/packages/simple_1_layer_chocolate_moist.jpg',
                        'is_active' => true,
                    ],
                    'option' => [
                        'layers' => 1,
                        'included_contents' => 'Includes a 1-layer chocolate moist cake and 6 pieces of free puto cheese.',
                        'price' => 900.00,
                        'is_active' => true,
                    ],
                ],
                [
                    'product' => [
                        'product_name' => '2-Layer Chocolate Moist Cake Package (with Free 12 pcs Puto Cheese)',
                        'price' => 1600.00,
                        'description' => 'A rich and moist 2-layer chocolate cake package that comes with 12 pieces of free puto cheese.',
                        'photo_path' => 'catalog/packages/simple_2_layer_chocolate_moist.jpg',
                        'is_active' => true,
                    ],
                    'option' => [
                        'layers' => 2,
                        'included_contents' => 'Includes a 2-layer chocolate moist cake and 12 pieces of free puto cheese.',
                        'price' => 1600.00,
                        'is_active' => true,
                    ],
                ],
            ];

            $createdProducts = [];
            foreach ($packages as $pkg) {
                $product = Product::create($pkg['product']);
                $product->options()->create($pkg['option']);
                $createdProducts[] = $product;
            }

            // 3. Optional Paid Add-ons
            $addOns = [
                [
                    'name' => 'Cupcakes (Box of 6)',
                    'description' => 'Box of 6 freshly baked vanilla and chocolate cupcakes with buttercream swirls.',
                    'price' => 300.00,
                    'photo_path' => 'catalog/add-ons/cupcakes.jpg',
                    'is_active' => true,
                ],
                [
                    'name' => 'Puto Cheese (Box of 12)',
                    'description' => 'Box of 12 traditional soft and fluffy steamed rice cakes topped with cheese.',
                    'price' => 150.00,
                    'photo_path' => 'catalog/add-ons/puto_cheese.jpg',
                    'is_active' => true,
                ],
                [
                    'name' => 'Kutsinta (Box of 12)',
                    'description' => 'Box of 12 chewy brown steamed cakes served with freshly grated coconut.',
                    'price' => 120.00,
                    'photo_path' => 'catalog/add-ons/kutsinta.jpg',
                    'is_active' => true,
                ],
                [
                    'name' => 'Leche Flan (Whole)',
                    'description' => 'Whole traditional Filipino rich and creamy caramel custard dessert.',
                    'price' => 250.00,
                    'photo_path' => 'catalog/add-ons/leche_flan.jpg',
                    'is_active' => true,
                ],
            ];

            $createdAddOns = [];
            foreach ($addOns as $ao) {
                $addon = AddOn::create($ao);
                $createdAddOns[] = $addon;
            }

            // 4. Attach optional paid Add-ons to Products
            $addOnIds = collect($createdAddOns)->pluck('id')->toArray();
            foreach ($createdProducts as $prod) {
                $prod->addOns()->sync($addOnIds);
            }
        });
    }
}
