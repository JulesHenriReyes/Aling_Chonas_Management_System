<?php
require dirname(__DIR__,2).'/2026-10-05/staff-confirmation-before-payment/preview-bootstrap.php';
$directory = sys_get_temp_dir().'/workflow-concurrency-expiry-preview-'.bin2hex(random_bytes(8));
mkdir($directory); $database=$directory.'/workflow-concurrency-preview.sqlite'; touch($database);
previewApplication($database,$directory.'/storage');
config(['app.url'=>'http://127.0.0.1:8136']);
Illuminate\Support\Facades\Artisan::call('migrate',['--force'=>true]);
$owner=App\Models\User::factory()->create(['first_name'=>'Preview','last_name'=>'Owner','role'=>'owner','email'=>'expiry-owner@example.test','password'=>'Preview-only-2026','is_active'=>true]);
App\Models\User::factory()->create(['first_name'=>'Preview','last_name'=>'Assistant','role'=>'assistant','email'=>'expiry-assistant@example.test','password'=>'Preview-only-2026','is_active'=>true]);
$ledger=app(App\Services\InventoryService::class); $today=App\Support\InventoryCalendar::today();
$flour=App\Models\Supply::create(['supply_name'=>'All-purpose flour with an exceptionally long ingredient name for display checks','category'=>'ingredients','unit'=>'kg','current_quantity'=>3,'reorder_level'=>12,'is_active'=>true]);
$ledger->establishBaseline($flour);
foreach ([[5,1],[6,30],[2,-1]] as [$quantity,$days]) $ledger->post(['submission_key'=>(string)Illuminate\Support\Str::uuid(),'type'=>'receipt','operation_date'=>$today->subDays(3)->toDateString(),'lines'=>[['supply_id'=>$flour->id,'quantity'=>$quantity,'expiry_date'=>$today->addDays($days)->toDateString()]]],$owner);
foreach([['Cake boxes','packaging','piece',8],['Egg','ingredients','piece',0]] as [$name,$category,$unit,$quantity]) {
 $s=App\Models\Supply::create(['supply_name'=>$name,'category'=>$category,'unit'=>$unit,'current_quantity'=>$quantity,'reorder_level'=>2,'is_active'=>true]); $ledger->establishBaseline($s);
}
file_put_contents(__DIR__.'/preview-fixture.json',json_encode(['database'=>$database,'storage'=>$directory.'/storage','supply_id'=>$flour->id],JSON_PRETTY_PRINT));
echo "Isolated expiry browser fixture created.\n";
