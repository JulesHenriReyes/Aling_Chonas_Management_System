<?php

require_once dirname(__DIR__, 4).'/vendor/autoload.php';
if (! isset($app)) {
    $app = require dirname(__DIR__, 4).'/bootstrap/app.php';
    $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
}
$metadata = json_decode(file_get_contents(__DIR__.'/backup-metadata.json'), true, flags: JSON_THROW_ON_ERROR);
if (hash_file('sha256', $metadata['backup_path']) !== $metadata['sha256']) {
    throw new RuntimeException('Private backup hash mismatch.');
}
$backup = json_decode(file_get_contents($metadata['backup_path']), true, flags: JSON_THROW_ON_ERROR);
$pdo = $app['db']->connection()->getPdo();
$identity = $pdo->query('SELECT DATABASE() AS db, @@port AS port, @@datadir AS datadir')->fetch(PDO::FETCH_ASSOC);
if ($identity !== $backup['identity']) {
    throw new RuntimeException('Database identity differs from the backup.');
}
$canonical = function (array $rows): array {
    $rows = array_map(fn ($row) => json_encode($row, JSON_THROW_ON_ERROR), $rows);
    sort($rows, SORT_STRING);

    return $rows;
};
$pdo->exec('START TRANSACTION READ ONLY');
try {
    $preserved = [];
    foreach ($backup['tables'] as $table => $data) {
        if (! preg_match('/^[a-z_]+$/', $table)) throw new RuntimeException('Unexpected table name.');
        $rows = $pdo->query('SELECT * FROM `'.$table.'`')->fetchAll(PDO::FETCH_ASSOC);
        if ($table === 'migrations') {
            $rows = array_values(array_filter($rows, fn ($row) => $row['migration'] !== '2026_10_05_000002_bind_receipts_to_order_identity'));
        }
        foreach ($rows as &$row) {
            if ($table === 'orders') unset($row['receipt_key']);
            if ($table === 'payment_proofs') unset($row['order_receipt_key']);
        }
        unset($row);
        $preserved[$table] = $canonical($data['rows']) === $canonical($rows);
        if (! $preserved[$table]) throw new RuntimeException('Existing rows changed in '.$table.'.');
    }
    $owner = App\Models\User::where('role', 'owner')->where('is_active', true)->firstOrFail();
    Illuminate\Support\Facades\Auth::setUser($owner);
    // HTTP middleware normally supplies this shared bag; CLI rendering needs it too.
    Illuminate\Support\Facades\View::share('errors', new Illuminate\Support\ViewErrorBag());
    $rendered = [];
    foreach (App\Models\Order::with('paymentProofs')->orderBy('id')->get() as $order) {
        $html = app(App\Http\Controllers\OrderController::class)->show($order)->render();
        $rendered[] = ['order_id' => $order->id, 'visible_receipts' => $order->paymentProofs->count(),
            'receipt_image_in_staff_html' => str_contains($html, '/payment-proofs/')];
    }
    $filesPreserved = true;
    foreach ($backup['tables']['payment_proofs']['rows'] as $proof) {
        $filesPreserved = $filesPreserved && Illuminate\Support\Facades\Storage::disk('receipts')->exists($proof['file_path']);
    }
    $result = ['database' => $identity['db'], 'existing_rows_preserved' => $preserved,
        'receipt_records_preserved' => (int) $pdo->query('SELECT COUNT(*) FROM payment_proofs')->fetchColumn(),
        'unbound_receipts' => (int) $pdo->query('SELECT COUNT(*) FROM payment_proofs WHERE order_receipt_key IS NULL')->fetchColumn(),
        'active_receipts' => App\Models\PaymentProof::count(), 'receipt_files_preserved' => $filesPreserved,
        'staff_pages' => $rendered];
    file_put_contents(__DIR__.'/local-repair.json', json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
} finally {
    $pdo->rollBack();
}
