<?php
declare(strict_types=1);
use Illuminate\Support\Facades\{DB, Schema, Artisan, Storage};
use Tests\Support\DisposableDatabase;
require dirname(__DIR__,4).'/vendor/autoload.php';
require __DIR__.'/engine.php';
$mode=$argv[1]??'';
if(!in_array($mode,['rehearse','live','audit'],true)) {fwrite(STDERR,"Choose rehearse, live, or audit.\n");exit(1);}
$prepared=json_decode(file_get_contents(__DIR__.'/prepared.json'),true,512,JSON_THROW_ON_ERROR);
demand(hash_file('sha256',$prepared['backup'])===$prepared['backup_sha256'],'Backup integrity mismatch.');
$snapshot=json_decode(file_get_contents($prepared['backup']),true,512,JSON_THROW_ON_ERROR);
$written=[]; $committed=false;
try {
    $storage=null;
    if($mode==='rehearse') {
        putenv('APP_ENV=testing');$_ENV['APP_ENV']=$_SERVER['APP_ENV']='testing';
        putenv('BAKERY_QA_MYSQL_MANIFEST');unset($_ENV['BAKERY_QA_MYSQL_MANIFEST'],$_SERVER['BAKERY_QA_MYSQL_MANIFEST']);
        $storage=DisposableDatabase::storage();
    }
    $app=require dirname(__DIR__,4).'/bootstrap/app.php';
    $app->addAbsoluteCachePathPrefix('C:');
    if($storage)$app->useStoragePath($storage);
    $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    if($mode==='rehearse') {
        DisposableDatabase::guard($app); // Inspect the actual PDO target before all fixture writes.
        demand(Artisan::call('migrate',['--force'=>true])===0,'Disposable migrations failed.');
        Schema::disableForeignKeyConstraints();
        foreach($snapshot['tables'] as $table=>$rows) {
            demand(Schema::hasTable($table),'Missing fixture table '.$table);
            DB::table($table)->delete();
            foreach(array_chunk($rows,100) as $chunk)DB::table($table)->insert($chunk);
        }
        Schema::enableForeignKeyConstraints();
    } else {
        $actual=(array)DB::selectOne('SELECT DATABASE() db, @@port port, VERSION() version');
        demand($app->environment('local') && $actual['db']==='aling_chona_db' && (int)$actual['port']===3306
            && in_array(config('database.connections.mysql.host'),['localhost','127.0.0.1'],true),'Refusing an unexpected live target.');
    }
    $ids=App\Models\Order::whereIn('submission_key',array_column($prepared['plan'],'submission_key'))->get()->keyBy('submission_key');
    if($ids->count()>0) {
        demand($ids->count()===20,'A partial preexisting batch requires investigation.');
        $ids=array_map(fn($row)=>$ids[$row['submission_key']]->id,$prepared['plan']);
        $result=auditOrders($prepared,$ids,$snapshot);
    } else {
        demand($mode!=='audit','No inserted batch exists to audit.');
        if($mode==='live') {
            $rehearsal=json_decode(file_get_contents(__DIR__.'/rehearsal.json'),true,512,JSON_THROW_ON_ERROR);
            demand($rehearsal['verified']===true && $rehearsal['engine_sha256']===hash_file('sha256',__DIR__.'/engine.php')
                && $rehearsal['controller_sha256']===hash_file('sha256',base_path('app/Http/Controllers/OrderController.php')),'Run the same code successfully in the disposable rehearsal first.');
        }
        demand(App\Models\Order::count()===$prepared['baseline_order_count'] && $prepared['baseline_order_count']===0,'The active database changed since preparation.');
        $result=DB::transaction(function()use($prepared,$snapshot,&$written) {
            $ids=addRequestedOrders($prepared,$written);
            return auditOrders($prepared,$ids,$snapshot);
        });
        $committed=true;
    }
    $result+=['verified'=>true,'mode'=>$mode,'engine_sha256'=>hash_file('sha256',__DIR__.'/engine.php'),
        'controller_sha256'=>hash_file('sha256',base_path('app/Http/Controllers/OrderController.php'))];
    if($mode==='rehearse')$result['isolated_storage']=$storage;
    file_put_contents(__DIR__.'/'.($mode==='rehearse'?'rehearsal':($mode==='live'?'inserted':'audit')).'.json',json_encode($result,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
    echo json_encode(['verified'=>true,'mode'=>$mode,'count'=>count($result['orders']),'tabs'=>$result['tab_counts'],
        'batch_delta'=>$result['batch_report_delta'],'payments'=>$result['payment_methods'],'preexisting_rows_unchanged'=>$result['preexisting_business_rows_unchanged']],JSON_PRETTY_PRINT)."\n";
} catch(Throwable $e) {
    clearClock();
    // These are only the individual receipt paths created by this failed batch.
    if(isset($app) && !$committed)foreach($written as $path)if(preg_match('#^orders/[0-9]+/[^/]+$#',$path))Storage::disk('receipts')->delete($path);
    fwrite(STDERR,get_class($e).': '.$e->getMessage()."\n".$e->getTraceAsString()."\n");exit(1);
}

