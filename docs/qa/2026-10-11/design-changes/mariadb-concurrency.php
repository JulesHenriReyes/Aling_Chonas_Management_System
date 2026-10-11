<?php
require dirname(__DIR__, 4).'/tests/Support/mariadb-bootstrap.php';
use App\Models\{Customer, Order, PaymentProof, Product, User};
use App\Services\{DesignChangeService, DesignChangeStore, OrderReviewService, OrderService};
use Illuminate\Support\Facades\{Artisan, DB};
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

$app = disposableMariaDbApplication();
$sharedStorage = $app->storagePath();
Artisan::call('migrate', ['--force' => true]);
$staff = User::factory()->create(['role' => 'assistant']);
$owner = User::factory()->create(['role' => 'owner']);
$customer = Customer::create(['first_name'=>'Concurrency','last_name'=>'Fixture','phone_number'=>'09171234567']);
$product = Product::create(['product_name'=>'Concurrency cake','price'=>1000,'is_active'=>true]);
$option = $product->options()->create(['layers'=>1,'price'=>1000,'included_contents'=>'Cake','is_active'=>true]);
$changes = app(DesignChangeService::class);
$newOrder = fn () => app(OrderService::class)->createPublicOrder(['customer_id'=>$customer->id,'pickup_date'=>now()->addDays(3)->toDateString(),'pickup_time'=>'14:00',
    'items'=>[['product_id'=>$product->id,'package_option_id'=>$option->id,'quantity'=>1,'themes'=>'Pink flowers']]]);
$draft = function ($order) use ($changes) {
    $snapshot = $changes->snapshot($order); $items=[];
    foreach($snapshot as $line) $items[$line['id']]=['themes'=>'Green flowers','special_request'=>'','keep_images'=>[],'staged_images'=>[]];
    return ['id'=>(string)Str::uuid(),'fingerprint'=>$changes->fingerprint($snapshot),'snapshot'=>$snapshot,'items'=>$items,'uploads'=>[]];
};
$evidence=[];
function check(bool $ok, string $message): void { if(!$ok) throw new RuntimeException($message); }
function race(string $case, Order $order, array $payloads): array {
    global $staff,$sharedStorage,$evidence;
    $directory = sys_get_temp_dir().'/bakery-design-race-'.Str::uuid(); mkdir($directory);
    $workers=[]; DB::beginTransaction(); DB::table('orders')->where('id',$order->id)->lockForUpdate()->first();
    try {
        foreach($payloads as $index=>$payload) {
            $worker=new Process([PHP_BINARY,__DIR__.'/mariadb-worker.php',$directory,(string)$index,(string)$staff->id,json_encode($payload+['order'=>$order->id],JSON_THROW_ON_ERROR),$sharedStorage],base_path(),null,null,25);
            $worker->start(); $workers[]=$worker;
        }
        $deadline=microtime(true)+15;
        while(count(glob($directory.'/ready-*'))!==count($workers)) {
            foreach($workers as $worker) check($worker->isRunning(),'Worker exited: '.$worker->getErrorOutput());
            check(microtime(true)<$deadline,'Workers failed to reach barrier.'); usleep(10000);
        }
        touch($directory.'/release'); usleep(300000);
        check(count(glob($directory.'/result-*'))===0,'Both workers must wait for the InnoDB row lock.');
        DB::commit(); $results=[];
        foreach($workers as $worker){$worker->wait();check($worker->getExitCode()===0,$worker->getErrorOutput().$worker->getOutput());$results[]=json_decode($worker->getOutput(),true,512,JSON_THROW_ON_ERROR);}
        $evidence[$case]=['both_waited_for_row_lock'=>true,'results'=>$results]; return $results;
    } finally {if(DB::transactionLevel())DB::rollBack();foreach($workers as $worker)if($worker->isRunning())$worker->stop();}
}
function oneRejected(array $results): void {$statuses=array_column($results,'status');sort($statuses);check($statuses===['posted','rejected'],'Exactly one mutation must win.');}

$order=$newOrder(); $proposal=$draft($order);
$result=race('duplicate-submission',$order,[['action'=>'submit','draft'=>$proposal],['action'=>'submit','draft'=>$proposal]]);
check(array_column($result,'status')===['posted','posted'],'Replay must succeed.');
check(count(app(DesignChangeStore::class)->read($order,true)['requests'])===1,'Duplicate submission created history twice.');
$result=race('duplicate-approval',$order,[['action'=>'approve','change'=>$proposal['id']],['action'=>'approve','change'=>$proposal['id']]]);
check(array_column($result,'status')===['posted','posted'],'Approval replay must succeed.');
check($changes->find($order,$proposal['id'])['status']==='approved','Approval did not commit.');
check($order->orderDetails()->first()->themes==='Green flowers','Approved theme is incorrect.');

$order=$newOrder();$a=$draft($order);$b=$draft($order);
oneRejected(race('two-different-requests',$order,[['action'=>'submit','draft'=>$a],['action'=>'submit','draft'=>$b]]));
check(count(app(DesignChangeStore::class)->read($order,true)['requests'])===1,'Two pending requests were recorded.');

$order=$newOrder();$proposal=$draft($order);$changes->submit($order,$proposal,$proposal['items']);
oneRejected(race('approval-vs-withdrawal',$order,[['action'=>'approve','change'=>$proposal['id']],['action'=>'withdraw','change'=>$proposal['id']]]));
check(in_array($changes->find($order,$proposal['id'])['status'],['approved','withdrawn'],true),'Decision remained pending.');

$order=$newOrder();app(OrderReviewService::class)->confirm($order,$owner,true);
$proof=PaymentProof::create(['order_id'=>$order->id,'file_path'=>'private/race.png','reference_number'=>'DESIGN-RACE-'.$order->id]);
app(OrderService::class)->recordDownPayment($order->fresh(),500,'gcash',$proof->reference_number,$owner,null,$proof);
app(OrderService::class)->updateStatus($order->fresh(),'preparing',$staff);
$proposal=$draft($order->fresh());
oneRejected(race('request-vs-ready',$order,[['action'=>'submit','draft'=>$proposal],['action'=>'ready']]));
$state=app(DesignChangeStore::class)->read($order,true);
check(($order->fresh()->status==='preparing' && count($state['requests'])===1) || ($order->fresh()->status==='ready_for_pickup' && count($state['requests'])===0),'Order became ready with a pending request.');

file_put_contents(__DIR__.'/mariadb-results.json',json_encode($evidence,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
echo count($evidence)." MariaDB contention cases passed.\n";
