<?php

// Private backup remains outside the repository; shared evidence contains hashes/counts only.
require dirname(__DIR__, 4).'/vendor/autoload.php';
$app = require dirname(__DIR__, 4).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
set_exception_handler(function (Throwable $error) { fwrite(STDERR, $error->getMessage().PHP_EOL); exit(1); });
$pdo = Illuminate\Support\Facades\DB::connection()->getPdo();
if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'mysql') throw new RuntimeException('Expected regular MariaDB.');
$identity = $pdo->query('SELECT DATABASE() AS db, @@port AS port, @@datadir AS datadir')->fetch(PDO::FETCH_ASSOC);
if ($identity['db'] !== 'aling_chona_db' || (int) $identity['port'] !== 3306
    || strtolower(str_replace('\\', '/', rtrim($identity['datadir'], '/\\'))) !== 'c:/users/user/desktop/xampp/mysql/data') {
    throw new RuntimeException('Unexpected regular database identity: '.json_encode($identity).'; no backup or migration authorized for it.');
}
$backup = ['identity' => $identity, 'captured_at_utc' => gmdate('c'), 'tables' => []];
$pdo->exec('SET TRANSACTION READ ONLY');
$pdo->beginTransaction();
try {
    foreach ($pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $table) {
        if (!preg_match('/^[a-z_]+$/', $table)) throw new RuntimeException('Unexpected table name.');
        $backup['tables'][$table] = [
            'create_sql' => $pdo->query('SHOW CREATE TABLE `'.$table.'`')->fetch(PDO::FETCH_NUM)[1],
            'rows' => $pdo->query('SELECT * FROM `'.$table.'`')->fetchAll(PDO::FETCH_ASSOC),
        ];
    }
} finally { $pdo->rollBack(); }
$destination = sys_get_temp_dir().'/bakery-staff-review-before-'.bin2hex(random_bytes(8)).'.json';
file_put_contents($destination, json_encode($backup, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
$evidence = ['database' => $identity['db'], 'port' => $identity['port'], 'backup_path' => $destination,
    'sha256' => hash_file('sha256', $destination), 'table_count' => count($backup['tables']),
    'row_counts' => array_map(fn ($table) => count($table['rows']), $backup['tables'])];
$evidenceFile = __DIR__.'/verification/evidence/regular-backup.json';
if (is_file($evidenceFile)) throw new RuntimeException('Preserve the existing backup evidence.');
file_put_contents($evidenceFile, json_encode($evidence, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
echo json_encode($evidence, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
