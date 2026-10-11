<?php
require dirname(__DIR__, 2).'/2026-10-05/staff-confirmation-before-payment/preview-bootstrap.php';

use App\Models\{Customer, Order, Product};
use App\Services\{CompletedOrderRetentionService, DesignChangeStore, OrderService};
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

$fixture = json_decode(file_get_contents(__DIR__.'/preview-fixture.json'), true, 512, JSON_THROW_ON_ERROR);
previewApplication($fixture['database'], $fixture['storage']); // Inspects the actual disposable PDO first.
$manifestPath = __DIR__.'/junction-fixture.json';
if (in_array('--prepare', $argv, true)) {
    $product = Product::firstOrFail();
    $order = app(OrderService::class)->createPublicOrder(['customer_id' => Customer::firstOrFail()->id,
        'pickup_date' => now()->addDays(5)->toDateString(), 'pickup_time' => '14:00',
        'items' => [['product_id' => $product->id, 'package_option_id' => $product->options()->firstOrFail()->id, 'quantity' => 1]]]);
    $order->forceFill(['status' => 'completed', 'completed_at' => now()->subDays(90)])->save();
    $relative = 'order_images/'.$order->id.'/junction-guard.png';
    $order->orderDetails()->first()->images()->create(['order_id' => $order->id,
        'file_path' => $relative, 'original_filename' => 'Junction guard.png']);
    $contents = 'Synthetic protected junction target';
    $targetPath = 'products/retention-junction-'.Str::uuid().'/junction-guard.png';
    Storage::disk('public')->put($targetPath, $contents);
    $manifest = ['order_id' => $order->id, 'junction' => dirname(Storage::disk('public')->path($relative)),
        'target' => dirname(Storage::disk('public')->path($targetPath)), 'file' => Storage::disk('public')->path($targetPath),
        'sha256' => hash('sha256', $contents)];
    file_put_contents($manifestPath, json_encode($manifest, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    echo "Disposable junction fixture prepared.\n";
    exit;
}
$manifest = json_decode(file_get_contents($manifestPath), true, 512, JSON_THROW_ON_ERROR);
$normalized = fn ($path) => strtolower(str_replace('\\', '/', $path));
if (realpath($manifest['junction']) === false || $normalized(realpath($manifest['junction'])) !== $normalized(realpath($manifest['target']))) {
    throw new RuntimeException('Create the disposable junction before verification.');
}
$result = app(CompletedOrderRetentionService::class)->run(true);
$order = Order::findOrFail($manifest['order_id']);
$state = app(DesignChangeStore::class)->read($order, true);
$refused = collect($result['errors'])->contains(fn ($error) => str_contains($error, 'linked reference path') || str_contains($error, 'redirected reference path'));
if (! $refused || ! empty($state['retention']['photos_expired_at'])
    || ! is_file($manifest['file']) || hash_file('sha256', $manifest['file']) !== $manifest['sha256']) {
    throw new RuntimeException('Cleanup did not safely refuse the Windows junction.');
}
file_put_contents(__DIR__.'/junction-results.json', json_encode(['refused_junction' => true,
    'protected_file_unchanged' => true, 'order_not_expired' => true], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
echo "Windows junction refused; target bytes and expiration state unchanged.\n";
