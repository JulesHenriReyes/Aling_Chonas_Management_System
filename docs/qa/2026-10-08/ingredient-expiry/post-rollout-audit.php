<?php
// Read-only audit of the explicitly identified local active database.
require dirname(__DIR__, 4).'/vendor/autoload.php';
$app = require dirname(__DIR__, 4).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
$target = DB::selectOne('SELECT DATABASE() db, @@port port');
if ($target->db !== 'aling_chona_db' || (int)$target->port !== 3306) throw new RuntimeException('Unexpected database.');
$rollout = json_decode(file_get_contents(__DIR__.'/rollout-evidence.json'), true, 512, JSON_THROW_ON_ERROR);
$changed = [];
foreach ($rollout['original_tables'] as $table => $before) {
    $rows = DB::table($table)->get()->map(fn($row) => json_encode((array)$row, JSON_THROW_ON_ERROR))->all(); sort($rows);
    if (['count'=>count($rows), 'sha256'=>hash('sha256', implode("\n", $rows))] !== $before) $changed[] = $table;
}
$mismatches = DB::select('SELECT s.id FROM supplies s LEFT JOIN stock_entries e ON e.supply_id=s.id GROUP BY s.id,s.current_quantity HAVING ABS(s.current_quantity-COALESCE(SUM(e.remaining_quantity),0)) > 0.001');
$report = [
    'app_up' => !$app->isDownForMaintenance(),
    'migration_recorded' => DB::table('migrations')->where('migration','2026_10_08_000001_add_inventory_stock_entries')->exists(),
    'original_business_tables_changed' => $changed,
    'supply_entry_mismatches' => count($mismatches),
    'unknown_ingredient_entries' => DB::table('stock_entries as e')->join('supplies as s','s.id','=','e.supply_id')->where('s.category','ingredients')->whereNull('e.expiry_date')->where('e.remaining_quantity','>',0)->count(),
    'packaging_entries' => DB::table('stock_entries as e')->join('supplies as s','s.id','=','e.supply_id')->where('s.category','packaging')->count(),
    'stock_allocations' => DB::table('stock_allocations')->count(),
    'backup_hash_verified' => hash_file('sha256',$rollout['backup']['path']) === $rollout['backup']['sha256'],
];
if (!$report['app_up'] || !$report['migration_recorded'] || $changed || $mismatches || !$report['backup_hash_verified']) throw new RuntimeException('Post-rollout verification failed.');
file_put_contents(__DIR__.'/post-rollout-audit.json',json_encode($report, JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
echo json_encode($report, JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR), PHP_EOL;
