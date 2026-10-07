<?php
require dirname(__DIR__,4).'/tests/Support/mariadb-bootstrap.php';
disposableMariaDbApplication();
use App\Models\{Supply,User};
use App\Services\InventoryService;
use App\Support\InventoryCalendar;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
function check(bool $condition,string $message): void { if (!$condition) throw new RuntimeException($message); }
$actor=User::factory()->create(['role'=>'owner','is_active'=>true]);
$supply=Supply::create(['supply_name'=>'Expiry contention '.Str::uuid(),'category'=>'ingredients','unit'=>'kg','current_quantity'=>0,'reorder_level'=>2,'is_active'=>true]);
function payload(Supply $s,string $type,int $quantity): array {return ['action'=>'inventory','data'=>['submission_key'=>(string)Str::uuid(),'type'=>$type,'operation_date'=>InventoryCalendar::date(),'business_date'=>InventoryCalendar::date(),'notes'=>'Concurrent expiry verification','lines'=>[['supply_id'=>$s->id,'quantity'=>$quantity,'expected_version'=>$s->fresh()->stock_version,'expiry_date'=>InventoryCalendar::today()->addDays(30)->toDateString()]]]];}
app(InventoryService::class)->post(payload($supply,'receipt',10)['data'],$actor);
function race(Supply $s,User $actor,array $payloads): array {
    $directory=sys_get_temp_dir().'/bakery-expiry-contention-'.Str::uuid(); mkdir($directory); $workers=[];
    DB::beginTransaction(); DB::table('supplies')->where('id',$s->id)->lockForUpdate()->first();
    try {
        foreach ($payloads as $index=>$data) { $worker=new Process([PHP_BINARY,base_path('tests/Support/mariadb-concurrency-worker.php'),$directory,(string)$index,(string)$actor->id,json_encode($data,JSON_THROW_ON_ERROR)],base_path(),null,null,30); $worker->start(); $workers[]=$worker; }
        $deadline=microtime(true)+15;
        while(count(glob($directory.'/ready-*'))!==count($workers)) { foreach($workers as $worker) check($worker->isRunning(),'Worker failed: '.$worker->getErrorOutput()); check(microtime(true)<$deadline,'Barrier timeout.'); usleep(10000); }
        touch($directory.'/release'); usleep(300000); check(count(glob($directory.'/result-*.json'))===0,'Both workers must wait for the InnoDB supply lock.'); DB::commit();
        return array_map(function($worker) {$worker->wait();check($worker->getExitCode()===0,$worker->getErrorOutput()); return json_decode($worker->getOutput(),true,512,JSON_THROW_ON_ERROR);},$workers);
    } finally {if(DB::transactionLevel()) DB::rollBack(); foreach($workers as $worker) if($worker->isRunning()) $worker->stop();}
}
$usage=payload($supply,'usage',7); $results=race($supply,$actor,[$usage,payload($supply,'usage',7)]); $statuses=array_column($results,'status');sort($statuses);check($statuses===['posted','rejected'],'Concurrent usage overspent or did not post.');check((float)$supply->fresh()->current_quantity===3.0,'Unexpected usage balance.');
$receipt=payload($supply,'receipt',2);$duplicate=race($supply,$actor,[$receipt,$receipt]);check($duplicate[0]['status']==='posted'&&$duplicate[1]['status']==='posted'&&$duplicate[0]['id']===$duplicate[1]['id'],'Duplicate submission was not idempotent.');check((float)$supply->fresh()->current_quantity===5.0,'Duplicate changed balance twice.');
check((float)$supply->stockEntries()->sum('remaining_quantity')===5.0,'Entry totals do not reconcile.');
$proof=['held_supply_lock_blocked_workers'=>true,'usage'=>$results,'duplicate_receipt'=>$duplicate,'on_hand'=>5,'entry_total'=>5];file_put_contents(__DIR__.'/mariadb-concurrency.json',json_encode($proof,JSON_PRETTY_PRINT));echo json_encode($proof,JSON_PRETTY_PRINT),PHP_EOL;
