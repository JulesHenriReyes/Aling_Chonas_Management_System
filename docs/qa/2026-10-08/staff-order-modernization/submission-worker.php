<?php
require dirname(__DIR__, 4).'/tests/Support/mariadb-bootstrap.php';
disposableMariaDbApplication();
use App\Models\{Order, User};
use App\Services\OrderService;
use Illuminate\Support\Facades\Storage;
[$script, $directory, $index, $actorId, $json] = $argv;
$expected = strtolower(str_replace('\\', '/', realpath(sys_get_temp_dir()))).'/';
$actual = strtolower(str_replace('\\', '/', realpath($directory)));
if (!str_starts_with($actual, $expected) || !str_starts_with(basename($actual), 'bakery-staff-contention-')) throw new RuntimeException('Unsafe worker directory');
config(['filesystems.disks.staff_drafts.root' => $directory.'/staged', 'filesystems.disks.staff_references.root' => $directory.'/references']);
$payload = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
$actor = User::findOrFail($actorId);
touch($directory.'/ready-'.$index);
$deadline = microtime(true)+15;
while (!file_exists($directory.'/release')) {if (microtime(true)>$deadline) throw new RuntimeException('Barrier timeout'); usleep(10000);}
$order = $payload['action'] === 'create'
    ? app(OrderService::class)->createInternalOrder($payload['data'], $actor)
    : app(OrderService::class)->cancelOrder(Order::findOrFail($payload['order_id']), $actor);
$result = ['id' => $order->id, 'status' => $order->status, 'payments' => $order->payments()->count()];
file_put_contents($directory.'/result-'.$index.'.json', json_encode($result, JSON_THROW_ON_ERROR));
echo json_encode($result, JSON_THROW_ON_ERROR);
