<?php
// GET-only local smoke; no actual customer details or bearer tokens are exported.
require dirname(__DIR__, 4).'/vendor/autoload.php';
$app = require dirname(__DIR__, 4).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
set_exception_handler(function (Throwable $error) { fwrite(STDERR, $error->getMessage().PHP_EOL); exit(1); });
$pdo = Illuminate\Support\Facades\DB::connection()->getPdo();
$pdo->exec('SET TRANSACTION READ ONLY'); $pdo->beginTransaction();
try { $legacy = $pdo->query("SELECT id, private_token FROM orders WHERE status='pending' AND user_id IS NULL ORDER BY id LIMIT 1")->fetch(PDO::FETCH_ASSOC); }
finally { $pdo->rollBack(); }
$checks = [];
foreach (['catalog' => '/', 'login' => '/login', 'legacy_private_page' => '/order/payment/'.$legacy['private_token']] as $name => $path) {
    $curl = curl_init('http://127.0.0.1:8000'.$path);
    curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10]);
    $html = curl_exec($curl); $status = curl_getinfo($curl, CURLINFO_HTTP_CODE); curl_close($curl);
    if ($status !== 200) throw new RuntimeException($name.' did not return 200 ('.$status.').');
    $check = ['screen' => $name, 'http_status' => $status];
    if ($name === 'legacy_private_page') {
        $check['review_first_visible'] = str_contains($html, 'Awaiting staff confirmation');
        $check['payment_qr_withheld'] = !str_contains($html, 'alt="Business GCash payment QR"');
        $check['receipt_form_withheld'] = !str_contains($html, 'name="receipt"');
        if (!$check['review_first_visible'] || !$check['payment_qr_withheld'] || !$check['receipt_form_withheld']) throw new RuntimeException('Legacy payment guard failed.');
    }
    $checks[] = $check;
}
file_put_contents(__DIR__.'/verification/evidence/regular-smoke.json', json_encode($checks, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
echo json_encode($checks, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
