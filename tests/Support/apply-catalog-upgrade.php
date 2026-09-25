<?php

// Additive upgrade with a private pre-migration snapshot and a comparison of
// every pre-existing column. This script never resets or seeds a database.
require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

if (Schema::hasTable('package_options')) {
    throw new RuntimeException('Catalog upgrade is already present; do not repeat it.');
}
$tables = ['users', 'customers', 'products', 'orders', 'order_details', 'order_images', 'payments', 'supplies', 'inventory_transactions', 'expenses'];
$snapshot = ['created_at' => now()->toIso8601String(), 'database' => DB::connection()->getDatabaseName(), 'tables' => []];
foreach ($tables as $table) {
    $columns = Schema::getColumnListing($table);
    $snapshot['tables'][$table] = ['columns' => $columns, 'rows' => DB::table($table)->select($columns)->orderBy('id')->get()->toArray()];
}
$directory = storage_path('app/upgrade-backups');
if (!is_dir($directory)) {
    mkdir($directory, 0700, true);
}
$path = $directory.'/before-catalog-'.now()->format('Ymd-His').'-'.getmypid().'.json';
if (file_put_contents($path, json_encode($snapshot, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT)) === false) {
    throw new RuntimeException('Backup failed. Migration was not run.');
}
$exit = Artisan::call('migrate', ['--path' => 'database/migrations/2026_09_24_000001_add_catalog_options_and_payment_review.php', '--force' => true]);
echo Artisan::output();
if ($exit !== 0) {
    throw new RuntimeException('Migration failed; inspect the database and private backup before retrying.');
}
foreach ($snapshot['tables'] as $table => $data) {
    $after = DB::table($table)->select($data['columns'])->orderBy('id')->get()->toArray();
    if ($after != $data['rows']) {
        throw new RuntimeException("Existing {$table} values differ after migration. Inspect the private backup.");
    }
    echo $table.': '.count($after)." original rows preserved\n";
}
echo 'Private backup: '.$path."\n";
