<?php

require dirname(__DIR__, 4).'/vendor/autoload.php';
$app = require dirname(__DIR__, 4).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
set_exception_handler(function (Throwable $error) { fwrite(STDERR, $error->getMessage().PHP_EOL); exit(1); });
$evidence = __DIR__.'/verification/evidence/';
$before = json_decode(file_get_contents($evidence.'regular-before-migration.json'), true, flags: JSON_THROW_ON_ERROR);
$after = json_decode(file_get_contents($evidence.'regular-after-migration.json'), true, flags: JSON_THROW_ON_ERROR);
$changed = [];
foreach ($before['tables'] as $table => $facts) if ($facts !== $after['tables'][$table]) $changed[] = $table;
if ($changed !== ['migrations']) throw new RuntimeException('Unexpected changes: '.json_encode($changed));
if ($after['tables']['migrations']['count'] !== $before['tables']['migrations']['count'] + 1) throw new RuntimeException('Migration ledger mismatch.');
$pdo = Illuminate\Support\Facades\DB::connection()->getPdo();
$pdo->exec('SET TRANSACTION READ ONLY'); $pdo->beginTransaction();
try {
    $reviewed = (int) $pdo->query('SELECT COUNT(*) FROM orders WHERE review_status IS NOT NULL OR reviewed_by IS NOT NULL OR reviewed_at IS NOT NULL')->fetchColumn();
    if ($reviewed !== 0) throw new RuntimeException('Legacy review metadata was fabricated.');
    $columns = $pdo->query("SHOW COLUMNS FROM orders WHERE Field IN ('review_status','reviewed_by','reviewed_at')")->fetchAll(PDO::FETCH_ASSOC);
    if (count($columns) !== 3 || array_filter($columns, fn ($column) => $column['Null'] !== 'YES')) throw new RuntimeException('Review schema mismatch.');
    $queueCounts = [];
    foreach (['review','deposit','receipts','booked'] as $queue) $queueCounts[$queue] = App\Models\Order::workflowQueue($queue)->count();
} finally { $pdo->rollBack(); }
$result = ['database' => $after['database'], 'old_columns_and_business_tables_unchanged' => true,
    'changed_tables' => $changed, 'migration_rows_added' => 1, 'legacy_review_fields_all_null' => true,
    'preserved_orders' => $after['tables']['orders']['count'], 'preserved_payments' => $after['tables']['payments']['count'],
    'preserved_proofs' => $after['tables']['payment_proofs']['count'], 'queue_counts' => $queueCounts, 'review_columns' => $columns];
file_put_contents($evidence.'regular-preservation.json', json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
echo json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
