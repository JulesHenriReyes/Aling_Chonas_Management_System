<?php
declare(strict_types=1);
use App\Models\{Product, Customer};
use App\Services\{CatalogPricingService, FinancialReportService, ReportPeriod};
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
require dirname(__DIR__,4).'/vendor/autoload.php';
$app = require dirname(__DIR__,4).'/bootstrap/app.php';
$app->addAbsoluteCachePathPrefix('C:');
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
try {
    if (is_file(__DIR__.'/prepared.json')) { echo "Preparation already exists; reusing the same 20 submission keys.\n"; exit; }
    $actual=(array)DB::selectOne('SELECT DATABASE() db, @@port port, VERSION() version');
    if (!$app->environment('local') || $actual['db']!=='aling_chona_db' || (int)$actual['port']!==3306) throw new RuntimeException('Unexpected active database.');
    $private=getenv('USERPROFILE').'/AppData/Local/Temp/bakery-order-population-backup-'.bin2hex(random_bytes(6));
    mkdir($private,0700,true);
    $pdo=DB::connection()->getPdo();
    $pdo->exec('SET TRANSACTION READ ONLY');
    $pdo->beginTransaction();
    $snapshot=['actual'=>$actual,'tables'=>[],'schema'=>[]];
    foreach($pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $table) {
        $identifier=chr(96).str_replace(chr(96),chr(96).chr(96),$table).chr(96);
        $snapshot['tables'][$table]=$pdo->query('SELECT * FROM '.$identifier)->fetchAll(PDO::FETCH_ASSOC);
        $create=$pdo->query('SHOW CREATE TABLE '.$identifier)->fetch(PDO::FETCH_NUM);
        $snapshot['schema'][$table]=$create[1];
    }
    $pdo->commit();
    $backup=$private.'/before-insertion.json';
    file_put_contents($backup,json_encode($snapshot,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
    $dirty=['app/Http/Controllers/OrderController.php','public/css/bakery-ui.css','public/css/workspace-refinements.css','public/js/bakery-ui.js','resources/views/admin/dashboard.blade.php','resources/views/admin/orders/index.blade.php'];
    $hashes=[];
    foreach($dirty as $index=>$file) {
        $path=base_path($file); $hashes[$file]=hash_file('sha256',$path);
        copy($path,$private.'/working-file-'.$index);
    }
    $people=[
      ['Sofia','Mendoza','pending','Butterflies in lavender and cream','Write Happy Birthday Sofia in white icing and keep the butterfly decorations small.'],
      ['Miguel','Santos','pending','Blue ocean and sea creatures','Use pale blue icing with small fish decorations and leave the center clear for the greeting.'],
      ['Isabella','Reyes','deposit','Blush pink garden flowers','Arrange small roses around the top edge and write Happy Birthday Bella in the center.'],
      ['Gabriel','Cruz','deposit','Basketball in orange and black','Place a basketball design on top and add a simple black border around the base.'],
      ['Amelia','Garcia','receipts','White and gold graduation','Write Congratulations Amelia with gold lettering and add a small graduation cap.'],
      ['Lucas','Torres','receipts','Forest green camping adventure','Add small trees around the sides and place a tent design on the top.'],
      ['Victoria','Ramos','confirmed','Lilac stars and clouds','Use lilac and white icing with a few silver stars and write Happy Birthday Vicky.'],
      ['Daniel','Flores','confirmed','Red racing car celebration','Add a checkered border and a small red car decoration without covering the greeting.'],
      ['Olivia','Aquino','confirmed','Peach flowers and soft ivory','Use small peach flowers near the base and write Happy Anniversary in brown lettering.'],
      ['Nathan','Castillo','preparing','Space planets in navy and silver','Place a moon and several small stars on top with navy icing around the sides.'],
      ['Mia','Villanueva','preparing','Rainbow pastel birthday','Arrange pastel colors in a rainbow and add white clouds on both ends.'],
      ['Ethan','Navarro','preparing','Safari animals in sage green','Add a lion and giraffe design with small leaves around the bottom edge.'],
      ['Chloe','Bautista','ready_for_pickup','Sunflowers in yellow and cream','Decorate the top edge with sunflowers and write Happy Birthday Chloe in the middle.'],
      ['Adrian','Dela Rosa','ready_for_pickup','Simple navy and white celebration','Use clean navy borders and write Congratulations Adrian with a small star decoration.'],
      ['Hannah','Lim','ready_for_pickup','Pink heart birthday','Arrange pink hearts on the sides and write Happy Birthday Hannah in white icing.'],
      ['Joaquin','Rivera','completed','Football field in green and white','Use a green top with white field lines and write Happy Birthday Joaquin near the goal.'],
      ['Ella','Fernandez','completed','Dusty rose wedding anniversary','Add a ring of small flowers around the top and write Happy Anniversary Ella and Marco.'],
      ['Marco','Domingo','completed','Coffee colors in beige and brown','Use brown piping around a beige top and write Happy Birthday Marco with a small coffee cup design.'],
      ['Grace','Tan','cancelled_unpaid','Daisies in white and soft yellow','Arrange small daisies around the sides and keep the top greeting simple.'],
      ['Samuel','Pascual','cancelled_deposit','Dinosaurs in green and orange','Add small dinosaur shapes and leafy borders with Happy Birthday Sam on top.'],
    ];
    $catalog=[];
    foreach(Product::where('is_active',true)->with(['options'=>fn($q)=>$q->where('is_active',true)->orderBy('id'),'addOns'=>fn($q)=>$q->where('is_active',true)->orderBy('add_ons.id')])->orderBy('id')->get() as $product)
        foreach($product->options as $option) $catalog[]=['product'=>$product,'option'=>$option];
    if(count($catalog)!==6) throw new RuntimeException('Catalog differs from inspected six options.');
    $used=[];
    $reference=function()use(&$used) {
        do {$ref=(string)random_int(1000000000000,9999999999999);} while(isset($used[$ref]) || DB::table('gcash_references')->where('reference_number',$ref)->exists() || DB::table('payment_proofs')->where('reference_number',$ref)->exists());
        $used[$ref]=true; return $ref;
    };
    $base=CarbonImmutable::now(config('bakery.pickup_timezone'))->startOfDay();
    $plan=[];
    foreach($people as $i=>[$first,$last,$stage,$theme,$instructions]) {
        $choice=$catalog[$i%count($catalog)]; $product=$choice['product'];$option=$choice['option'];
        $extras=[];
        if($i%3!==1 && $product->addOns->isNotEmpty()) {
            $extra=$product->addOns[$i%$product->addOns->count()];
            $extras[]=['add_on_id'=>$extra->id,'quantity'=>$i%5===0?2:1];
        }
        $item=['product_id'=>$product->id,'package_option_id'=>$option->id,'quantity'=>$i%7===0?2:1,'themes'=>$theme,'special_request'=>$instructions,'add_ons'=>$extras];
        $quote=app(CatalogPricingService::class)->quote([$item]);
        do {$phone='0917'.str_pad((string)random_int(0,9999999),7,'0',STR_PAD_LEFT);} while(isset($used[$phone]) || Customer::where('phone_number',$phone)->exists());
        $used[$phone]=true;
        $pickup=$stage==='completed'?$base->subDays(3-($i-15)):($stage==='ready_for_pickup'?$base:$base->addDays(2+$i%5));
        $plan[]=['first_name'=>$first,'last_name'=>$last,'phone_number'=>$phone,'stage'=>$stage,'public'=>in_array($stage,['pending','deposit','receipts','cancelled_unpaid'],true),
          'created_at'=>$base->subDays(7-$i%4)->setTime(9,30)->toIso8601String(),
          'preparing_at'=>($stage==='completed'?$pickup->subDay():$base->subDay())->setTime(10,0)->toIso8601String(),
          'ready_at'=>($stage==='completed'?$pickup:$base->subDay())->setTime($stage==='completed'?11:16,0)->toIso8601String(),
          'cancelled_at'=>$base->subDay()->setTime(12,0)->toIso8601String(),
          'pickup_at'=>$pickup->setTime(14+$i%3,0)->toIso8601String(),
          'deposit_method'=>$i%2===0?'cash':'gcash','deposit_reference'=>$reference(),
          'final_method'=>$i%2===0?'gcash':'cash','final_reference'=>$reference(),'proof_reference'=>$reference(),
          'submission_key'=>(string)Str::uuid(),'expected_total'=>$quote['total'],'expected_deposit'=>$quote['deposit'],'items'=>[$item]];
    }
    $period=ReportPeriod::resolve(['mode'=>'month','month'=>$base->format('Y-m')]);
    $prepared=['actual'=>$actual,'backup'=>$backup,'backup_sha256'=>hash_file('sha256',$backup),'dirty_hashes'=>$hashes,
      'baseline_order_count'=>count($snapshot['tables']['orders']),'report_month'=>$base->format('Y-m'),
      'baseline_report'=>app(FinancialReportService::class)->report($period)['summary'],'plan'=>$plan];
    file_put_contents(__DIR__.'/prepared.json',json_encode($prepared,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
    echo json_encode(['backup_created'=>true,'orders_planned'=>count($plan),'baseline_orders'=>$prepared['baseline_order_count'],'stages'=>array_count_values(array_column($plan,'stage'))],JSON_PRETTY_PRINT)."\n";
} catch(Throwable $e) {fwrite(STDERR,get_class($e).': '.$e->getMessage()."\n"); exit(1);}

