<?php
require dirname(__DIR__, 4).'/tests/Support/mariadb-bootstrap.php';
use App\Models\{Order, User};
use App\Services\{DesignChangeService, OrderService};
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

$app = disposableMariaDbApplication();
[$script, $directory, $index, $actorId, $payloadJson, $sharedStorage] = $argv;
$app->useStoragePath($sharedStorage);
Tests\Support\DisposableDatabase::guard($app);
$payload = json_decode($payloadJson, true, 512, JSON_THROW_ON_ERROR);
$actor = User::findOrFail($actorId);
$order = Order::findOrFail($payload['order']);
touch($directory.'/ready-'.$index);
$deadline = microtime(true) + 15;
while (!file_exists($directory.'/release')) {
    if (microtime(true)>$deadline) throw new RuntimeException('Barrier timed out.');
    usleep(10000);
}
try {
    $changes = app(DesignChangeService::class);
    $record = match ($payload['action']) {
        'submit' => $changes->submit($order, $payload['draft'], $payload['draft']['items']),
        'approve' => $changes->review($order, $payload['change'], 'approved', $actor),
        'withdraw' => $changes->withdraw($order, $payload['change']),
        'ready' => app(OrderService::class)->updateStatus($order, 'ready_for_pickup', $actor),
        'cancel' => app(OrderService::class)->cancelOrder($order, $actor),
    };
    $result = ['status' => 'posted'];
} catch (ValidationException $error) {
    $result = ['status' => 'rejected', 'fields' => array_keys($error->errors())];
}
file_put_contents($directory.'/result-'.$index.'.json', json_encode($result, JSON_THROW_ON_ERROR));
echo json_encode($result, JSON_THROW_ON_ERROR);
