<?php

use App\Models\Order;
use App\Models\User;
use App\Services\InventoryService;
use App\Services\OrderService;
use App\Services\OrderReviewService;
use App\Services\PaymentReviewService;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

require __DIR__.'/mariadb-bootstrap.php';
disposableMariaDbApplication();
[$script, $directory, $index, $actorId, $payloadJson] = $argv;
$payload = json_decode($payloadJson, true, 512, JSON_THROW_ON_ERROR);
$actor = User::findOrFail($actorId);
if ($payload['action'] === 'receipt') {
    $receiptPath = $directory.'/receipt-'.$index.'.png';
    file_put_contents($receiptPath, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aL1sAAAAASUVORK5CYII='));
}
touch($directory.'/ready-'.$index);
$deadline = microtime(true) + 15;
while (! file_exists($directory.'/release')) {
    if (microtime(true) > $deadline) {
        throw new RuntimeException('Start barrier timed out.');
    }
    usleep(10000);
}
try {
    $orders = app(OrderService::class);
    $record = match ($payload['action']) {
        'confirm' => app(OrderReviewService::class)->confirm(Order::findOrFail($payload['order']), $actor, true),
        'decline' => app(OrderReviewService::class)->decline(Order::findOrFail($payload['order']), 'Fixture capacity unavailable', $actor),
        'cancel' => $orders->cancelOrder(Order::findOrFail($payload['order']), $actor),
        'prepare' => $orders->updateStatus(Order::findOrFail($payload['order']), 'preparing', $actor),
        'receipt' => app(PaymentReviewService::class)->submit(Order::findOrFail($payload['order']), new UploadedFile($receiptPath, 'receipt.png', 'image/png', null, true), 'RACE-RECEIPT'),
        'inventory' => app(InventoryService::class)->post($payload['data'], $actor),
        'deposit' => $orders->recordDownPayment(Order::findOrFail($payload['order']), 1000, $payload['method'] ?? 'cash', $payload['reference'] ?? null, $actor),
        'pickup' => $orders->completePickup(Order::findOrFail($payload['order']), 'cash', null, $actor, true),
    };
    $result = ['status' => 'posted', 'id' => $record?->id];
} catch (ValidationException $error) {
    $result = ['status' => 'rejected', 'fields' => array_keys($error->errors())];
}
$result['receipt_files'] = Storage::disk('receipts')->allFiles();
file_put_contents($directory.'/result-'.$index.'.json', json_encode($result, JSON_THROW_ON_ERROR));
echo json_encode($result, JSON_THROW_ON_ERROR);
