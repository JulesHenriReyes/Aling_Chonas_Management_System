<?php
require __DIR__.'/preview-bootstrap.php';
$fixture = json_decode(file_get_contents(__DIR__.'/verification/evidence/preview-fixture.json'), true, flags: JSON_THROW_ON_ERROR);
previewApplication($fixture['database'], $fixture['storage']);
set_exception_handler(function (Throwable $error) { fwrite(STDERR, $error->getMessage().PHP_EOL); exit(1); });
$setting = App\Models\PaymentSetting::firstOrFail();
if ($setting->account_number !== 'TEST-ONLY-NOT-A-REAL-ACCOUNT') throw new RuntimeException('Only the synthetic preview may be changed.');
$setting->forceFill(['qr_path' => ($argv[1] ?? '') === 'disable' ? null : 'business/synthetic-review-qr.png'])->save();
echo "Synthetic QR configuration updated.\n";
