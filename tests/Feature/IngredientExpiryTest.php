<?php

namespace Tests\Feature;

use App\Models\{InventoryOperation, InventoryTransaction, StockEntry, Supply, User};
use App\Services\InventoryService;
use App\Support\InventoryCalendar;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class IngredientExpiryTest extends TestCase
{
    use RefreshDatabase;
    private User $actor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-10-08 02:00:00','UTC'));
        $this->actor = User::factory()->create(['role'=>'owner','is_active'=>true]);
        $this->actingAs($this->actor);
    }
    private function supply(string $category='ingredients'): Supply
    {
        return Supply::create(['supply_name'=>(string)Str::uuid(),'category'=>$category,'unit'=>'kg','current_quantity'=>0,'reorder_level'=>2,'is_active'=>true]);
    }
    private function payload(Supply $supply, string $type, float $quantity, array $extra=[]): array
    {
        return ['submission_key'=>(string)Str::uuid(),'type'=>$type,'operation_date'=>'2026-10-08','business_date'=>InventoryCalendar::date(),'notes'=>'Verified test operation',
            'lines'=>[array_merge(['supply_id'=>$supply->id,'quantity'=>$quantity,'expected_version'=>(int)$supply->fresh()->stock_version],$extra)]];
    }
    private function receive(Supply $supply, float $quantity, ?string $expiry, string $date='2026-10-08'): InventoryOperation
    {
        $data=$this->payload($supply,'receipt',$quantity,['expiry_date'=>$expiry]); $data['operation_date']=$date;
        return app(InventoryService::class)->post($data,$this->actor);
    }
    private function reconciles(Supply $supply): void
    {
        $this->assertEquals((float)$supply->fresh()->current_quantity,(float)$supply->stockEntries()->sum('remaining_quantity'));
        foreach ($supply->stockEntries()->get() as $entry) $this->assertEquals((float)$entry->remaining_quantity,($entry->allocations()->exists() && $entry->source !== 'opening_stock' && !($entry->source === 'verified_opening' && !$entry->parent_entry_id) ? 0 : (float)$entry->opening_quantity)+(float)$entry->allocations()->sum('quantity'));
    }
    public function test_receipts_are_separate_even_with_identical_expiry_and_replay_once(): void
    {
        $s=$this->supply(); $data=$this->payload($s,'receipt',3,['expiry_date'=>'2026-10-20']);
        $this->post('/inventory',$data)->assertRedirect(); $this->post('/inventory',$data)->assertRedirect();
        $this->receive($s,5,'2026-10-20');
        $this->assertEquals([3,5],$s->stockEntries()->orderBy('id')->pluck('opening_quantity')->map(fn($q)=>(float)$q)->all());
        $this->assertDatabaseCount('stock_entries',2); $this->assertEquals(8,$s->fresh()->current_quantity);
        $this->assertEquals([3,5],$s->stockEntries()->orderBy('id')->pluck('remaining_quantity')->map(fn($q)=>(float)$q)->all()); $this->reconciles($s);
    }
    public function test_ingredient_expiry_is_required_on_all_stock_in_paths(): void
    {
        $s=$this->supply();
        $this->post('/inventory',$this->payload($s,'receipt',3))->assertSessionHasErrors('lines.0.expiry_date');
        $this->post('/supplies/'.$s->id.'/transactions',['transaction_type'=>'stock_in','quantity'=>3])->assertSessionHasErrors('lines.0.expiry_date');
        $this->post('/supplies',['supply_name'=>'Opening bypass','category'=>'ingredients','unit'=>'kg','current_quantity'=>3,'reorder_level'=>1])->assertSessionHasErrors('current_quantity');
        $this->assertDatabaseCount('inventory_operations',0); $this->assertDatabaseCount('stock_entries',0);
    }
    public function test_packaging_needs_no_date_and_uses_oldest_entry(): void
    {
        $s=$this->supply('packaging'); $this->receive($s,2,null,'2026-10-01'); $this->receive($s,4,null);
        $this->post('/inventory',$this->payload($s,'usage',3))->assertRedirect();
        $this->assertEquals([0,3],$s->stockEntries()->orderBy('id')->pluck('remaining_quantity')->map(fn($q)=>(float)$q)->all()); $this->reconciles($s);
    }
    public function test_packaging_count_increase_creates_undated_entry_and_reduction_uses_oldest(): void
    {
        $s=$this->supply('packaging'); $this->receive($s,2,null,'2026-10-01');
        $this->post('/inventory',$this->payload($s,'stocktake',5))->assertRedirect();
        $adjustment=$s->stockEntries()->where('source','count_adjustment')->sole();
        $this->assertNull($adjustment->stock_in_date); $this->assertNull($adjustment->expiry_date);
        $this->assertEquals(3,$adjustment->opening_quantity); $this->reconciles($s);
        $this->post('/inventory',$this->payload($s,'stocktake',1))->assertRedirect();
        $this->assertEquals(0,$adjustment->fresh()->remaining_quantity);
        $this->assertEquals(1,$s->stockEntries()->where('source','stock_in')->sole()->remaining_quantity); $this->reconciles($s);
    }
    public function test_fefo_splits_and_cannot_be_overridden(): void
    {
        $s=$this->supply(); $this->receive($s,3,'2026-11-15'); $later=$s->stockEntries()->sole(); $this->receive($s,5,'2026-10-20');
        $data=$this->payload($s,'usage',6);
        $this->postJson('/inventory/preview',['lines'=>$data['lines']])->assertOk()->assertJsonPath('lines.0.allocations.0.quantity',5)->assertJsonPath('lines.0.allocations.1.quantity',1);
        $bad=$data; $bad['lines'][0]['stock_entry_id']=$later->id;
        $this->post('/inventory',$bad)->assertSessionHasErrors('lines.0.quantity');
        $this->post('/inventory',$data)->assertRedirect(); $this->assertEquals(2,$later->fresh()->remaining_quantity); $this->reconciles($s);
    }
    public function test_expiry_uses_business_day_and_does_not_decrement_physical_stock(): void
    {
        $s=$this->supply(); $this->receive($s,3,'2026-10-08');
        $this->assertEquals(3,$s->fresh()->usable_quantity);
        $this->travelTo(Carbon::parse('2026-10-08 16:00:00','UTC')); // midnight October 9 in the bakery
        $this->assertEquals(0,$s->fresh()->usable_quantity); $this->assertEquals(3,$s->fresh()->current_quantity);
        $this->post('/inventory',$this->payload($s,'usage',1))->assertSessionHasErrors('lines.0.quantity');
        $this->assertDatabaseCount('inventory_operations',1);
    }
    public function test_stale_preview_and_date_are_rejected_without_posting(): void
    {
        $s=$this->supply(); $this->receive($s,5,'2026-10-20'); $data=$this->payload($s,'usage',1);
        $this->receive($s,2,'2026-10-15');
        $this->post('/inventory',$data)->assertSessionHasErrors('lines.0.quantity');
        $data=$this->payload($s,'usage',1); $data['business_date']='2026-10-07';
        $this->post('/inventory',$data)->assertSessionHasErrors('lines.0.quantity'); $this->assertEquals(7,$s->fresh()->current_quantity);
    }
    public function test_unknown_and_expired_stock_can_be_wasted_and_counted_but_not_used(): void
    {
        $s=$this->supply(); $s->update(['current_quantity'=>5]); app(InventoryService::class)->establishBaseline($s); $opening=$s->stockEntries()->sole();
        $this->receive($s,2,'2026-10-07','2026-10-01'); $expired=$s->stockEntries()->whereNotNull('expiry_date')->sole();
        $this->post('/inventory',$this->payload($s,'usage',1))->assertSessionHasErrors('lines.0.quantity');
        $this->post('/inventory',$this->payload($s,'waste',1,['entries'=>[['stock_entry_id'=>$expired->id,'quantity'=>1]]]))->assertRedirect();
        $this->post('/inventory',$this->payload($s,'stocktake',0,['entries'=>[['stock_entry_id'=>$opening->id,'quantity'=>4],['stock_entry_id'=>$expired->id,'quantity'=>1]]]))->assertRedirect();
        $this->assertEquals(5,$s->fresh()->current_quantity); $this->assertEquals(0,$s->fresh()->usable_quantity); $this->reconciles($s);
    }
    public function test_counts_require_all_entries_and_reject_stale_versions(): void
    {
        $s=$this->supply(); $this->receive($s,3,'2026-10-20'); $this->receive($s,2,'2026-10-21');
        $data=$this->payload($s,'stocktake',0,['entries'=>[['stock_entry_id'=>$s->stockEntries()->first()->id,'quantity'=>1]]]);
        $this->post('/inventory',$data)->assertSessionHasErrors('lines.0.quantity'); $this->assertEquals(5,$s->fresh()->current_quantity);
    }
    public function test_opening_verification_splits_without_changing_quantity_or_expiry_metadata(): void
    {
        $s=$this->supply(); $s->update(['current_quantity'=>5]); app(InventoryService::class)->establishBaseline($s); $old=$s->stockEntries()->sole();
        $data=$this->payload($s,'expiry_verification',0,['stock_entry_id'=>$old->id,'splits'=>[['quantity'=>2,'expiry_date'=>'2026-10-10'],['quantity'=>3,'expiry_date'=>'2026-10-20']]]);
        $this->post('/inventory/verify-opening',$data)->assertRedirect(); $this->post('/inventory/verify-opening',$data)->assertRedirect();
        $this->assertEquals(5,$s->fresh()->current_quantity); $this->assertEquals(5,$s->fresh()->usable_quantity); $this->assertEquals(0,$old->fresh()->remaining_quantity); $this->assertNull($old->fresh()->expiry_date);
        $this->assertDatabaseCount('inventory_operations',1); $this->reconciles($s);
    }
    public function test_verification_is_owner_only_and_cannot_change_a_dated_entry(): void
    {
        $s=$this->supply(); $this->receive($s,2,'2026-10-20'); $data=$this->payload($s,'expiry_verification',0,['stock_entry_id'=>$s->stockEntries()->sole()->id,'splits'=>[['quantity'=>2,'expiry_date'=>'2026-11-20']]]);
        $this->post('/inventory/verify-opening',$data)->assertSessionHasErrors('lines.0.quantity');
        $this->actingAs(User::factory()->create(['role'=>'assistant','is_active'=>true]));
        $this->get('/inventory/verify-opening')->assertForbidden(); $this->post('/inventory/verify-opening',$data)->assertForbidden();
    }
    public function test_receipt_reversal_cannot_take_stock_from_another_entry(): void
    {
        $s=$this->supply(); $original=$this->receive($s,3,'2026-10-10'); $this->receive($s,8,'2026-10-20');
        $this->post('/inventory',$this->payload($s,'usage',2))->assertRedirect();
        $this->post('/inventory/'.$original->id.'/reverse',['submission_key'=>(string)Str::uuid(),'notes'=>'Incorrect stock in'])->assertSessionHasErrors('notes');
        $this->assertEquals(9,$s->fresh()->current_quantity); $this->reconciles($s);
    }
    public function test_usage_reversal_restores_original_entries_even_after_expiration(): void
    {
        $s=$this->supply(); $this->receive($s,3,'2026-10-08'); $this->post('/inventory',$this->payload($s,'usage',2))->assertRedirect(); $usage=InventoryOperation::where('type','usage')->sole();
        $this->travelTo(Carbon::parse('2026-10-09 02:00:00','UTC'));
        $this->post('/inventory/'.$usage->id.'/reverse',['submission_key'=>(string)Str::uuid(),'notes'=>'Correction'])->assertRedirect();
        $this->assertEquals(3,$s->fresh()->current_quantity); $this->assertEquals(0,$s->fresh()->usable_quantity); $this->reconciles($s);
    }
    public function test_invalid_last_row_rolls_back_entries_and_movements(): void
    {
        $one=$this->supply(); $two=$this->supply(); $data=$this->payload($one,'receipt',2,['expiry_date'=>'2026-10-20']); $data['lines'][]=['supply_id'=>$two->id,'quantity'=>2];
        $this->post('/inventory',$data)->assertSessionHasErrors('lines.1.expiry_date');
        $this->assertDatabaseCount('stock_entries',0); $this->assertDatabaseCount('stock_allocations',0); $this->assertDatabaseCount('inventory_operations',0);
    }
    public function test_migration_backfill_is_resumable_and_preserves_old_records(): void
    {
        $s=$this->supply(); $s->update(['current_quantity'=>7]); $before=DB::table('supplies')->where('id',$s->id)->first();
        $migration=require database_path('migrations/2026_10_08_000001_add_inventory_stock_entries.php'); $migration->up(); $migration->up();
        $this->assertEquals($before,DB::table('supplies')->where('id',$s->id)->first()); $this->assertDatabaseCount('stock_entries',1); $this->assertNull($s->stockEntries()->sole()->expiry_date); $this->reconciles($s);
    }
    public function test_low_stock_counts_usable_quantity_and_category_cannot_bypass_expiration(): void
    {
        $s=$this->supply(); $this->receive($s,5,'2026-10-07','2026-10-01');
        $this->assertTrue($s->fresh()->is_low_stock); $this->assertEquals(1,Supply::active()->lowStock()->count());
        $this->patch('/supplies/'.$s->id,['supply_name'=>$s->supply_name,'category'=>'packaging','unit'=>'kg','reorder_level'=>2,'is_active'=>1])->assertSessionHasErrors('category');
    }

    public function test_alternate_endpoint_replay_and_held_stock_enforcement(): void
    {
        $s=$this->supply(); $key=(string)Str::uuid();
        $receipt=['submission_key'=>$key,'transaction_type'=>'stock_in','quantity'=>3,'expiry_date'=>'2026-10-20'];
        $this->post('/supplies/'.$s->id.'/transactions',$receipt)->assertRedirect();
        $this->post('/supplies/'.$s->id.'/transactions',$receipt)->assertRedirect();
        $this->assertEquals(3,$s->fresh()->current_quantity); $this->assertDatabaseCount('inventory_operations',1);
        $this->post('/supplies/'.$s->id.'/transactions',['transaction_type'=>'stock_out','quantity'=>1,'stock_entry_id'=>$s->stockEntries()->sole()->id])->assertSessionHasErrors('lines.0.quantity');
        $held=$this->supply(); $held->update(['current_quantity'=>3]); app(InventoryService::class)->establishBaseline($held);
        $this->post('/supplies/'.$held->id.'/transactions',['transaction_type'=>'stock_out','quantity'=>1])->assertSessionHasErrors('lines.0.quantity');
        $this->assertEquals(3,$held->fresh()->current_quantity);
    }

    public function test_legacy_ingredient_reconciliation_requires_owner_selected_entries(): void
    {
        $s=$this->supply(); $s->update(['current_quantity'=>5]); app(InventoryService::class)->establishBaseline($s);
        $entry=$s->stockEntries()->sole();
        $legacy=\App\Models\InventoryTransaction::create(['supply_id'=>$s->id,'user_id'=>$this->actor->id,'transaction_type'=>'stock_in','quantity'=>2,'transaction_date'=>'2026-09-01']);
        $data=['submission_key'=>(string)Str::uuid(),'notes'=>'Reconcile verified physical stock','reconciliation'=>[$legacy->id=>[['stock_entry_id'=>$entry->id,'quantity'=>2]]]];
        $this->actingAs(User::factory()->create(['role'=>'assistant','is_active'=>true]))->post('/inventory/movements/'.$legacy->id.'/reverse',$data)->assertForbidden();
        $this->actingAs($this->actor)->post('/inventory/movements/'.$legacy->id.'/reverse',array_diff_key($data,['reconciliation'=>true]))->assertSessionHasErrors('lines.0.quantity');
        $this->post('/inventory/movements/'.$legacy->id.'/reverse',$data)->assertRedirect();
        $this->assertEquals(3,$s->fresh()->current_quantity); $this->assertEquals(2,$legacy->fresh()->quantity); $this->reconciles($s);
    }

    public function test_verified_opening_ties_sort_first_and_bulk_reversal_matches_movements(): void
    {
        $s=$this->supply(); $s->update(['current_quantity'=>2]); app(InventoryService::class)->establishBaseline($s); $opening=$s->stockEntries()->sole();
        $this->receive($s,3,'2026-10-20');
        $this->post('/inventory/verify-opening',$this->payload($s,'expiry_verification',0,['stock_entry_id'=>$opening->id,'splits'=>[['quantity'=>2,'expiry_date'=>'2026-10-20']]]))->assertRedirect();
        $preview=$this->postJson('/inventory/preview',['lines'=>[['supply_id'=>$s->id,'quantity'=>1]]]);
        $preview->assertOk()->assertJsonPath('lines.0.allocations.0.stock_entry_id',$s->stockEntries()->where('parent_entry_id',$opening->id)->sole()->id);
        $other=\App\Models\StockEntry::create(['supply_id'=>$s->id,'source'=>'legacy_reconciliation','opening_quantity'=>1,'remaining_quantity'=>1]);
        // Model a second adoption entry without altering any previously dated entry.
        $s->update(['current_quantity'=>6]);
        $data=$this->payload($s,'expiry_verification',0,['stock_entry_id'=>$other->id,'splits'=>[['quantity'=>1,'expiry_date'=>'2026-10-22']]]);
        $this->post('/inventory/verify-opening',$data)->assertRedirect(); $verify=InventoryOperation::where('type','expiry_verification')->latest('id')->first();
        $this->post('/inventory/'.$verify->id.'/reverse',['submission_key'=>(string)Str::uuid(),'notes'=>'Verification corrected'])->assertRedirect();
        $this->assertEquals(1,$other->fresh()->remaining_quantity); $this->assertEquals(6,$s->fresh()->current_quantity);
    }

    public function test_stale_count_keeps_entered_counts_and_zero_stock_stays_countable(): void
    {
        $s=$this->supply(); $this->receive($s,3,'2026-10-20');
        $data=$this->payload($s,'stocktake',2,['entries'=>[['stock_entry_id'=>$s->stockEntries()->sole()->id,'quantity'=>2]]]);
        $this->receive($s,1,'2026-10-21');
        $this->from('/inventory/create/stocktake')->post('/inventory',$data)->assertSessionHasErrors('lines.0.quantity')->assertSessionHasInput('lines.0.entries.0.quantity',2);
        $zero=$this->supply(); $this->post('/inventory',$this->payload($zero,'stocktake',0,['entries'=>[]]))->assertRedirect();
        $this->assertEquals(0,$zero->fresh()->current_quantity);
    }

    public function test_bulk_verification_of_one_supply_reverses_each_movement_once(): void
    {
        $s=$this->supply(); $s->update(['current_quantity'=>5]);
        $one=StockEntry::create(['supply_id'=>$s->id,'source'=>'opening_stock','opening_quantity'=>2,'remaining_quantity'=>2]);
        $two=StockEntry::create(['supply_id'=>$s->id,'source'=>'opening_stock','opening_quantity'=>3,'remaining_quantity'=>3]);
        $data=$this->payload($s,'expiry_verification',0,['stock_entry_id'=>$one->id,'splits'=>[['quantity'=>2,'expiry_date'=>'2026-10-20']]]);
        $data['lines'][]=['supply_id'=>$s->id,'stock_entry_id'=>$two->id,'expected_version'=>0,'splits'=>[['quantity'=>3,'expiry_date'=>'2026-10-21']]];
        $this->post('/inventory/verify-opening',$data)->assertRedirect(); $operation=InventoryOperation::sole();
        $this->assertCount(2,$operation->movements); $this->assertEquals(5,$s->fresh()->usable_quantity);
        $this->post('/inventory/'.$operation->id.'/reverse',['submission_key'=>(string)Str::uuid(),'notes'=>'Correct bulk verification'])->assertRedirect();
        $this->assertEquals(2,$one->fresh()->remaining_quantity); $this->assertEquals(3,$two->fresh()->remaining_quantity);
        $this->assertEquals(0,$s->fresh()->usable_quantity); $this->reconciles($s);
    }
}
