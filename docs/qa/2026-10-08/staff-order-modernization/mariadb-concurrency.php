<?php
require dirname(__DIR__, 4).'/tests/Support/mariadb-bootstrap.php';
disposableMariaDbApplication();
use App\Models\{Customer, Order, Product, User};
use App\Services\{FinancialReportService, OrderDraftService, OrderService};
use Illuminate\Http\Request;
use Illuminate\Session\{ArraySessionHandler, Store};
use Illuminate\Support\Facades\{Artisan, DB, Storage};
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
function verify(bool $value, string $message): void {if (!$value) throw new RuntimeException($message);}
Artisan::call('migrate:fresh', ['--force' => true]); // DisposableMariaDb verified the actual server first.
$owner = User::factory()->create(['role' => 'owner', 'is_active' => true]);
$customer = Customer::create(['first_name' => 'Concurrent', 'last_name' => 'Synthetic Buyer', 'phone_number' => '09171234567']);
$product = Product::create(['product_name' => 'Concurrent synthetic package', 'price' => 1000, 'is_active' => true]);
$option = $product->options()->create(['layers' => 2, 'price' => 1000, 'included_contents' => 'Cake', 'is_active' => true]);
$request = Request::create('/orders/create');
$session = new Store('staff-concurrency', new ArraySessionHandler(120)); $session->start();
$request->setLaravelSession($session); $request->setUserResolver(fn () => $owner);
$draft = app(OrderDraftService::class)->start($request);
$request->merge(['submission_key' => $draft['submission_key']]);
$key = app(OrderDraftService::class)->submissionKey($request, $draft);
$directory = sys_get_temp_dir().'/bakery-staff-contention-'.Str::uuid(); mkdir($directory);
config(['filesystems.disks.staff_drafts.root' => $directory.'/staged', 'filesystems.disks.staff_references.root' => $directory.'/references']);
Storage::disk('staff_drafts')->put('reference.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aL1sAAAAASUVORK5CYII='));
$data = ['submission_key' => $key, 'customer_id' => $customer->id, 'pickup_date' => \App\Support\PickupCalendar::today()->addDays(3)->toDateString(), 'pickup_time' => '14:00', 'expected_total' => 1000,
    'items' => [['product_id' => $product->id, 'package_option_id' => $option->id, 'quantity' => 1, 'themes' => 'Preserved design', 'images' => [['staged_disk' => 'staff_drafts', 'staged_path' => 'reference.png', 'original_filename' => 'reference.png']]]]];
function race(string $directory, User $actor, array $payload, string $table, int $id): array {
    $workers = []; DB::beginTransaction(); DB::table($table)->where('id', $id)->lockForUpdate()->first();
    try {
        foreach ([0,1] as $index) {
            $worker = new Process([PHP_BINARY, __DIR__.'/submission-worker.php', $directory, (string)$index, (string)$actor->id, json_encode($payload, JSON_THROW_ON_ERROR)], base_path(), null, null, 30);
            $worker->start(); $workers[] = $worker;
        }
        $deadline = microtime(true)+15;
        while (count(glob($directory.'/ready-*')) !== 2) {foreach ($workers as $worker) verify($worker->isRunning(), $worker->getErrorOutput().$worker->getOutput()); verify(microtime(true)<$deadline,'Barrier timed out'); usleep(10000);}
        touch($directory.'/release'); usleep(300000);
        verify(count(glob($directory.'/result-*.json')) === 0, 'Workers did not wait for the held InnoDB row lock');
        DB::commit();
        return array_map(function ($worker) {$worker->wait(); verify($worker->getExitCode()===0, $worker->getErrorOutput().$worker->getOutput()); return json_decode($worker->getOutput(), true, 512, JSON_THROW_ON_ERROR);}, $workers);
    } finally {if (DB::transactionLevel()) DB::rollBack(); foreach ($workers as $worker) if ($worker->isRunning()) $worker->stop();}
}
$creation = race($directory, $owner, ['action' => 'create', 'data' => $data], 'products', $product->id);
verify($creation[0]['id'] === $creation[1]['id'] && Order::count()===1, 'Concurrent submissions did not return exactly one original order');
$order = Order::sole();
verify($order->hasApprovedReview() && $order->amount_paid === 0.0 && $order->user_id === $owner->id, 'Creation lost attribution or invented a payment');
verify($order->images()->count() === 1 && count(Storage::disk('staff_references')->allFiles()) === 1, 'Creation duplicated or leaked a reference file');
app(OrderService::class)->recordDownPayment($order, 500, 'cash', null, $owner);
$cancellationDirectory = sys_get_temp_dir().'/bakery-staff-contention-'.Str::uuid(); mkdir($cancellationDirectory);
$cancellation = race($cancellationDirectory, $owner, ['action' => 'cancel', 'order_id' => $order->id], 'orders', $order->id);
verify($cancellation[0]['id'] === $cancellation[1]['id'] && $order->payments()->count()===1, 'Concurrent cancellation changed the payment ledger');
$day = now(config('bakery.business_timezone'))->toDateString();
$reports = app(FinancialReportService::class);
verify($reports->getCancellationIncome($day,$day)===500.0 && $reports->getPaymentCollections($day,$day)===500.0 && $reports->getSales($day,$day)===0.0, 'Retained deposit was counted incorrectly');
$evidence = ['guarded_server' => true, 'held_locks_blocked_workers' => true, 'submission_key_server_issued_owner_session_scoped' => true, 'creation' => $creation, 'saved_order_count' => 1, 'saved_reference_count' => 1, 'no_payment_at_creation' => true, 'cancellation' => $cancellation, 'retained_deposit' => 500, 'collections' => 500, 'completed_sales' => 0];
file_put_contents(__DIR__.'/mariadb-concurrency.json', json_encode($evidence, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
echo json_encode($evidence, JSON_PRETTY_PRINT), PHP_EOL;
