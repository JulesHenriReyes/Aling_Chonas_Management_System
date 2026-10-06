<?php

require dirname(__DIR__, 4).'/vendor/autoload.php';
$app = require dirname(__DIR__, 4).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$pdo = $app['db']->connection()->getPdo();
if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'mysql'
    || $pdo->query('SELECT DATABASE()')->fetchColumn() !== 'aling_chona_db') {
    throw new RuntimeException('Expected local application database.');
}
$pdo->exec('START TRANSACTION READ ONLY');
try {
    $tables = [];
    foreach ($pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $table) {
        if (! preg_match('/^[a-z_]+$/', $table)) throw new RuntimeException('Unexpected table.');
        $rows = $pdo->query('SELECT * FROM `'.$table.'`')->fetchAll(PDO::FETCH_ASSOC);
        $encoded = array_map(fn ($row) => json_encode($row, JSON_THROW_ON_ERROR), $rows);
        sort($encoded, SORT_STRING);
        $tables[$table] = ['count' => count($rows), 'sha256' => hash('sha256', implode("\n", $encoded))];
    }
} finally {
    $pdo->rollBack();
}
$label = $argv[1] ?? 'before';
if (! in_array($label, ['before', 'after'], true)) throw new RuntimeException('Unexpected snapshot label.');
$path = __DIR__.'/data-'.$label.'.json';
if (is_file($path)) throw new RuntimeException('Preserve existing evidence.');
file_put_contents($path, json_encode($tables, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
echo json_encode(['tables' => count($tables), 'saved' => basename($path)]);
