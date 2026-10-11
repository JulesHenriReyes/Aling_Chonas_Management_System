<?php
require dirname(__DIR__, 4).'/tests/Support/mariadb-bootstrap.php';

use App\Models\{Customer, Order, Product};
use App\Services\{CompletedOrderRetentionService, OrderService};
use Illuminate\Support\Facades\{Artisan, DB, Storage};
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

$app = disposableMariaDbApplication();
$sharedStorage = $app->storagePath();
Artisan::call('migrate', ['--force' => true]); // Guard checked actual server/schema/datadir.
$customer = Customer::create(['first_name' => 'Retention', 'last_name' => 'Fixture', 'phone_number' => '09171234567']);
$product = Product::create(['product_name' => 'Retention cake', 'price' => 1000, 'is_active' => true]);
$option = $product->options()->create(['layers' => 1, 'price' => 1000, 'included_contents' => 'Celebration cake', 'is_active' => true]);
$newOrder = function () use ($customer, $product, $option) {
    $order = app(OrderService::class)->createPublicOrder(['customer_id' => $customer->id,
        'pickup_date' => now()->addDays(3)->toDateString(), 'pickup_time' => '14:00',
        'items' => [['product_id' => $product->id, 'package_option_id' => $option->id, 'quantity' => 1]]]);
    $order->forceFill(['status' => 'completed', 'completed_at' => now()->subDays(90)])->save();
    $path = 'order_images/'.$order->id.'/'.Str::uuid().'.png';
    Storage::disk('public')->put($path, 'Synthetic reference photo');
    $order->orderDetails()->first()->images()->create(['order_id' => $order->id, 'file_path' => $path, 'original_filename' => 'Fixture.png']);
    return [$order, $path];
};
$evidence = [];
function check(bool $ok, string $message): void { if (! $ok) throw new RuntimeException($message); }
function race(string $case, Order $order, int $count, callable $commitAction): array
{
    global $sharedStorage, $evidence;
    $directory = sys_get_temp_dir().'/bakery-retention-race-'.Str::uuid();
    mkdir($directory);
    $workers = [];
    DB::beginTransaction();
    DB::table('orders')->where('id', $order->id)->lockForUpdate()->first();
    try {
        for ($index = 0; $index < $count; $index++) {
            $worker = new Process([PHP_BINARY, __DIR__.'/mariadb-worker.php', $directory, (string) $index, $sharedStorage], base_path(), null, null, 30);
            $worker->start();
            $workers[] = $worker;
        }
        $deadline = microtime(true)+15;
        while (count(glob($directory.'/ready-*')) !== $count) {
            foreach ($workers as $worker) check($worker->isRunning(), 'Worker failed: '.$worker->getErrorOutput().$worker->getOutput());
            check(microtime(true)<$deadline, 'Workers did not reach the barrier.');
            usleep(10000);
        }
        touch($directory.'/release');
        usleep(400000);
        $early = glob($directory.'/result-*');
        if ($count === 1) check(count($early) === 0, 'Cleanup must wait for the InnoDB row lock.');
        else {
            check(count($early) === 1, 'Exactly one overlapping run should skip while the other waits.');
            check(json_decode(file_get_contents($early[0]), true)['already_running'], 'The second run must respect the filesystem mutex.');
        }
        $commitAction();
        DB::commit();
        $results = [];
        foreach ($workers as $worker) {
            $worker->wait();
            check($worker->getExitCode() === 0, $worker->getErrorOutput().$worker->getOutput());
            $result = json_decode($worker->getOutput(), true, 512, JSON_THROW_ON_ERROR);
            check($result['errors'] === [], 'Cleanup worker reported errors.');
            $results[] = $result;
        }
        $evidence[$case] = ['waited_for_row_lock' => true, 'results' => $results];
        return $results;
    } finally {
        if (DB::transactionLevel()) DB::rollBack();
        foreach ($workers as $worker) if ($worker->isRunning()) $worker->stop();
    }
}

[$order, $photo] = $newOrder();
race('status-rechecked-after-lock', $order, 1, fn () => DB::table('orders')->where('id', $order->id)->update(['status' => 'cancelled']));
check(Storage::disk('public')->exists($photo), 'A cancelled order was swept.');

[$order, $photo] = $newOrder();
race('hold-rechecked-after-lock', $order, 1, fn () => app(CompletedOrderRetentionService::class)->setHold($order, 'Complaint opened during cleanup'));
check(Storage::disk('public')->exists($photo), 'A retention hold was ignored.');

[$order, $photo] = $newOrder();
$results = race('overlapping-cleanup-jobs', $order, 2, fn () => null);
check(array_sum(array_column($results, 'files')) === 1, 'One physical photo should be deleted once.');
check(! Storage::disk('public')->exists($photo), 'Eligible completed photo did not expire.');

[$order, $photo] = $newOrder();
race('completion-date-rechecked-after-lock', $order, 1,
    fn () => DB::table('orders')->where('id', $order->id)->update(['completed_at' => now()]));
check(Storage::disk('public')->exists($photo), 'A newly completed order was swept.');

file_put_contents(__DIR__.'/mariadb-results.json', json_encode($evidence, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
echo json_encode(['cases' => count($evidence), 'workers' => 5, 'errors' => []], JSON_THROW_ON_ERROR)."\n";
