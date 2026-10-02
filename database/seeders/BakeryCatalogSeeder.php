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
                Supply::firstOrCreate(['supply_name' => $s['supply_name']], $s);
            }

            // 2. Add-ons (Preserved or Created if missing)
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

            foreach ($addOns as $ao) {
                AddOn::firstOrCreate(['name' => $ao['name']], $ao);
            }

            $cupcakes = AddOn::where('name', 'like', '%Cupcakes%')->first();
            $puto = AddOn::where('name', 'like', '%Puto Cheese%')->first();
            $kutsinta = AddOn::where('name', 'like', '%Kutsinta%')->first();
            $flan = AddOn::where('name', 'like', '%Leche Flan%')->first();

            // 3. Four Cake Packages (2 Multi-layer, 2 Fixed 2-layer Bundles)
            $packages = [
                [
                    'product' => [
                        'product_name' => 'Vanilla Chiffon Cake Package (with Free Cupcakes)',
                        'price' => 800.00,
                        'description' => 'A soft and fluffy vanilla chiffon cake package. Includes free cupcakes scaled with your selected cake layers.',
                        'photo_path' => 'catalog/packages/simple_2_layer_chiffon.jpg',
                        'is_active' => true,
                    ],
                    'options' => [
                        [
                            'data' => [
                                'layers' => 1,
                                'included_contents' => '',
                                'price' => 800.00,
                                'is_active' => true,
                            ],
                            'included_items' => $cupcakes ? [
                                $cupcakes->id => ['quantity' => 1],
                            ] : [],
                        ],
                        [
                            'data' => [
                                'layers' => 2,
                                'included_contents' => '',
                                'price' => 1500.00,
                                'is_active' => true,
                            ],
                            'included_items' => $cupcakes ? [
                                $cupcakes->id => ['quantity' => 2],
                            ] : [],
                        ],
                    ],
                ],
                [
                    'product' => [
                        'product_name' => 'Chocolate Moist Cake Package (with Free Puto Cheese)',
                        'price' => 900.00,
                        'description' => 'A rich and decadent chocolate moist cake package. Includes free puto cheese scaled with your selected cake layers.',
                        'photo_path' => 'catalog/packages/simple_2_layer_chocolate_moist.jpg',
                        'is_active' => true,
                    ],
                    'options' => [
                        [
                            'data' => [
                                'layers' => 1,
                                'included_contents' => '',
                                'price' => 900.00,
                                'is_active' => true,
                            ],
                            'included_items' => $puto ? [
                                $puto->id => ['quantity' => 1],
                            ] : [],
                        ],
                        [
                            'data' => [
                                'layers' => 2,
                                'included_contents' => '',
                                'price' => 1600.00,
                                'is_active' => true,
                            ],
                            'included_items' => $puto ? [
                                $puto->id => ['quantity' => 2],
                            ] : [],
                        ],
                    ],
                ],
                [
                    'product' => [
                        'product_name' => 'Chiffon Celebration Bundle (with Free Kutsinta)',
                        'price' => 1450.00,
                        'description' => 'A 2-layer vanilla chiffon celebration bundle. Comes with a complimentary box of 12 kutsinta.',
                        'photo_path' => 'catalog/packages/simple_1_layer_chiffon.jpg',
                        'is_active' => true,
                    ],
                    'options' => [
                        [
                            'data' => [
                                'layers' => 2,
                                'included_contents' => '',
                                'price' => 1450.00,
                                'is_active' => true,
                            ],
                            'included_items' => $kutsinta ? [
                                $kutsinta->id => ['quantity' => 1],
                            ] : [],
                        ],
                    ],
                ],
                [
                    'product' => [
                        'product_name' => 'Chocolate Deluxe Feast Bundle (with Free Leche Flan)',
                        'price' => 1700.00,
                        'description' => 'A rich 2-layer chocolate moist cake deluxe bundle. Comes with a complimentary whole traditional leche flan.',
                        'photo_path' => 'catalog/packages/simple_1_layer_chocolate_moist.jpg',
                        'is_active' => true,
                    ],
                    'options' => [
                        [
                            'data' => [
                                'layers' => 2,
                                'included_contents' => '',
                                'price' => 1700.00,
                                'is_active' => true,
                            ],
                            'included_items' => $flan ? [
                                $flan->id => ['quantity' => 1],
                            ] : [],
                        ],
                    ],
                ],
            ];

            $allActiveAddOnIds = AddOn::where('is_active', true)->pluck('id')->toArray();

            foreach ($packages as $pkg) {
                $product = Product::create($pkg['product']);

                foreach ($pkg['options'] as $opt) {
                    $option = $product->options()->create($opt['data']);
                    if (!empty($opt['included_items'])) {
                        $option->includedItems()->sync($opt['included_items']);
                    }
                }

                // Attach all active add-ons as optional paid extras for this package
                $product->addOns()->sync($allActiveAddOnIds);
            }
        });
    }
}
