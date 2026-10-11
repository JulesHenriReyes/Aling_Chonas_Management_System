<?php
require dirname(__DIR__, 2).'/2026-10-05/staff-confirmation-before-payment/preview-bootstrap.php';

use App\Models\{Customer, PaymentProof, Product, User};
use App\Services\{DesignChangeService, DesignChangeStore, OrderReviewService, OrderService};
use Illuminate\Support\Facades\{Artisan, Storage};
use Illuminate\Support\Str;

$root = sys_get_temp_dir().'/bakery-reference-retention-'.bin2hex(random_bytes(10));
mkdir($root, 0777, true);
$database = $root.'/workflow-concurrency-order-retention.sqlite';
touch($database);
$storage = $root.'/storage';
$app = previewApplication($database, $storage);
Artisan::call('migrate', ['--force' => true]); // Actual disposable PDO was inspected first.
$owner = User::factory()->create(['email' => 'owner@example.test', 'password' => 'password123', 'role' => 'owner']);
$customer = Customer::create(['first_name' => 'Maria Alexandra', 'last_name' => 'Santos', 'phone_number' => '09171234567']);
$product = Product::create(['product_name' => 'Floral celebration cake with eight cupcakes', 'price' => 1000, 'is_active' => true]);
$option = $product->options()->create(['layers' => 2, 'price' => 1000, 'included_contents' => 'Celebration cake and eight cupcakes', 'is_active' => true]);
$png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/l1sAAAAASUVORK5CYII=');
$orders = [];
foreach (['recent', 'photos', 'history', 'active', 'cancelled', 'hold'] as $kind) {
    $order = app(OrderService::class)->createPublicOrder(['customer_id' => $customer->id,
        'pickup_date' => now()->addDays(5)->toDateString(), 'pickup_time' => '14:00',
        'items' => [['product_id' => $product->id, 'package_option_id' => $option->id, 'quantity' => 1,
            'themes' => 'Blush pink and white flowers', 'special_request' => 'Happy birthday, Maria! Small flowers around the base.']]]);
    $file = 'order_images/'.$order->id.'/reference.png';
    Storage::disk('public')->put($file, $png);
    $order->orderDetails()->first()->images()->create(['order_id' => $order->id,
        'file_path' => $file, 'original_filename' => 'Floral reference.png']);
    if (in_array($kind, ['photos', 'history'], true)) {
        $before = app(DesignChangeService::class)->snapshot($order);
        $proposed = $before;
        $proposed[0]['themes'] = 'Sage green and cream';
        $id = (string) Str::uuid();
        app(DesignChangeStore::class)->locked($order, function ($state) use ($order, $before, $proposed, $id) {
            $state['requests'][$id] = ['id' => $id, 'status' => 'declined',
                'submitted_at' => now()->subDays(400)->toIso8601String(),
                'before' => $before, 'proposed' => $proposed,
                'base_fingerprint' => app(DesignChangeService::class)->fingerprint($before),
                'reviewer_id' => null, 'reviewer_name' => 'Review Owner',
                'decided_at' => now()->subDays(399)->toIso8601String(), 'reason' => 'The requested decoration required a different package.'];
            app(DesignChangeStore::class)->write($order, $state);
        });
    }
    if ($kind === 'cancelled') {
        $order->forceFill(['status' => 'cancelled', 'cancelled_at' => now()->subDays(400)])->save();
    } elseif ($kind !== 'active') {
        app(OrderReviewService::class)->confirm($order, $owner, true);
        $proof = PaymentProof::create(['order_id' => $order->id, 'file_path' => 'private/receipt-'.$order->id.'.png', 'reference_number' => 'QA-'.$order->id]);
        Storage::disk('receipts')->put($proof->file_path, $png);
        app(OrderService::class)->recordDownPayment($order, 500, 'gcash', $proof->reference_number, $owner, null, $proof);
        $order->payments()->create(['amount' => 500, 'payment_type' => 'final_payment', 'payment_method' => 'cash', 'payment_date' => now(), 'user_id' => $owner->id]);
        $order->forceFill(['status' => 'completed', 'completed_at' => now()->subDays($kind === 'recent' ? 30 : ($kind === 'history' ? 400 : 90))])->save();
    }
    if ($kind === 'hold') app(\App\Services\CompletedOrderRetentionService::class)->setHold($order, 'Customer complaint under review');
    $orders[$kind] = ['id' => $order->id, 'token' => $order->private_token, 'number' => $order->order_number];
}
file_put_contents(__DIR__.'/preview-fixture.json', json_encode(compact('root', 'database', 'storage', 'orders'), JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
echo "Guarded completed-order reference preview ready.\n";
