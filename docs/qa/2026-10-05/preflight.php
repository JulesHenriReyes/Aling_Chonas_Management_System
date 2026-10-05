<?php
// Audit only: bootstrap the regular configuration, issue SELECTs, never migrate.
$root = dirname(__DIR__, 3);
$mode = $argv[1] ?? 'before';
$paths = preg_split('/\r?\n/', trim((string) shell_exec('git -C '.escapeshellarg($root).' ls-files')));
$hashes = [];
foreach (array_merge($paths, ['.env', 'bootstrap/cache/config.php']) as $path) {
    if ($path && is_file($root.'/'.$path)) $hashes[$path] = hash_file('sha256', $root.'/'.$path);
}
ksort($hashes);
file_put_contents(__DIR__.'/evidence/production-files-'.$mode.'.json', json_encode($hashes, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$connection = Illuminate\Support\Facades\DB::connection();
$c = $connection->getConfig();
$result = ['audit_date'=>'2026-10-05','url'=>config('app.url'),'app_environment'=>app()->environment(), 'php'=>PHP_VERSION,
    'laravel'=>app()->version(), 'connection_default'=>config('database.default'), 'connection_name'=>$connection->getName(),
    'driver'=>$connection->getDriverName(), 'database'=>$connection->getDatabaseName(), 'host'=>$c['host']??null,
    'port'=>$c['port']??null, 'session_driver'=>config('session.driver'), 'storage_timezone'=>config('app.timezone'),
    'business_timezone'=>config('bakery.business_timezone'), 'pickup_timezone'=>config('bakery.pickup_timezone')];
try {
    $result['resolved_database'] = $connection->selectOne('select database() as db')->db;
    $result['connection_status']='connected';
    $tables = [];
    foreach ($connection->select('show tables') as $t) {
        $name = array_values((array)$t)[0];
        $rows = array_map(fn($row)=>(array)$row, $connection->select('select * from `'.str_replace('`','``',$name).'`'));
        // Sessions/cache/logs legitimately change during read-only browser checks.
        if (in_array($name,['sessions','cache','cache_locks','jobs','failed_jobs','job_batches'])) continue;
        $serialized = array_map(fn($row)=>json_encode($row,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),$rows);
        sort($serialized,SORT_STRING);
        $tables[$name]=['count'=>count($rows),'sha256'=>hash('sha256',implode("\n",$serialized)),
            'columns'=>array_map(fn($c)=>$c->Field,$connection->select('show columns from `'.$name.'`'))];
    }
    ksort($tables);
    file_put_contents(__DIR__.'/evidence/business-records-'.$mode.'.json',json_encode($tables,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
    $result['business_table_count']=count($tables);
    $result['migrations']=$connection->select('select migration, batch from migrations order by migration');
}
catch (Throwable $e) { $result['connection_status']='failed'; $result['error_code']=$e->getCode(); $result['error_summary']=strtok($e->getMessage(), '('); }
if ($c['driver']==='mysql') {
    try {
        $server = new PDO('mysql:host='.$c['host'].';port='.$c['port'].';charset=utf8mb4', $c['username'], $c['password'], [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
        $result['server_version']=$server->query('select version()')->fetchColumn();
        $s=$server->prepare('select count(*) from information_schema.schemata where schema_name=?'); $s->execute([$connection->getDatabaseName()]);
        $result['configured_schema_visible']=(bool)$s->fetchColumn();
    } catch (Throwable $e) { $result['server_read_status']='blocked'; $result['server_error_code']=$e->getCode(); }
}
file_put_contents(__DIR__.'/evidence/regular-environment-'.$mode.'.json', json_encode($result,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
echo json_encode($result,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES).PHP_EOL;
