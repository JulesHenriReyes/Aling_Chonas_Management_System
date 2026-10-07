<?php

namespace Tests\Feature;

use App\Models\{Customer, InventoryTransaction, Order, Payment, Product, Supply, User};
use Illuminate\Support\Facades\{Artisan, DB, File};
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class StockConcurrencyAndMigrationTest extends TestCase
{
    private string $directory;
    private string $database;
    protected function setUp(): void
    {
        parent::setUp();
        $this->directory=sys_get_temp_dir().'/workflow-concurrency-'.Str::uuid();
        File::makeDirectory($this->directory);
        $this->database=$this->directory.'/workflow-concurrency-fixture.sqlite';
        touch($this->database);
        config(['database.default'=>'sqlite','database.connections.sqlite.database'=>$this->database,'database.connections.sqlite.url'=>null]);
        DB::purge('sqlite');
    }
    protected function tearDown(): void
    {
        DB::disconnect('sqlite');
        File::deleteDirectory($this->directory);
        parent::tearDown();
    }
    private function migrate(): void { Artisan::call('migrate',['--force'=>true]); }
    private function staff(): User { return User::factory()->create(['role'=>'assistant','is_active'=>true]); }
    private function supply(): Supply { return Supply::create(['supply_name'=>'Shared flour','category'=>'ingredients','unit'=>'kg','current_quantity'=>10,'reorder_level'=>3,'is_active'=>true]); }
    private function payload(Supply $supply,string $type,int $quantity): array
    {
        return ['submission_key'=>(string)Str::uuid(),'type'=>$type,'operation_date'=>'2026-10-02','notes'=>'Concurrent fixture','business_date'=>\App\Support\InventoryCalendar::date(),'lines'=>[['supply_id'=>$supply->id,'quantity'=>$quantity,'expiry_date'=>'2099-12-31','expected_version'=>$supply->fresh()->stock_version]]];
    }
    private function runTogether(User $actor,array $payloads): array
    {
        $workers=[];
        foreach($payloads as $i=>$payload) {
            $workers[]=new Process([PHP_BINARY,base_path('tests/Support/stock-concurrency-worker.php'),$this->database,$this->directory,(string)$i,(string)$actor->id,json_encode($payload)],base_path(),null,null,20);
            $workers[$i]->start();
        }
        try {
            $deadline=microtime(true)+10;
            while(count(glob($this->directory.'/ready-*'))!==count($workers)) {
                foreach($workers as $worker) if(!$worker->isRunning()) $this->fail('Worker exited before barrier: '.$worker->getErrorOutput());
                if(microtime(true)>$deadline) $this->fail('Concurrent workers did not reach the start barrier.');
                usleep(10000);
            }
            touch($this->directory.'/release');
            return array_map(function($worker) { $worker->wait(); $this->assertSame(0,$worker->getExitCode(),$worker->getErrorOutput()); $this->assertJson($worker->getOutput(),$worker->getOutput()); return json_decode($worker->getOutput(),true,512,JSON_THROW_ON_ERROR); },$workers);
        } finally { foreach($workers as $worker) if($worker->isRunning()) $worker->stop(); }
    }
    public function test_concurrent_receipts_have_no_lost_update(): void
    {
        $this->migrate(); $actor=$this->staff(); $supply=$this->supply();
        \App\Models\StockEntry::create(['supply_id'=>$supply->id,'source'=>'verified_opening','expiry_date'=>'2099-12-31','opening_quantity'=>10,'remaining_quantity'=>10]);
        $results=$this->runTogether($actor,[$this->payload($supply,'receipt',3),$this->payload($supply,'receipt',4)]);
        $this->assertSame(['posted','posted'],array_column($results,'status'));
        $this->assertEquals(17,$supply->fresh()->current_quantity);
        $this->assertDatabaseCount('inventory_operations',2); $this->assertDatabaseCount('inventory_transactions',2);
        $this->assertEquals(2,$supply->fresh()->stock_version);
    }
    public function test_concurrent_usage_cannot_overspend_stock(): void
    {
        $this->migrate(); $actor=$this->staff(); $supply=$this->supply();
        \App\Models\StockEntry::create(['supply_id'=>$supply->id,'source'=>'verified_opening','expiry_date'=>'2099-12-31','opening_quantity'=>10,'remaining_quantity'=>10]);
        $results=$this->runTogether($actor,[$this->payload($supply,'usage',7),$this->payload($supply,'usage',7)]);
        $statuses=array_column($results,'status'); sort($statuses);
        $this->assertSame(['posted','rejected'],$statuses);
        $this->assertEquals(3,$supply->fresh()->current_quantity);
        $this->assertDatabaseCount('inventory_transactions',1);
    }
    public function test_concurrent_duplicate_receipt_posts_once(): void
    {
        $this->migrate(); $actor=$this->staff(); $supply=$this->supply();
        \App\Models\StockEntry::create(['supply_id'=>$supply->id,'source'=>'verified_opening','expiry_date'=>'2099-12-31','opening_quantity'=>10,'remaining_quantity'=>10]); $payload=$this->payload($supply,'receipt',3);
        $results=$this->runTogether($actor,[$payload,$payload]);
        $this->assertSame($results[0]['id'],$results[1]['id']);
        $this->assertEquals(13,$supply->fresh()->current_quantity);
        $this->assertDatabaseCount('inventory_operations',1); $this->assertDatabaseCount('inventory_transactions',1);
    }
    public function test_real_additive_migration_preserves_preexisting_records(): void
    {
        // Build the actual old schema, populate it, then run the actual new migrations.
        foreach(File::glob(database_path('migrations/*.php')) as $path) {
            if(basename($path)>='2026_10_02') continue;
            (require $path)->up();
        }
        $actor=$this->staff(); $supply=$this->supply(); $supply->update(['current_quantity'=>15]);
        InventoryTransaction::create(['supply_id'=>$supply->id,'user_id'=>$actor->id,'transaction_type'=>'stock_in','quantity'=>8,'transaction_date'=>'2026-09-01','notes'=>'Legacy receipt']);
        InventoryTransaction::create(['supply_id'=>$supply->id,'user_id'=>$actor->id,'transaction_type'=>'stock_out','quantity'=>3,'transaction_date'=>'2026-09-02']);
        DB::table('expenses')->insert(['user_id'=>$actor->id,'description'=>'Legacy invoice','amount'=>250,'category'=>'ingredients','expense_date'=>'2026-09-01','created_at'=>now(),'updated_at'=>now()]);
        $customer=Customer::create(['first_name'=>'Legacy','last_name'=>'Buyer','phone_number'=>'09170000002']);
        $product=Product::create(['product_name'=>'Legacy celebration cake','description'=>'Saved catalog record','price'=>1250,'is_active'=>true]);
        // Populate the pre-migration schema without current-model creation hooks.
        $order=Order::withoutEvents(fn () => Order::create(['order_number'=>'LEGACY-SAVED-ORDER','customer_id'=>$customer->id,'user_id'=>$actor->id,'status'=>'completed','completed_at'=>'2026-09-20 08:00:00','pickup_date'=>'2026-09-20','pickup_time'=>'16:00','total_amount'=>1250]));
        $order->orderDetails()->create(['product_id'=>$product->id,'product_name_snapshot'=>'Original saved cake name','quantity'=>1,'unit_price'=>1250,'layers'=>1]);
        Payment::create(['order_id'=>$order->id,'user_id'=>$actor->id,'amount'=>1250,'payment_type'=>'final_payment','payment_method'=>'cash','payment_date'=>'2026-09-20 08:00:00']);
        $cancelled=Order::withoutEvents(fn () => Order::create(['order_number'=>'LEGACY-REFUND','customer_id'=>$customer->id,'user_id'=>$actor->id,'status'=>'cancelled','cancelled_at'=>'2026-09-21 08:00:00','cancellation_kind'=>'bakery_failure','pickup_date'=>'2026-09-21','pickup_time'=>'16:00']));
        DB::table('refunds')->insert(['order_id'=>$cancelled->id,'requested_by'=>$actor->id,'amount'=>500,'reason'=>'Legacy bakery-failure refund','status'=>'pending']);
        $snapshots=[];
        foreach(['users','customers','products','orders','order_details','payments','refunds','supplies','inventory_transactions','expenses'] as $table) $snapshots[$table]=DB::table($table)->get()->map(fn($row)=>(array)$row)->all();
        foreach(File::glob(database_path('migrations/2026_10_02*.php')) as $path) (require $path)->up();
        foreach($snapshots as $table=>$rows) {
            $this->assertDatabaseCount($table,count($rows));
            foreach($rows as $row) $this->assertDatabaseHas($table,$row);
        }
        $this->assertDatabaseCount('inventory_transactions',2); $this->assertDatabaseCount('inventory_operations',0);
        $this->assertDatabaseCount('expenses',1); $this->assertDatabaseCount('expense_audits',1);
        $this->assertDatabaseHas('inventory_baselines',['supply_id'=>$supply->id,'opening_quantity'=>10,'observed_quantity'=>15,'legacy_net_quantity'=>5,'source'=>'legacy_reconciliation']);
        $this->assertDatabaseHas('expense_audits',['action'=>'legacy_baseline','actor_id'=>null]);
    }
}
