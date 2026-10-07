<?php
$root = dirname(__DIR__, 4);
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
echo json_encode([
    'connection' => config('database.default'),
    'refunds_table_exists' => Illuminate\Support\Facades\Schema::hasTable('refunds'),
    'refund_record_count' => Illuminate\Support\Facades\Schema::hasTable('refunds') ? Illuminate\Support\Facades\DB::table('refunds')->count() : 0,
    'pending_migrations' => Illuminate\Support\Facades\DB::table('migrations')->where('migration', '2026_10_07_000001_remove_refunds_feature')->count() === 0,
], JSON_PRETTY_PRINT), PHP_EOL;
