<?php

// Regular application preflight: only SELECTs, inside a read-only transaction.
require dirname(__DIR__, 4).'/vendor/autoload.php';
$app = require dirname(__DIR__, 4).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$connection = Illuminate\Support\Facades\DB::connection();
$pdo = $connection->getPdo();
if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'mysql') {
    throw new RuntimeException('Regular baseline expects the existing MariaDB connection.');
}
$pdo->exec('SET TRANSACTION READ ONLY');
$pdo->beginTransaction();
try {
    $result = [
        'captured_at_utc' => gmdate('c'),
        'connection' => $connection->getName(),
        'driver' => $pdo->getAttribute(PDO::ATTR_DRIVER_NAME),
        'database' => $pdo->query('SELECT DATABASE()')->fetchColumn(),
        'version' => $pdo->query('SELECT VERSION()')->fetchColumn(),
        'pickup_timezone' => config('bakery.pickup_timezone'),
        'storage_timezone' => config('app.timezone'),
        'tables' => [],
    ];
    foreach ($pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $table) {
        if (!preg_match('/^[a-z_]+$/', $table)) throw new RuntimeException('Unexpected table name.');
        $rows = $pdo->query('SELECT * FROM `'.$table.'`')->fetchAll(PDO::FETCH_ASSOC);
        $encoded = array_map(fn ($row) => json_encode($row, JSON_THROW_ON_ERROR), $rows);
        sort($encoded, SORT_STRING);
        $result['tables'][$table] = ['count' => count($rows), 'sha256' => hash('sha256', implode("\n", $encoded))];
    }
    // Export amounts/IDs only; no contacts, credentials or private order tokens.
    $result['legacy_cancellation_exceptions'] = $pdo->query("SELECT o.id, o.status, o.cancellation_kind, o.cancelled_at,
        COALESCE(SUM(p.amount), 0) AS paid,
        COALESCE(SUM(CASE WHEN p.payment_type = 'down_payment' THEN p.amount ELSE 0 END), 0) AS deposits,
        COALESCE(SUM(CASE WHEN p.payment_type = 'final_payment' THEN p.amount ELSE 0 END), 0) AS final_payments
        FROM orders o LEFT JOIN payments p ON p.order_id = o.id
        WHERE o.status = 'cancelled' AND (o.cancellation_kind IS NULL OR o.cancellation_kind = 'customer')
        GROUP BY o.id, o.status, o.cancellation_kind, o.cancelled_at
        HAVING final_payments > 0 OR o.cancellation_kind IS NULL")->fetchAll(PDO::FETCH_ASSOC);
    $result['legacy_overpayments'] = $pdo->query("SELECT o.id,
        (SELECT COALESCE(SUM(p.amount),0) FROM payments p WHERE p.order_id=o.id) AS paid,
        ((SELECT COALESCE(SUM(d.quantity*d.unit_price),0) FROM order_details d WHERE d.order_id=o.id)
        +(SELECT COALESCE(SUM(a.quantity*a.unit_price),0) FROM order_add_ons a JOIN order_details d ON d.id=a.order_detail_id WHERE d.order_id=o.id)) AS total
        FROM orders o HAVING paid > total")->fetchAll(PDO::FETCH_ASSOC);
} finally {
    $pdo->rollBack();
}
$destination = __DIR__.'/evidence/'.($argv[1] ?? 'regular-before').'.json';
if (!is_dir(dirname($destination))) mkdir(dirname($destination), 0777, true);
if (is_file($destination)) throw new RuntimeException('Preserve existing evidence; use a new snapshot name.');
file_put_contents($destination, json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
echo json_encode(['database' => $result['database'], 'tables' => count($result['tables']),
    'legacy_cancellation_exceptions' => $result['legacy_cancellation_exceptions'],
    'legacy_overpayments' => $result['legacy_overpayments'], 'saved' => $destination], JSON_PRETTY_PRINT);
