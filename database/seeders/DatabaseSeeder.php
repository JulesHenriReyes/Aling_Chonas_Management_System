<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\Product;
use App\Models\Supply;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Users: 1 Owner, 1 Assistant
        $owner = User::firstOrCreate(
            ['email' => 'owner@alingchona.local'],
            [
                'first_name' => 'Chona',
                'middle_name' => 'C.',
                'last_name' => 'Hinay',
                'password' => Hash::make('password123'),
                'role' => 'owner',
            ]
        );

        $assistant = User::firstOrCreate(
            ['email' => 'assistant@alingchona.local'],
            [
                'first_name' => 'Blyte',
                'middle_name' => 'P.',
                'last_name' => 'Hinay',
                'password' => Hash::make('password123'),
                'role' => 'assistant',
            ]
        );

        // 2. Customers
        $customers = [
            [
                'first_name' => 'Maria',
                'middle_name' => 'Clara',
                'last_name' => 'Santos',
                'phone_number' => '09171234567',
            ],
            [
                'first_name' => 'Juan',
                'middle_name' => 'Crisostomo',
                'last_name' => 'Ibarra',
                'phone_number' => '09187654321',
            ],
            [
                'first_name' => 'Elena',
                'middle_name' => 'Rose',
                'last_name' => 'Dela Cruz',
                'phone_number' => '09223344556',
            ],
            [
                'first_name' => 'Roberto',
                'middle_name' => null,
                'last_name' => 'Alcantara',
                'phone_number' => '09951122334',
            ],
        ];

        foreach ($customers as $c) {
            Customer::firstOrCreate(['phone_number' => $c['phone_number']], $c);
        }

        // 3. Products
        $products = [
            [
                'product_name' => 'Custom Fondant Celebration Cake',
                'price' => 1500.00,
                'is_active' => true,
            ],
            [
                'product_name' => 'Custom Buttercream Birthday Cake',
                'price' => 950.00,
                'is_active' => true,
            ],
            [
                'product_name' => 'Classic Chocolate Dream Cake',
                'price' => 800.00,
                'is_active' => true,
            ],
            [
                'product_name' => 'Moist Vanilla Celebration Cake',
                'price' => 750.00,
                'is_active' => true,
            ],
            [
                'product_name' => 'Custom Party Cupcakes (Box of 6)',
                'price' => 250.00,
                'is_active' => true,
            ],
            [
                'product_name' => 'Custom Party Cupcakes (Box of 12)',
                'price' => 480.00,
                'is_active' => true,
            ],
        ];

        foreach ($products as $p) {
            Product::firstOrCreate(['product_name' => $p['product_name']], $p);
        }

        // 4. Supplies (Ingredients and Packaging)
        $supplies = [
            [
                'supply_name' => 'All-Purpose Flour',
                'category' => 'ingredients',
                'unit' => 'kg',
                'current_quantity' => 25.00,
                'reorder_level' => 10.00,
                'is_active' => true,
            ],
            [
                'supply_name' => 'Granulated White Sugar',
                'category' => 'ingredients',
                'unit' => 'kg',
                'current_quantity' => 20.00,
                'reorder_level' => 8.00,
                'is_active' => true,
            ],
            [
                'supply_name' => 'Unsalted Butter',
                'category' => 'ingredients',
                'unit' => 'kg',
                'current_quantity' => 12.00,
                'reorder_level' => 5.00,
                'is_active' => true,
            ],
            [
                'supply_name' => 'Dutch Process Cocoa Powder',
                'category' => 'ingredients',
                'unit' => 'kg',
                'current_quantity' => 6.00,
                'reorder_level' => 3.00,
                'is_active' => true,
            ],
            [
                'supply_name' => 'Fresh Eggs (Trays)',
                'category' => 'ingredients',
                'unit' => 'tray',
                'current_quantity' => 5.00,
                'reorder_level' => 2.00,
                'is_active' => true,
            ],
            [
                'supply_name' => 'Pure Vanilla Extract',
                'category' => 'ingredients',
                'unit' => 'bottle',
                'current_quantity' => 4.00,
                'reorder_level' => 2.00,
                'is_active' => true,
            ],
            [
                'supply_name' => 'Pastry Cake Box 8x8',
                'category' => 'packaging',
                'unit' => 'pcs',
                'current_quantity' => 30.00,
                'reorder_level' => 15.00,
                'is_active' => true,
            ],
            [
                'supply_name' => 'Pastry Cake Box 10x10',
                'category' => 'packaging',
                'unit' => 'pcs',
                'current_quantity' => 25.00,
                'reorder_level' => 10.00,
                'is_active' => true,
            ],
            [
                'supply_name' => 'Cupcake Box (6-Cavity)',
                'category' => 'packaging',
                'unit' => 'pcs',
                'current_quantity' => 40.00,
                'reorder_level' => 15.00,
                'is_active' => true,
            ],
            [
                'supply_name' => 'Silver Round Cake Board 8-inch',
                'category' => 'packaging',
                'unit' => 'pcs',
                'current_quantity' => 20.00,
                'reorder_level' => 10.00,
                'is_active' => true,
            ],
        ];

        foreach ($supplies as $s) {
            Supply::firstOrCreate(['supply_name' => $s['supply_name']], $s);
        }
    }
}
