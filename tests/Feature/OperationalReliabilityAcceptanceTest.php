<?php

namespace Tests\Feature;

use App\Models\InventoryTransaction;
use App\Models\Product;
use App\Models\Supply;
use App\Models\User;
use App\Services\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Tests\TestCase;

class OperationalReliabilityAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    private function supply(string $name): Supply
    {
        return Supply::create(['supply_name' => $name, 'category' => 'ingredients', 'unit' => 'kg',
            'current_quantity' => 10, 'reorder_level' => 5, 'is_active' => true]);
    }

    private function snapshot(): array
    {
        $snapshot = [];
        foreach (['supplies', 'inventory_operations', 'inventory_transactions', 'inventory_baselines'] as $table) {
            $snapshot[$table] = DB::table($table)->orderBy('id')->get()->map(fn ($row) => (array) $row)->all();
        }

        return $snapshot;
    }

    public function test_missing_stocktake_reason_preserves_every_record_and_old_count_input(): void
    {
        $actor = User::factory()->create(['role' => 'assistant', 'is_active' => true]);
        $supply = $this->supply('Reason fixture flour');
        $data = ['submission_key' => (string) Str::uuid(), 'type' => 'stocktake', 'operation_date' => '2026-10-05',
            'notes' => '', 'lines' => [['supply_id' => $supply->id, 'quantity' => 8, 'expected_version' => 0]]];
        $before = $this->snapshot();
        $this->actingAs($actor)->from('/inventory/create/stocktake')->post('/inventory', $data)
            ->assertRedirect('/inventory/create/stocktake')->assertSessionHasErrors('notes')
            ->assertSessionHasInput('lines.0.quantity', 8)->assertSessionHasInput('submission_key', $data['submission_key']);
        $this->assertSame($before, $this->snapshot());
    }

    public function test_failure_after_first_stock_quantity_write_rolls_back_batch_baselines_and_history(): void
    {
        $actor = User::factory()->create(['role' => 'assistant', 'is_active' => true]);
        $one = $this->supply('Rollback flour');
        $two = $this->supply('Rollback sugar');
        $before = $this->snapshot();
        $firstWriteObserved = false;
        Event::listen('eloquent.updated: '.Supply::class, function (Supply $supply) use ($one, &$firstWriteObserved) {
            if ($supply->id !== $one->id) {
                return;
            }
            $firstWriteObserved = (float) DB::table('supplies')->where('id', $one->id)->value('current_quantity') === 12.0
                && InventoryTransaction::where('supply_id', $one->id)->count() === 1;
            throw new \RuntimeException('Injected after the first committed-to-transaction stock update');
        });
        try {
            app(InventoryService::class)->post(['submission_key' => (string) Str::uuid(), 'type' => 'receipt',
                'operation_date' => '2026-10-05', 'notes' => 'Rollback acceptance',
                'lines' => [['supply_id' => $one->id, 'quantity' => 2], ['supply_id' => $two->id, 'quantity' => 3]]], $actor);
            $this->fail('The injected failure did not fire.');
        } catch (\RuntimeException $error) {
            $this->assertStringStartsWith('Injected after', $error->getMessage());
        } finally {
            Event::forget('eloquent.updated: '.Supply::class);
        }
        $this->assertTrue($firstWriteObserved, 'Observe a real quantity and movement write before testing rollback.');
        $this->assertSame($before, $this->snapshot());
    }

    public function test_product_toggle_is_owner_only_and_inactive_actor_cannot_toggle_or_create_stock(): void
    {
        $owner = User::factory()->create(['role' => 'owner', 'is_active' => true]);
        $assistant = User::factory()->create(['role' => 'assistant', 'is_active' => true]);
        $product = Product::create(['product_name' => 'Toggle fixture', 'price' => 2000, 'is_active' => true]);
        $before = $product->fresh()->getRawOriginal();
        $this->actingAs($assistant)->patch('/products/'.$product->id.'/toggle-status')->assertForbidden();
        $this->assertSame($before, $product->fresh()->getRawOriginal());
        $this->actingAs($owner)->patch('/products/'.$product->id.'/toggle-status')->assertRedirect();
        $this->assertFalse($product->fresh()->is_active);
        $owner->update(['is_active' => false]);
        $before = $product->fresh()->getRawOriginal();
        $this->patch('/products/'.$product->id.'/toggle-status')->assertForbidden();
        $this->assertSame($before, $product->fresh()->getRawOriginal());
        $this->post('/supplies', ['supply_name' => 'Forbidden new flour', 'category' => 'ingredients', 'unit' => 'kg',
            'current_quantity' => 10, 'reorder_level' => 5, 'is_active' => 1])->assertForbidden();
        $this->assertDatabaseCount('supplies', 0);
        $this->assertDatabaseCount('inventory_baselines', 0);
    }
}
