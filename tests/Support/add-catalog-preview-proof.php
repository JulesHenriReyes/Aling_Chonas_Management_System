<?php

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (!app()->environment('testing') || config('database.default') !== 'sqlite'
    || !str_contains(config('database.connections.sqlite.database'), 'catalog-preview-')) {
    throw new RuntimeException('Only the isolated catalog preview may use this fixture.');
}
$order = App\Models\Order::whereNull('user_id')->where('status', 'pending')->firstOrFail();
if (!$order->paymentProofs()->where('status', 'awaiting_verification')->exists()) {
    $file = new Illuminate\Http\UploadedFile(getenv('TEMP').'/catalog-preview-receipt.png', 'TEST-RECEIPT.png', 'image/png', null, true);
    app(App\Services\PaymentReviewService::class)->submit($order, $file, 'UI-TEST-001');
}
echo 'Synthetic pending proof prepared for isolated order '.$order->id."\n";
