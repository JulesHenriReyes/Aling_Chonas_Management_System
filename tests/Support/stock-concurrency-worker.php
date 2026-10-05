<?php

// Only disposable local SQLite fixtures accepted. No environment database is used.
[$script,$database,$directory,$number,$actorId,$payload]=$argv;
if (!str_contains(basename($database),'workflow-concurrency-') || !is_file($database)) throw new RuntimeException('Disposable fixture required.');
putenv('APP_ENV=testing'); putenv('DB_CONNECTION=sqlite'); putenv('DB_URL='); putenv('DB_DATABASE='.$database);
putenv('CACHE_STORE=array'); putenv('SESSION_DRIVER=array');
require dirname(__DIR__,2).'/vendor/autoload.php';
$storage=Tests\Support\DisposableDatabase::storage();
$app=require dirname(__DIR__,2).'/bootstrap/app.php';
$app->addAbsoluteCachePathPrefix('C:');
$app->useStoragePath($storage);
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
Tests\Support\DisposableDatabase::guard($app,$database);
$actor=App\Models\User::findOrFail($actorId);
file_put_contents($directory.'/ready-'.$number,'ready');
$deadline=microtime(true)+10;
while (!is_file($directory.'/release')) { if(microtime(true)>$deadline) throw new RuntimeException('Barrier timeout.'); usleep(10000); }
// Keep the first acquired stock lock long enough for the other process to contend.
$held=false;
Illuminate\Support\Facades\DB::listen(function($query) use (&$held) {
    if (!$held && Illuminate\Support\Facades\DB::transactionLevel()>0 && str_contains($query->sql,'from "supplies"')) { $held=true; usleep(150000); }
});
try {
    $operation=app(App\Services\InventoryService::class)->post(json_decode($payload,true,512,JSON_THROW_ON_ERROR),$actor);
    echo json_encode(['status'=>'posted','id'=>$operation->id]);
} catch (Illuminate\Validation\ValidationException $error) {
    echo json_encode(['status'=>'rejected','errors'=>$error->errors()]);
}
