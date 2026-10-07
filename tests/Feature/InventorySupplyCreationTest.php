<?php

namespace Tests\Feature;

use App\Models\{InventoryOperation, Supply, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class InventorySupplyCreationTest extends TestCase
{
    use RefreshDatabase;

    private function definition(array $overrides = []): array
    {
        return $overrides + ['supply_name' => 'Egg', 'category' => 'ingredients', 'unit' => 'piece',
            'current_quantity' => 0, 'reorder_level' => 12, 'is_active' => true];
    }

    public function test_quick_creation_starts_at_zero_and_stock_is_posted_exactly_once(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'assistant', 'is_active' => true]));
        $response = $this->postJson(route('supplies.store'), $this->definition());
        $response->assertCreated()->assertJsonPath('supply_name', 'Egg')->assertJsonPath('unit', 'piece')
            ->assertJsonPath('current_quantity', '0.00')->assertJsonPath('stock_version', 0);
        $supply = Supply::findOrFail($response->json('id'));
        $this->assertDatabaseHas('inventory_baselines', ['supply_id' => $supply->id, 'opening_quantity' => 0, 'unit' => 'piece']);
        $this->assertDatabaseCount('inventory_operations', 0);
        $this->assertDatabaseCount('inventory_transactions', 0);

        $stock = ['submission_key' => (string) Str::uuid(), 'type' => 'receipt', 'operation_date' => '2026-10-05',
            'lines' => [['supply_id' => $supply->id, 'quantity' => 24, 'expiry_date'=>'2099-12-31']]];
        $this->post(route('inventory.store'), $stock)->assertRedirect();
        $this->post(route('inventory.store'), $stock)->assertRedirect();
        $this->assertEquals(24, $supply->fresh()->current_quantity);
        $this->assertDatabaseCount('inventory_operations', 1);
        $this->assertDatabaseCount('inventory_transactions', 1);
        $this->assertDatabaseCount('expenses', 0);
        $this->assertDatabaseHas('inventory_transactions', ['supply_id' => $supply->id, 'quantity_before' => 0, 'quantity_after' => 24, 'unit' => 'piece']);
        $this->assertNull(InventoryOperation::sole()->delivery_reference);
        $this->get(route('supplies.index'))->assertOk()->assertSee('24.00 piece');
    }

    public function test_quick_creation_rejects_opening_stock_and_duplicate_names_without_extra_records(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'owner', 'is_active' => true]));
        $this->postJson(route('supplies.store'), $this->definition(['current_quantity' => 24]))
            ->assertUnprocessable()->assertJsonValidationErrors('current_quantity');
        $this->assertDatabaseCount('supplies', 0);
        $this->assertDatabaseCount('inventory_baselines', 0);
        $this->postJson(route('supplies.store'), $this->definition())->assertCreated();
        $this->postJson(route('supplies.store'), $this->definition())->assertUnprocessable()->assertJsonValidationErrors('supply_name');
        $this->assertDatabaseCount('supplies', 1);
        $this->assertDatabaseCount('inventory_baselines', 1);
        $this->assertDatabaseCount('inventory_transactions', 0);
    }

    public function test_save_and_stock_in_selects_the_new_supply_without_adding_stock(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'assistant', 'is_active' => true]));
        $response = $this->post(route('supplies.store'), $this->definition(['next' => 'stock_in']));
        $supply = Supply::sole();
        $url = route('inventory.create', ['type' => 'receipt', 'supply_id' => $supply->id]);
        $response->assertRedirect($url);
        $this->get($url)->assertOk()->assertViewHas('initialLines', fn ($lines) => count($lines) === 1
            && $lines[0]['supply_id'] === $supply->id && $lines[0]['quantity'] === ''
            && $lines[0]['name'] === 'Egg' && $lines[0]['unit'] === 'piece' && (float) $lines[0]['current_quantity'] === 0.0);
        $this->assertEquals(0, $supply->fresh()->current_quantity);
        $this->assertDatabaseCount('inventory_operations', 0);
    }

    public function test_save_only_and_inactive_supplies_do_not_open_an_unusable_stock_form(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'owner', 'is_active' => true]));
        $this->post(route('supplies.store'), $this->definition())->assertRedirect(route('supplies.show', Supply::where('supply_name','Egg')->sole()));
        $this->post(route('supplies.store'), $this->definition(['supply_name' => 'Inactive eggs', 'is_active' => false, 'next' => 'stock_in']))
            ->assertRedirect(route('supplies.show', Supply::where('supply_name','Inactive eggs')->sole()));
        $this->get(route('inventory.create', ['type' => 'receipt', 'supply_id' => Supply::where('supply_name','Inactive eggs')->sole()->id]))->assertNotFound();
        $this->assertDatabaseCount('inventory_transactions', 0);
    }

    public function test_quick_creation_is_unavailable_to_guests_and_inactive_staff(): void
    {
        $this->postJson(route('supplies.store'), $this->definition())->assertUnauthorized();
        $this->actingAs(User::factory()->create(['role' => 'assistant', 'is_active' => false]));
        $this->postJson(route('supplies.store'), $this->definition())->assertForbidden();
        $this->assertDatabaseCount('supplies', 0);
        $this->assertDatabaseCount('inventory_baselines', 0);
    }

    public function test_default_ingredient_catalogue_starts_at_zero_and_reseeding_preserves_stock(): void
    {
        $this->seed(\Database\Seeders\DatabaseSeeder::class);
        $this->assertEqualsCanonicalizing(['Flour', 'Sugar', 'Cocoa', 'Egg', 'Evaporated milk', 'Vegetable oil', 'Baking powder', 'Baking soda'],
            Supply::where('category', 'ingredients')->pluck('supply_name')->all());
        foreach (config('inventory.ingredient_presets') as $preset) {
            $this->assertDatabaseHas('supplies', $preset + ['current_quantity' => 0]);
        }
        $this->assertDatabaseCount('inventory_transactions', 0);
        $flour = Supply::where('supply_name', 'Flour')->sole();
        $egg = Supply::where('supply_name', 'Egg')->sole();
        $stock = app(\App\Services\InventoryService::class)->post([
            'submission_key' => (string) Str::uuid(), 'type' => 'receipt', 'operation_date' => '2026-10-05',
            'lines' => [['supply_id' => $flour->id, 'quantity' => 12.5, 'expiry_date'=>'2099-12-31'], ['supply_id' => $egg->id, 'quantity' => 75, 'expiry_date'=>'2099-12-31']],
        ], User::where('role', 'owner')->sole());
        $this->seed(\Database\Seeders\DatabaseSeeder::class);
        $this->assertDatabaseCount('inventory_operations', 1);
        $this->assertDatabaseCount('inventory_transactions', 2);
        $this->assertEquals(12.5, $flour->fresh()->current_quantity);
        $this->assertEquals(75, $egg->fresh()->current_quantity);
        $this->assertEquals('piece', $egg->fresh()->unit);
        $this->assertEquals(2, $stock->movements()->count());
        $this->assertEquals(8, Supply::where('category', 'ingredients')->count());
    }
}
