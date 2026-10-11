<?php
require dirname(__DIR__, 4).'/vendor/autoload.php';
$app = require dirname(__DIR__, 4).'/bootstrap/app.php';
$app->addAbsoluteCachePathPrefix('C:');
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
use App\Models\{Product,User,Order};
$pdo=DB::connection()->getPdo();
$actual=$pdo->query('SELECT DATABASE() db, @@port port, VERSION() version')->fetch(PDO::FETCH_ASSOC);
$catalog=Product::active()->with(['options'=>fn($q)=>$q->where('is_active',true),'addOns'=>fn($q)=>$q->where('is_active',true)])->get()->map(fn($p)=>[
'id'=>$p->id,'name'=>$p->product_name,
'options'=>$p->options->map(fn($o)=>['id'=>$o->id,'layers'=>$o->layers,'price'=>$o->price])->all(),
'extras'=>$p->addOns->map(fn($a)=>['id'=>$a->id,'name'=>$a->name,'price'=>$a->price])->all()])->all();
$data=['actual'=>$actual,'app_timezone'=>config('app.timezone'),'business_timezone'=>config('bakery.business_timezone'),
'now'=>now()->toIso8601String(),'business_now'=>now(config('bakery.business_timezone'))->toIso8601String(),
'staff'=>User::where('is_active',true)->get(['id','role'])->toArray(),
'order_counts'=>Order::selectRaw('status, COUNT(*) count')->groupBy('status')->get()->toArray(),
'catalog'=>$catalog];
file_put_contents(__DIR__.'/inspection.json',json_encode($data,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
echo json_encode($data,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR).PHP_EOL;

