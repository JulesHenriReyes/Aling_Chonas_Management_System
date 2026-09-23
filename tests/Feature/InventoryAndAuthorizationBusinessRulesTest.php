<?php

namespace Tests\Feature;

use App\Models\Supply;
use App\Models\User;
use App\Services\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class InventoryAndAuthorizationBusinessRulesTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;
    protected User $assistant;
    protected Supply $flour;
    protected InventoryService $inventoryService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->inventoryService = new InventoryService();

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

        $this->flour = Supply::create([
            'supply_name' => 'All-Purpose Flour',
            'category' => 'ingredients',
            'unit' => 'kg',
            'current_quantity' => 10.00,
            'reorder_level' => 5.00,
            'is_active' => true,
        ]);
    }

    public function test_inventory_stock_in_increases_stock_and_creates_transaction(): void
    {
        $this->assertEquals(10.00, $this->flour->current_quantity);

        $tx = $this->inventoryService->recordTransaction(
            $this->flour,
            'stock_in',
            5.00,
            $this->assistant,
            'Weekly grocery restock'
        );

        $this->flour->refresh();
        $this->assertEquals(15.00, $this->flour->current_quantity);
        $this->assertEquals('stock_in', $tx->transaction_type);
        $this->assertEquals(5.00, $tx->quantity);
        $this->assertDatabaseHas('inventory_transactions', [
            'id' => $tx->id,
            'supply_id' => $this->flour->id,
            'transaction_type' => 'stock_in',
            'quantity' => 5.00,
        ]);
    }

    public function test_inventory_stock_out_decreases_stock(): void
    {
        $this->inventoryService->recordTransaction(
            $this->flour,
            'stock_out',
            4.00,
            $this->assistant,
            'Used for weekend wedding cake'
        );

        $this->flour->refresh();
        $this->assertEquals(6.00, $this->flour->current_quantity);
    }

    public function test_negative_stock_rejected_on_stock_out(): void
    {
        $this->expectException(ValidationException::class);
        // Current quantity is 10.00, stocking out 15.00 must be rejected
        $this->inventoryService->recordTransaction(
            $this->flour,
            'stock_out',
            15.00,
            $this->assistant,
            'Excess stock out'
        );
    }

    public function test_positive_adjustment_increases_stock(): void
    {
        $this->inventoryService->recordTransaction(
            $this->flour,
            'adjustment',
            3.50,
            $this->owner,
            'Found extra unopened pack'
        );

        $this->flour->refresh();
        $this->assertEquals(13.50, $this->flour->current_quantity);
    }

    public function test_negative_adjustment_decreases_stock(): void
    {
        $this->inventoryService->recordTransaction(
            $this->flour,
            'adjustment',
            -2.00,
            $this->owner,
            'Spilled bag'
        );

        $this->flour->refresh();
        $this->assertEquals(8.00, $this->flour->current_quantity);
    }

    public function test_negative_adjustment_below_zero_rejected(): void
    {
        $this->expectException(ValidationException::class);
        // Current is 10.00, adjusting by -12.00 results in negative stock
        $this->inventoryService->recordTransaction(
            $this->flour,
            'adjustment',
            -12.00,
            $this->owner,
            'Invalid large reduction'
        );
    }

    public function test_inventory_transaction_and_stock_quantity_stay_synchronized(): void
    {
        $initial = (float) $this->flour->current_quantity;

        $this->inventoryService->recordTransaction($this->flour, 'stock_in', 10.00, $this->assistant);
        $this->inventoryService->recordTransaction($this->flour, 'stock_out', 3.00, $this->assistant);
        $this->inventoryService->recordTransaction($this->flour, 'adjustment', -1.00, $this->owner);

        $this->flour->refresh();
        $expected = $initial + 10.00 - 3.00 - 1.00; // 16.00
        $this->assertEquals($expected, $this->flour->current_quantity);

        $this->assertCount(3, $this->flour->inventoryTransactions);
    }

    public function test_owner_access_gates(): void
    {
        $this->assertTrue(Gate::forUser($this->owner)->allows('manage-users'));
        $this->assertTrue(Gate::forUser($this->owner)->allows('manage-products'));
        $this->assertTrue(Gate::forUser($this->owner)->allows('view-reports'));
        $this->assertTrue(Gate::forUser($this->owner)->allows('manage-expenses'));
        $this->assertTrue(Gate::forUser($this->owner)->allows('manage-customers'));
        $this->assertTrue(Gate::forUser($this->owner)->allows('manage-orders'));
        $this->assertTrue(Gate::forUser($this->owner)->allows('cancel-orders'));
        $this->assertTrue(Gate::forUser($this->owner)->allows('record-payments'));
        $this->assertTrue(Gate::forUser($this->owner)->allows('manage-inventory'));
    }

    public function test_assistant_access_restrictions(): void
    {
        // Restricted features for Assistant
        $this->assertFalse(Gate::forUser($this->assistant)->allows('manage-users'));
        
        // Permitted business/operational features for Assistant
        $this->assertTrue(Gate::forUser($this->assistant)->allows('manage-products'));
        $this->assertTrue(Gate::forUser($this->assistant)->allows('view-reports'));
        $this->assertTrue(Gate::forUser($this->assistant)->allows('manage-expenses'));
        $this->assertTrue(Gate::forUser($this->assistant)->allows('manage-customers'));
        $this->assertTrue(Gate::forUser($this->assistant)->allows('manage-orders'));
        $this->assertTrue(Gate::forUser($this->assistant)->allows('cancel-orders'));
        $this->assertTrue(Gate::forUser($this->assistant)->allows('record-payments'));
        $this->assertTrue(Gate::forUser($this->assistant)->allows('manage-inventory'));
    }

    public function test_assistant_can_access_all_business_modules_but_not_user_management(): void
    {
        $this->actingAs($this->assistant);

        foreach (['/dashboard', '/customers', '/products', '/orders', '/supplies', '/expenses', '/reports', '/pickup-schedule'] as $path) {
            $this->get($path)->assertOk();
        }

        $this->post('/products', [
            'product_name' => 'Assistant-added Cake',
            'price' => 650.00,
            'is_active' => true,
        ])->assertRedirect();

        $this->post('/expenses', [
            'description' => 'Assistant-recorded packaging purchase',
            'category' => 'packaging',
            'amount' => 150.00,
            'expense_date' => today()->toDateString(),
        ])->assertRedirect();

        $this->assertDatabaseHas('products', ['product_name' => 'Assistant-added Cake']);
        $this->assertDatabaseHas('expenses', ['description' => 'Assistant-recorded packaging purchase', 'user_id' => $this->assistant->id]);
        $this->get('/users')->assertForbidden();
    }

    public function test_owner_can_access_user_management(): void
    {
        $this->actingAs($this->owner)->get('/users')->assertOk();
    }
}
