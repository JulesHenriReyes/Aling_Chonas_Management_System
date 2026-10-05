<?php

// One-time guarded local repair. Private rows go only to a temporary backup.
require dirname(__DIR__, 4).'/vendor/autoload.php';
$app = require dirname(__DIR__, 4).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (($argv[1] ?? null) !== '--apply') {
    throw new RuntimeException('Pass --apply only for the authorized local receipt repair.');
}
$pdo = $app['db']->connection()->getPdo();
if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'mysql') {
    throw new RuntimeException('Expected the regular local MariaDB database.');
}
$identity = $pdo->query('SELECT DATABASE() AS db, @@port AS port, @@datadir AS datadir')->fetch(PDO::FETCH_ASSOC);
if ($identity['db'] !== 'aling_chona_db' || (int) $identity['port'] !== 3306
    || strtolower(rtrim(str_replace('\\', '/', $identity['datadir']), '/')) !== 'c:/users/user/desktop/xampp/mysql/data') {
    throw new RuntimeException('Unexpected database identity; no changes made.');
}
$migrationName = '2026_10_05_000002_bind_receipts_to_order_identity';
if ($pdo->query("SELECT COUNT(*) FROM migrations WHERE migration='$migrationName'")->fetchColumn() != 0
    || is_file(__DIR__.'/local-repair.json')) {
    throw new RuntimeException('Repair already recorded; preserve evidence and do not repeat it.');
}
$snapshot = function () use ($pdo): array {
    $pdo->exec('SET TRANSACTION READ ONLY');
    $pdo->beginTransaction();
    try {
        $tables = [];
        foreach ($pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $table) {
            if (! preg_match('/^[a-z_]+$/', $table)) {
                throw new RuntimeException('Unexpected table name.');
            }
            $tables[$table] = ['schema' => $pdo->query('SHOW CREATE TABLE `'.$table.'`')->fetch(PDO::FETCH_NUM)[1],
                'rows' => $pdo->query('SELECT * FROM `'.$table.'`')->fetchAll(PDO::FETCH_ASSOC)];
        }

        return $tables;
    } finally {
        $pdo->rollBack();
    }
};
$before = $snapshot();
$backupPath = sys_get_temp_dir().'/bakery-receipt-identity-before-'.bin2hex(random_bytes(8)).'.json';
file_put_contents($backupPath, json_encode(['identity' => $identity, 'captured_at_utc' => gmdate('c'),
    'tables' => $before], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR), LOCK_EX);
if (json_decode(file_get_contents($backupPath), true, flags: JSON_THROW_ON_ERROR)['tables'] !== $before) {
    throw new RuntimeException('Backup verification failed; no changes made.');
}
// Persist backup location before DDL so it remains recoverable if migration fails.
file_put_contents(__DIR__.'/backup-metadata.json', json_encode(['backup_path' => $backupPath,
    'sha256' => hash_file('sha256', $backupPath), 'database' => $identity['db'],
    'row_counts' => array_map(fn ($table) => count($table['rows']), $before)], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
$exit = Illuminate\Support\Facades\Artisan::call('migrate', [
    '--path' => 'database/migrations/'.$migrationName.'.php', '--force' => true, '--no-interaction' => true,
]);
file_put_contents(__DIR__.'/local-migration.txt', Illuminate\Support\Facades\Artisan::output());
if ($exit !== 0) {
    throw new RuntimeException('Migration failed; backup metadata is preserved.');
}
require __DIR__.'/verify-local.php';
