<?php

namespace Tests\Feature;

use App\Models\{Expense, ExpenseAudit, InventoryOperation, InventoryTransaction, Supply, User};
use App\Services\{FinancialReportService, InventoryService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class StockOperationsAndExpensesTest extends TestCase
{
    use RefreshDatabase;

    private function staff(string $role = 'assistant'): User
    {
        return User::create(['first_name' => ucfirst($role), 'last_name' => 'Test', 'email' => Str::uuid().'@example.test', 'password' => 'password123', 'role' => $role, 'is_active' => true]);
    }

    private function supply(string $name, string $unit = 'kg', float $quantity = 10): Supply
    {
        return Supply::create(['supply_name' => $name, 'category' => 'ingredients', 'unit' => $unit, 'current_quantity' => $quantity, 'reorder_level' => 5, 'is_active' => true]);
    }

    private function batch(array $supplies, string $type = 'receipt', float $quantity = 2): array
    {
        return ['submission_key' => (string) Str::uuid(), 'type' => $type, 'operation_date' => '2026-10-02', 'notes' => 'Delivery / production count',
            'lines' => array_map(fn ($s) => ['supply_id' => $s->id, 'quantity' => $quantity, 'expected_version' => $s->stock_version ?? 0], $supplies)];
    }

    public function test_receipt_is_atomic_grouped_idempotent_and_reconcilable_without_expenses(): void
    {
        $this->actingAs($this->staff());
        $flour = $this->supply('Flour'); $boxes = $this->supply('Boxes', 'piece', 20);
        $data = $this->batch([$flour, $boxes]);
        $this->post('/inventory', $data)->assertRedirect();
        $this->post('/inventory', $data)->assertRedirect();
        $this->assertDatabaseCount('inventory_operations', 1);
        $this->assertDatabaseCount('inventory_transactions', 2);
        $this->assertDatabaseCount('expenses', 0);
        $this->assertEquals(12, $flour->fresh()->current_quantity);
        $this->assertEquals(22, $boxes->fresh()->current_quantity);
        $this->assertDatabaseHas('inventory_baselines', ['supply_id' => $flour->id, 'opening_quantity' => 10]);
        $data['lines'][0]['quantity'] = 7;
        $this->post('/inventory', $data)->assertSessionHasErrors('submission_key');
        $this->assertEquals(12, $flour->fresh()->current_quantity);
        $this->get('/inventory/'.InventoryOperation::sole()->id)->assertOk()->assertSee('Flour')->assertSee('Boxes');
        $this->get('/supplies/'.$flour->id)->assertOk()->assertSee('12.00 kg');
    }

    public function test_invalid_or_duplicate_line_rolls_back_entire_receipt(): void
    {
        $this->actingAs($this->staff()); $one = $this->supply('Flour'); $two = $this->supply('Sugar');
        $data = $this->batch([$one, $two]); $data['lines'][1]['quantity'] = 0;
        $this->post('/inventory', $data)->assertSessionHasErrors('lines.1.quantity');
        $this->assertEquals(10, $one->fresh()->current_quantity);
        $this->assertDatabaseCount('inventory_operations', 0); $this->assertDatabaseCount('inventory_transactions', 0);
        $this->post('/inventory', $this->batch([$one, $one]))->assertSessionHasErrors('lines.0.supply_id');
        $this->assertDatabaseCount('inventory_transactions', 0);
    }

    public function test_usage_waste_negative_stock_and_stale_count(): void
    {
        $this->actingAs($this->staff()); $supply = $this->supply('Flour');
        $staleCount = $this->batch([$supply], 'stocktake', 8);
        $this->post('/inventory', $this->batch([$supply], 'usage', 3))->assertRedirect();
        $this->post('/inventory', $this->batch([$supply], 'waste', 2))->assertRedirect();
        $this->post('/inventory', $this->batch([$supply], 'usage', 6))->assertSessionHasErrors('lines.0.quantity');
        $this->post('/inventory', $staleCount)->assertSessionHasErrors('lines.0.quantity');
        $this->assertEquals(5, $supply->fresh()->current_quantity);
        $this->post('/inventory', $this->batch([$supply->fresh()], 'stocktake', 0))->assertRedirect();
        $this->assertEquals(0, $supply->fresh()->current_quantity);
        $this->assertDatabaseCount('inventory_transactions', 3);
        $this->assertDatabaseHas('inventory_operations', ['type' => 'waste']);
        $noReason = $this->batch([$supply->fresh()], 'stocktake', 1); $noReason['notes'] = '';
        $this->post('/inventory', $noReason)->assertSessionHasErrors('notes');
    }

    public function test_reversal_preserves_original_and_cannot_be_posted_twice(): void
    {
        $this->actingAs($this->staff()); $supply = $this->supply('Flour');
        $this->post('/inventory', $this->batch([$supply]))->assertRedirect();
        $operation = InventoryOperation::sole();
        $correction = ['submission_key' => (string) Str::uuid(), 'notes' => 'Delivery was posted in error'];
        $this->post('/inventory/'.$operation->id.'/reverse', $correction)->assertRedirect();
        $this->post('/inventory/'.$operation->id.'/reverse', $correction)->assertRedirect();
        $this->assertEquals(10, $supply->fresh()->current_quantity);
        $this->assertDatabaseCount('inventory_operations', 2);
        $this->assertDatabaseHas('inventory_transactions', ['reversal_of_id' => $operation->movements()->sole()->id, 'quantity' => -2]);
        $correction['submission_key'] = (string) Str::uuid();
        $this->post('/inventory/'.$operation->id.'/reverse', $correction)->assertSessionHasErrors('notes');
    }

    public function test_supply_opening_balance_unit_guard_search_filters_and_pagination(): void
    {
        $this->actingAs($this->staff());
        $data = ['supply_name' => 'Flour', 'category' => 'ingredients', 'unit' => 'kg', 'current_quantity' => 10, 'reorder_level' => 5, 'is_active' => 1];
        $this->post('/supplies', $data)->assertRedirect(); $supply = Supply::sole();
        $this->assertDatabaseHas('inventory_baselines', ['supply_id' => $supply->id, 'opening_quantity' => 10, 'source' => 'opening_balance']);
        $data['unit'] = 'bag'; $this->patch('/supplies/'.$supply->id, $data)->assertSessionHasErrors('unit');
        $this->assertEquals('kg', $supply->fresh()->unit);
        for ($i = 0; $i < 24; $i++) $this->supply('Boxes '.$i, 'piece', 0);
        $this->get('/supplies?status=out')->assertOk()->assertViewHas('supplies', fn ($rows) => $rows->total() === 24 && $rows->count() === 20);
        $this->get('/supplies/lookup?q=Flour')->assertJsonCount(1)->assertJsonPath('0.unit', 'kg');
        foreach (['/supplies/create', '/supplies/'.$supply->id.'/edit', '/inventory/history', '/inventory/create/receipt', '/inventory/create/usage', '/inventory/create/stocktake'] as $page) $this->get($page)->assertOk();
    }

    public function test_legacy_movement_can_be_corrected_once_without_rewriting_history(): void
    {
        $actor=$this->staff(); $this->actingAs($actor); $supply=$this->supply('Legacy sugar', 'kg', 15);
        $movement=InventoryTransaction::create(['supply_id'=>$supply->id,'user_id'=>$actor->id,'transaction_type'=>'stock_in','quantity'=>5,'transaction_date'=>'2026-09-01','notes'=>'Original delivery']);
        $this->get('/inventory/movements/'.$movement->id)->assertOk()->assertSee('Correct this movement');
        $data=['submission_key'=>(string)Str::uuid(),'notes'=>'Duplicate legacy delivery entry'];
        $this->post('/inventory/movements/'.$movement->id.'/reverse',$data)->assertRedirect();
        $this->post('/inventory/movements/'.$movement->id.'/reverse',$data)->assertRedirect();
        $this->assertEquals(10,$supply->fresh()->current_quantity);
        $this->assertEquals(5,$movement->fresh()->quantity);
        $this->assertDatabaseHas('inventory_baselines',['supply_id'=>$supply->id,'opening_quantity'=>10]);
        $this->assertDatabaseCount('inventory_transactions',2);
        $this->get('/inventory/movements/'.$movement->id)->assertSee('Reversed by');
        $data['submission_key']=(string)Str::uuid();
        $this->post('/inventory/movements/'.$movement->id.'/reverse',$data)->assertSessionHasErrors('notes');
        $operation=InventoryOperation::sole();
        $this->get('/inventory/'.$operation->id)->assertDontSee('Correct this operation');
        $this->expectException(\LogicException::class);
        $operation->update(['notes'=>'Rewrite history']);
    }

    private function expenseData(): array
    {
        return ['submission_key' => (string) Str::uuid(), 'description' => 'Flour delivery', 'category' => 'ingredients', 'amount' => 250, 'expense_date' => '2026-10-02'];
    }

    public function test_expense_creation_edit_and_void_audit_preserve_creator_and_report_effect(): void
    {
        $assistant = $this->staff(); $owner = $this->staff('owner');
        $data = $this->expenseData();
        $this->actingAs($assistant)->post('/expenses', $data)->assertRedirect();
        $this->post('/expenses', $data)->assertRedirect();
        $expense = Expense::sole(); $this->assertDatabaseCount('expense_audits', 1);
        $this->actingAs($owner)->patch('/expenses/'.$expense->id, array_merge($data, ['amount' => 300, 'version' => 0, 'reason' => 'Correct invoice amount']))->assertRedirect();
        $expense->refresh(); $this->assertEquals($assistant->id, $expense->user_id); $this->assertEquals($owner->id, $expense->edited_by);
        $this->assertEquals(250, ExpenseAudit::where('action', 'edited')->sole()->before_values['amount']);
        $this->assertEquals(300, app(FinancialReportService::class)->getExpenses('2026-10-01', '2026-10-31'));
        $this->patch('/expenses/'.$expense->id, $data + ['version' => 0])->assertSessionHasErrors('version');
        $this->delete('/expenses/'.$expense->id, ['version' => 1])->assertSessionHasErrors('reason');
        $this->delete('/expenses/'.$expense->id, ['version' => 1, 'reason' => 'Duplicate invoice in bookkeeping'])->assertRedirect();
        $this->assertSoftDeleted($expense); $this->assertDatabaseCount('expense_audits', 3);
        $this->assertEquals(0, app(FinancialReportService::class)->getExpenses('2026-10-01', '2026-10-31'));
        $this->get('/expenses?status=voided')->assertSee('Flour delivery')->assertViewHas('totalExpenses', 0);
        $this->get('/expenses/'.$expense->id)->assertOk()->assertSee('Duplicate invoice in bookkeeping');
        $this->get('/expenses/history?expense_id='.$expense->id)->assertOk()->assertSee('Correct invoice amount');
        $this->post('/expenses/history', [])->assertStatus(405);
    }

    public function test_expense_total_covers_all_pages_and_authorization_is_server_enforced(): void
    {
        $actor = $this->staff(); $this->actingAs($actor);
        for ($i = 0; $i < 25; $i++) Expense::create($this->expenseData() + ['user_id' => $actor->id]);
        $this->get('/expenses?category=ingredients')->assertOk()->assertViewHas('totalExpenses', 6250)->assertViewHas('expenses', fn ($rows) => $rows->count() === 20)->assertSee('data-desc=', false)->assertSee('data-action=', false);
        foreach (['/expenses/create', '/expenses/1/edit', '/expenses/1', '/expenses/history'] as $page) $this->get($page)->assertOk();
        $actor->update(['is_active' => false]);
        $this->get('/expenses')->assertForbidden(); $this->post('/expenses', $this->expenseData())->assertForbidden();
        $this->patch('/expenses/1', [])->assertForbidden(); $this->delete('/expenses/1', [])->assertForbidden();
        $this->post('/inventory', [])->assertForbidden(); $this->get('/supplies/lookup')->assertForbidden();
    }

    public function test_additive_migration_creates_truthful_legacy_baselines_without_modifying_records(): void
    {
        // Repeat only the backfill calculation on realistic legacy rows; migration schema safety is exercised by RefreshDatabase.
        $actor = $this->staff(); $supply = $this->supply('Legacy flour', 'kg', 15);
        InventoryTransaction::create(['supply_id' => $supply->id, 'user_id' => $actor->id, 'transaction_type' => 'stock_in', 'quantity' => 8, 'transaction_date' => '2026-09-01']);
        InventoryTransaction::create(['supply_id' => $supply->id, 'user_id' => $actor->id, 'transaction_type' => 'stock_out', 'quantity' => 3, 'transaction_date' => '2026-09-02']);
        app(InventoryService::class)->establishBaseline($supply, 'legacy_reconciliation');
        $baseline = DB::table('inventory_baselines')->sole();
        $this->assertEquals(10, $baseline->opening_quantity); $this->assertEquals(5, $baseline->legacy_net_quantity);
        $this->assertDatabaseCount('inventory_transactions', 2); $this->assertEquals(15, $supply->fresh()->current_quantity);
    }
}
