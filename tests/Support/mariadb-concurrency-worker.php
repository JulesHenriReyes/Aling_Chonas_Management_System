<?php

use App\Models\Order;
use App\Models\Refund;
use App\Models\User;
use App\Services\InventoryService;
use App\Services\OrderService;
use App\Services\RefundService;
use Illuminate\Validation\ValidationException;

require __DIR__.'/mariadb-bootstrap.php';
disposableMariaDbApplication();
[$script, $directory, $index, $actorId, $payloadJson] = $argv;
$payload = json_decode($payloadJson, true, 512, JSON_THROW_ON_ERROR);
$actor = User::findOrFail($actorId);
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
    $refunds = app(RefundService::class);
    $record = match ($payload['action']) {
        'inventory' => app(InventoryService::class)->post($payload['data'], $actor),
        'deposit' => $orders->recordDownPayment(Order::findOrFail($payload['order']), 1000, $payload['method'] ?? 'cash', $payload['reference'] ?? null, $actor),
        'pickup' => $orders->completePickup(Order::findOrFail($payload['order']), 'cash', null, $actor, true),
        'failure' => $refunds->markBakeryFailure(Order::findOrFail($payload['order']), 'Actual fixture oven failure', $actor, true),
        'refund' => $refunds->complete(Refund::findOrFail($payload['refund']), ['method' => 'cash', 'reference_number' => 'FIXTURE-RETURN', 'transfer_confirmed' => 1], $actor),
    };
    $result = ['status' => 'posted', 'id' => $record?->id];
} catch (ValidationException $error) {
    $result = ['status' => 'rejected', 'fields' => array_keys($error->errors())];
}
file_put_contents($directory.'/result-'.$index.'.json', json_encode($result, JSON_THROW_ON_ERROR));
echo json_encode($result, JSON_THROW_ON_ERROR);
