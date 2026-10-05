<?php

require dirname(__DIR__, 4).'/tests/Support/mariadb-bootstrap.php';
disposableMariaDbApplication();

use App\Models\{Customer, InventoryOperation, InventoryTransaction, Order, Payment, Product, Refund, Supply, User};
use App\Services\{OrderService, RefundService};
use Illuminate\Support\Facades\{Artisan, DB};
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

function check(bool $condition, string $message): void { if (!$condition) throw new RuntimeException($message); }
Artisan::call('migrate', ['--force' => true]);
$owner = User::factory()->create(['role' => 'owner', 'is_active' => true]);
$assistant = User::factory()->create(['role' => 'assistant', 'is_active' => true]);
$customer = Customer::create(['first_name' => 'Contention', 'last_name' => 'Buyer', 'phone_number' => '0322345678']);
$product = Product::create(['product_name' => 'Contention fixture', 'price' => 2000, 'is_active' => true]);
$option = $product->options()->create(['layers' => 1, 'price' => 2000, 'included_contents' => 'Cake', 'is_active' => true]);
$orders = app(OrderService::class);
$makeOrder = fn () => $orders->createInternalOrder(['customer_id' => $customer->id, 'pickup_date' => now()->addDays(2)->toDateString(),
    'pickup_time' => '15:00', 'expected_total' => 2000, 'items' => [['product_id' => $product->id, 'package_option_id' => $option->id, 'quantity' => 1]]], $owner);
$makeSupply = fn () => Supply::create(['supply_name' => 'Contention flour '.Str::uuid(), 'category' => 'ingredients', 'unit' => 'kg',
    'current_quantity' => 10, 'reorder_level' => 5, 'is_active' => true]);
$inventory = fn (Supply $supply, string $type, int $quantity) => ['action' => 'inventory', 'data' => [
    'submission_key' => (string) Str::uuid(), 'type' => $type, 'operation_date' => '2026-10-05', 'notes' => 'Concurrent fixture',
    'lines' => [['supply_id' => $supply->id, 'quantity' => $quantity, 'expected_version' => 0]]]];
$evidence = [];

function race(string $case, User $actor, array $payloads, string $lockTable, int $lockId): array
{
    global $evidence;
    $directory = sys_get_temp_dir().'/bakery-implementation-contention-'.Str::uuid();
    mkdir($directory);
    $workers = [];
    DB::beginTransaction();
    DB::table($lockTable)->where('id', $lockId)->lockForUpdate()->first();
    try {
        foreach ($payloads as $index => $payload) {
            $worker = new Process([PHP_BINARY, base_path('tests/Support/mariadb-concurrency-worker.php'), $directory,
                (string) $index, (string) $actor->id, json_encode($payload, JSON_THROW_ON_ERROR)], base_path(), null, null, 25);
            $worker->start();
            $workers[] = $worker;
        }
        $deadline = microtime(true) + 15;
        while (count(glob($directory.'/ready-*')) !== count($workers)) {
            foreach ($workers as $worker) check($worker->isRunning(), 'Worker exited before barrier: '.$worker->getErrorOutput());
            check(microtime(true) < $deadline, 'Workers did not reach barrier.');
            usleep(10000);
        }
        touch($directory.'/release');
        usleep(300000);
        $blocked = count(glob($directory.'/result-*.json')) === 0;
        check($blocked, $case.' must wait for the held InnoDB row lock.');
        DB::commit();
        $results = [];
        foreach ($workers as $worker) {
            $worker->wait();
            check($worker->getExitCode() === 0, $worker->getErrorOutput().$worker->getOutput());
            $results[] = json_decode($worker->getOutput(), true, 512, JSON_THROW_ON_ERROR);
        }
        $evidence[$case] = ['held_lock_blocked_both_workers' => $blocked, 'results' => $results];
        return $results;
    } finally {
        if (DB::transactionLevel()) DB::rollBack();
        foreach ($workers as $worker) if ($worker->isRunning()) $worker->stop();
    }
}
function oneRejected(array $results): void
{
    $statuses = array_column($results, 'status'); sort($statuses);
    check($statuses === ['posted', 'rejected'], 'Exactly one worker must post.');
}
$supply = $makeSupply();
$result = race('additive-receipts', $assistant, [$inventory($supply, 'receipt', 3), $inventory($supply, 'receipt', 4)], 'supplies', $supply->id);
check(array_column($result, 'status') === ['posted', 'posted'] && (float) $supply->fresh()->current_quantity === 17.0 && $supply->fresh()->stock_version === 2, 'Receipts lost an update.');
check(InventoryTransaction::where('supply_id', $supply->id)->count() === 2, 'Receipt history mismatch.');
$supply = $makeSupply();
oneRejected(race('usage-overdraw', $assistant, [$inventory($supply, 'usage', 7), $inventory($supply, 'usage', 7)], 'supplies', $supply->id));
check((float) $supply->fresh()->current_quantity === 3.0 && InventoryTransaction::where('supply_id', $supply->id)->count() === 1, 'Usage overspent stock.');
$supply = $makeSupply(); $payload = $inventory($supply, 'receipt', 3);
$result = race('duplicate-receipt', $assistant, [$payload, $payload], 'supplies', $supply->id);
check($result[0]['id'] === $result[1]['id'] && (float) $supply->fresh()->current_quantity === 13.0 && InventoryTransaction::where('supply_id', $supply->id)->count() === 1, 'Duplicate stock post.');
$supply = $makeSupply();
oneRejected(race('stale-stocktake', $assistant, [$inventory($supply, 'stocktake', 8), $inventory($supply, 'stocktake', 9)], 'supplies', $supply->id));
check(in_array((float) $supply->fresh()->current_quantity, [8.0, 9.0], true) && $supply->fresh()->stock_version === 1, 'Stale count was applied.');
$order = $makeOrder();
oneRejected(race('duplicate-booking', $owner, [['action' => 'deposit', 'order' => $order->id], ['action' => 'deposit', 'order' => $order->id]], 'orders', $order->id));
check((float) $order->fresh()->amount_paid === 1000.0 && Payment::where('order_id', $order->id)->count() === 1, 'Duplicate booking charge.');
$orders->updateStatus($order, 'preparing', $assistant); $orders->updateStatus($order, 'ready_for_pickup', $assistant);
oneRejected(race('duplicate-pickup', $owner, [['action' => 'pickup', 'order' => $order->id], ['action' => 'pickup', 'order' => $order->id]], 'orders', $order->id));
check($order->fresh()->status === 'completed' && (float) $order->fresh()->amount_paid === 2000.0 && Payment::where('order_id', $order->id)->count() === 2, 'Pickup was charged twice or not completed.');
$order = $makeOrder(); $orders->recordDownPayment($order, 1000, 'cash', null, $owner);
oneRejected(race('duplicate-bakery-failure', $owner, [['action' => 'failure', 'order' => $order->id], ['action' => 'failure', 'order' => $order->id]], 'orders', $order->id));
check(Refund::where('order_id', $order->id)->count() === 1 && (float) $order->fresh()->refund->amount === 1000.0, 'Failure refund duplicated.');
$refund = $order->fresh()->refund;
oneRejected(race('duplicate-refund-completion', $owner, [['action' => 'refund', 'refund' => $refund->id], ['action' => 'refund', 'refund' => $refund->id]], 'orders', $order->id));
check($refund->fresh()->status === 'completed' && Refund::where('order_id', $order->id)->count() === 1, 'Refund completion duplicated.');
file_put_contents(__DIR__.'/evidence/mariadb-contention.json', json_encode(['engine' => DB::selectOne('SELECT VERSION() AS version')->version,
    'cases' => $evidence, 'outcome' => 'All held-lock, amount, state, history and replay assertions passed'], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
echo "PASS: 8 contention cases, 16 independently guarded workers; stock, payment and refund amounts/history reconciled.\n";
