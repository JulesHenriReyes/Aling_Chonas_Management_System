<?php
// Local, explicit preflight/backup/restore rehearsal. Never seeds or resets the active schema.
require dirname(__DIR__,4).'/vendor/autoload.php';
$app=require dirname(__DIR__,4).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\{Artisan,DB,Schema};
use Symfony\Component\Process\Process;

function check(bool $ok,string $message): void { if (!$ok) throw new RuntimeException($message); }
function snapshot(): array {
    $result=[];
    foreach(DB::select('SHOW FULL TABLES WHERE Table_type = "BASE TABLE"') as $table) {
        $name=array_values((array)$table)[0];
        // Browser sessions and caches are transient and can change during maintenance.
        if (in_array($name,['migrations','sessions','cache','cache_locks'],true)) continue;
        $rows=DB::table($name)->get()->map(fn($row)=>json_encode((array)$row,JSON_THROW_ON_ERROR))->all(); sort($rows);
        $result[$name]=['count'=>count($rows),'sha256'=>hash('sha256',implode("\n",$rows))];
    }
    ksort($result); return $result;
}
function originalPreserved(array $before,array $after): bool {
    foreach ($before as $table=>$proof) if (($after[$table]??null)!==$proof) return false;
    return true;
}
function reconciliation(): array {
    $bad=DB::select('SELECT s.id FROM supplies s LEFT JOIN stock_entries e ON e.supply_id=s.id GROUP BY s.id,s.current_quantity HAVING ABS(s.current_quantity-COALESCE(SUM(e.remaining_quantity),0)) > 0.001');
    return ['mismatches'=>count($bad),'supplies'=>DB::table('supplies')->count(),'entries'=>DB::table('stock_entries')->count(),'on_hand'=>DB::table('supplies')->sum('current_quantity'),'entry_total'=>DB::table('stock_entries')->sum('remaining_quantity')];
}
$target=DB::selectOne('SELECT DATABASE() db,@@port port,@@datadir datadir,VERSION() version');
check(config('database.default')==='mysql' && $target->db==='aling_chona_db' && (int)$target->port===3306,'Unexpected active database; stop.');
check(!Schema::hasTable('stock_entries'),'Expiry rollout has already run; use a forward correction.');
check(count(array_diff(glob(database_path('migrations/*.php'))?:[],[]))>0,'Migration files unavailable.');
$directory=sys_get_temp_dir().'/bakery-expiry-backup-'.date('Ymd-His').'-'.bin2hex(random_bytes(4)); mkdir($directory);
$backup=$directory.'/before-expiry.sql';
$configFile=$directory.'/mysql-client.ini';
$mysql=config('database.connections.mysql');
$quote=fn($value)=>'"'.str_replace(['\\','"',"\n","\r"],['\\\\','\\"','\\n','\\r'],(string)$value).'"';
file_put_contents($configFile,"[client]\nhost=127.0.0.1\nport=3306\nuser=".$quote($mysql['username'])."\npassword=".$quote($mysql['password'])."\n");
$before=snapshot(); $supplyRows=DB::table('supplies')->orderBy('id')->get(['id','category','current_quantity','unit','stock_version'])->map(fn($r)=>(array)$r)->all();
try {
    $dump=new Process(['C:/xampp/mysql/bin/mysqldump.exe','--defaults-extra-file='.$configFile,'--single-transaction','--routines','--triggers','--events','--hex-blob','--result-file='.$backup,'aling_chona_db']); $dump->setTimeout(60); $dump->mustRun();
} finally { unlink($configFile); }
check(is_file($backup)&&filesize($backup)>0,'Backup missing or empty.');
$backupHash=hash_file('sha256',$backup);
$fixture=json_decode(file_get_contents(dirname(__DIR__,2).'/2026-10-05/staff-confirmation-before-payment/verification/evidence/mariadb-fixture.json'),true,512,JSON_THROW_ON_ERROR);
$pdo=new PDO('mysql:host=127.0.0.1;port=33317','root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$actual=$pdo->query('SELECT @@port port,@@datadir datadir')->fetch(PDO::FETCH_ASSOC);
$normalize=fn($path)=>strtolower(rtrim(str_replace('\\','/',realpath($path)?:$path),'/'));
check((int)$actual['port']===33317 && $normalize($actual['datadir'])===$normalize($fixture['datadir']) && str_starts_with($normalize($fixture['datadir']),$normalize(sys_get_temp_dir()).'/bakery-implementation-mysql-'),'Clone server isolation not proven.');
$pdo->exec('DROP DATABASE IF EXISTS bakery_implementation_qa'); $pdo->exec('CREATE DATABASE bakery_implementation_qa CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
$restore=new Process(['C:/xampp/mysql/bin/mysql.exe','--no-defaults','--host=127.0.0.1','--port=33317','--user=root','bakery_implementation_qa']); $restore->setInput(fopen($backup,'r')); $restore->setTimeout(60); $restore->mustRun();
$liveConfig=config('database.connections.mysql');
config(['database.connections.mysql'=>array_replace($liveConfig,['url'=>null,'host'=>'127.0.0.1','port'=>33317,'database'=>'bakery_implementation_qa','username'=>'root','password'=>''])]); DB::purge('mysql');
check(originalPreserved($before,snapshot()),'Restored backup differs from the captured active records. Obtain a new stable backup.');
$migrationPath='database/migrations/2026_10_08_000001_add_inventory_stock_entries.php';
Artisan::call('migrate',['--path'=>$migrationPath,'--force'=>true]);
check(originalPreserved($before,snapshot()),'Clone migration changed original records.');
$clone=reconciliation(); check($clone['mismatches']===0,'Clone quantities do not reconcile.');
(require database_path('migrations/2026_10_08_000001_add_inventory_stock_entries.php'))->up();
check($clone===reconciliation(),'Resuming the migration duplicated entries.');
config(['database.connections.mysql'=>$liveConfig]); DB::purge('mysql');
$report=['target'=>['database'=>$target->db,'port'=>(int)$target->port,'version'=>$target->version],'backup'=>['path'=>$backup,'bytes'=>filesize($backup),'sha256'=>$backupHash,'restore_verified'=>true],'original_tables'=>$before,'clone_rehearsal'=>$clone,'resume_safe'=>true,'active_migrated'=>false];
if (($argv[1]??'')==='apply') {
    check(hash_file('sha256',$backup)===$backupHash,'Backup changed.');
    Artisan::call('down',['--retry'=>5]);
    $safeToResume=false;
    try {
        check(originalPreserved($before,snapshot()),'Active records changed since the backup; create and rehearse a fresh backup.');
        Artisan::call('migrate',['--path'=>$migrationPath,'--force'=>true]);
        check(originalPreserved($before,snapshot()),'An original record changed during rollout. Investigate before permitting writes.');
        $live=reconciliation(); check($live['mismatches']===0,'Active entry totals differ from supply balances.');
        check(DB::table('supplies')->orderBy('id')->get(['id','category','current_quantity','unit','stock_version'])->map(fn($r)=>(array)$r)->all()===$supplyRows,'Supply identity, unit, quantity or stock version changed.');
        $report['active_migrated']=true; $report['active_reconciliation']=$live; $safeToResume=true;
    } finally { if ($safeToResume) Artisan::call('up'); }
}
file_put_contents(__DIR__.'/rollout-evidence.json',json_encode($report,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
echo json_encode(['restore_verified'=>true,'resume_safe'=>true,'clone'=>$clone,'active_migrated'=>$report['active_migrated'],'backup_path'=>$backup],JSON_PRETTY_PRINT),PHP_EOL;
