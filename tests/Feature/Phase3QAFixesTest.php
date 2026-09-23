<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\Supply;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class Phase3QAFixesTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;
    protected User $assistant;
    protected Customer $customer;
    protected Product $product;
    protected Supply $supply;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::create([
            'first_name' => 'Chona',
            'last_name' => 'Hinay',
            'email' => 'owner@test.com',
            'password' => 'password123',
            'role' => 'owner',
        ]);

        $this->assistant = User::create([
            'first_name' => 'Blyte',
            'last_name' => 'Hinay',
            'email' => 'assistant@test.com',
            'password' => 'password123',
            'role' => 'assistant',
        ]);

        $this->customer = Customer::create([
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
            'phone_number' => '09171112233',
        ]);

        $this->product = Product::create([
            'product_name' => 'Classic Mocha Cake',
            'price' => 750.00,
            'is_active' => true,
        ]);

        $this->supply = Supply::create([
            'supply_name' => 'Cocoa Powder',
            'category' => 'ingredients',
            'unit' => 'kg',
            'current_quantity' => 10.00,
            'reorder_level' => 3.00,
            'is_active' => true,
        ]);
    }

    public function test_product_update_accepts_both_put_and_patch_methods(): void
    {
        $this->actingAs($this->owner);

        // 1. PUT request (matching the Blade form submission)
        $putResponse = $this->put(route('products.update', $this->product), [
            'product_name' => 'Signature Mocha Chiffon Cake',
            'price' => 850.00,
            'is_active' => true,
        ]);
        $putResponse->assertRedirect();
        $this->product->refresh();
        $this->assertSame('Signature Mocha Chiffon Cake', $this->product->product_name);
        $this->assertEquals(850.00, (float) $this->product->price);

        // 2. PATCH request
        $patchResponse = $this->patch(route('products.update', $this->product), [
            'product_name' => 'Deluxe Mocha Chiffon Cake',
            'price' => 900.00,
            'is_active' => false,
        ]);
        $patchResponse->assertRedirect();
        $this->product->refresh();
        $this->assertSame('Deluxe Mocha Chiffon Cake', $this->product->product_name);
        $this->assertEquals(900.00, (float) $this->product->price);
        $this->assertFalse($this->product->is_active);
    }

    public function test_supply_update_via_patch_updates_master_item(): void
    {
        $this->actingAs($this->assistant);

        $response = $this->patch(route('supplies.update', $this->supply), [
            'supply_name' => 'Premium Dutch Cocoa Powder',
            'category' => 'ingredients',
            'unit' => 'kg',
            'reorder_level' => 5.00,
            'is_active' => true,
        ]);

        $response->assertRedirect();
        $this->supply->refresh();
        $this->assertSame('Premium Dutch Cocoa Powder', $this->supply->supply_name);
        $this->assertEquals(5.00, (float) $this->supply->reorder_level);
    }

    public function test_customer_phone_normalization_handles_various_philippine_formats(): void
    {
        // 10-digit without leading 0 (common local format)
        $this->assertSame('09179876543', Customer::normalizePhoneNumber('9179876543'));

        // Standard with hyphens
        $this->assertSame('09179876543', Customer::normalizePhoneNumber('0917-987-6543'));

        // Standard with international +63
        $this->assertSame('09179876543', Customer::normalizePhoneNumber('+63 917 987 6543'));

        // Customer findOrCreateMatching matches across 10-digit and 11-digit entries
        $customerA = Customer::findOrCreateMatching([
            'first_name' => 'Pedro',
            'last_name' => 'Penduko',
            'phone_number' => '9181234567',
        ]);
        $this->assertSame('09181234567', $customerA->phone_number);

        $customerB = Customer::findOrCreateMatching([
            'first_name' => 'Pedro',
            'last_name' => 'Penduko',
            'phone_number' => '09181234567',
        ]);
        $this->assertSame($customerA->id, $customerB->id);
    }

    public function test_payment_controller_requires_reference_number_for_gcash_payments(): void
    {
        $this->actingAs($this->assistant);

        $order = app(OrderService::class)->createInternalOrder([
            'customer_id' => $this->customer->id,
            'pickup_date' => now()->addDays(2)->toDateString(),
            'pickup_time' => '10:00',
            'items' => [['product_id' => $this->product->id, 'quantity' => 1]],
        ], $this->owner);

        // Attempt GCash down payment without reference number
        $failResponse = $this->from(route('orders.show', $order))->post(route('orders.payments.store', $order), [
            'payment_type' => 'down_payment',
            'amount' => 375.00,
            'payment_method' => 'gcash',
            'reference_number' => '',
        ]);

        $failResponse->assertRedirect(route('orders.show', $order));
        $failResponse->assertSessionHasErrors('reference_number');

        // Successful GCash down payment with reference number
        $successResponse = $this->post(route('orders.payments.store', $order), [
            'payment_type' => 'down_payment',
            'amount' => 375.00,
            'payment_method' => 'gcash',
            'reference_number' => 'GCASH-PH3-TEST-001',
        ]);

        $successResponse->assertRedirect();
        $order->refresh();
        $this->assertSame('confirmed', $order->status);
        $this->assertSame('partially_paid', $order->payment_status);
        $this->assertSame('GCASH-PH3-TEST-001', $order->payments->first()->reference_number);
    }

    public function test_internal_staff_order_creation_handles_per_item_image_uploads(): void
    {
        Storage::fake('public');
        $this->actingAs($this->owner);

        $imageFile = UploadedFile::fake()->image('staff_custom_cake_peg.png');

        $response = $this->post(route('orders.store'), [
            'customer_id' => $this->customer->id,
            'pickup_date' => now()->addDays(3)->toDateString(),
            'pickup_time' => '14:00',
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'quantity' => 1,
                    'layers' => 2,
                    'themes' => 'Vintage Gold & Navy',
                    'images' => [$imageFile],
                ],
            ],
        ]);

        $order = Order::latest()->first();
        $response->assertRedirect(route('orders.show', $order));

        $detail = $order->orderDetails->sole();
        $this->assertCount(1, $detail->images);
        $uploaded = $detail->images->first();
        $this->assertSame($this->owner->id, $uploaded->uploaded_by);
        Storage::disk('public')->assertExists($uploaded->file_path);
    }

    public function test_public_storage_symlink_is_established(): void
    {
        $storageLinkPath = public_path('storage');
        $this->assertTrue(
            File::exists($storageLinkPath) || is_link($storageLinkPath),
            'public/storage link should exist for asset rendering.'
        );
    }
}
