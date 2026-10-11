<?php
require dirname(__DIR__, 2).'/2026-10-05/staff-confirmation-before-payment/preview-bootstrap.php';

use App\Models\{Customer, PaymentProof, Product, User};
use App\Services\{OrderReviewService, OrderService};
use Illuminate\Support\Facades\{Artisan, Storage};

$root = sys_get_temp_dir().'/bakery-design-changes-'.bin2hex(random_bytes(10));
mkdir($root, 0777, true);
$database = $root.'/workflow-concurrency-design-changes.sqlite';
touch($database);
$storage = $root.'/storage';
$app = previewApplication($database, $storage);
Artisan::call('migrate', ['--force' => true]); // Guard has checked the actual disposable PDO.
Storage::disk('receipts')->put('private/receipt.jpg', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/l1sAAAAASUVORK5CYII='));
$owner = User::factory()->create(['email' => 'owner@example.test', 'password' => 'password123', 'role' => 'owner']);
$assistant = User::factory()->create(['email' => 'assistant@example.test', 'password' => 'password123', 'role' => 'assistant']);
$customer = Customer::create(['first_name' => 'Maria Alexandra', 'last_name' => 'De la Cruz Santiago', 'phone_number' => '09171234567']);
$product = Product::create(['product_name' => 'Floral celebration cake with eight cupcakes', 'price' => 1000, 'is_active' => true]);
$option = $product->options()->create(['layers' => 2, 'price' => 1000, 'included_contents' => 'Celebration cake and eight cupcakes', 'is_active' => true]);
$orders = [];
foreach (['pending', 'deposit', 'preparing', 'decline', 'cancel'] as $kind) {
    $order = app(OrderService::class)->createPublicOrder([
        'customer_id' => $customer->id, 'pickup_date' => now()->addDays(5)->toDateString(), 'pickup_time' => '14:00',
        'items' => [['product_id' => $product->id, 'package_option_id' => $option->id, 'quantity' => 1,
            'themes' => 'Blush pink and white flowers', 'special_request' => 'Happy birthday, Maria! Please use small flowers around the base.']],
    ]);
    if ($kind !== 'pending') {
        app(OrderReviewService::class)->confirm($order, $owner, true);
        $proof = PaymentProof::create(['order_id' => $order->id, 'file_path' => 'private/receipt.jpg', 'reference_number' => 'PREVIEW-'.$order->id]);
        app(OrderService::class)->recordDownPayment($order, 500, 'gcash', $proof->reference_number, $owner, null, $proof);
    }
    if ($kind === 'preparing') app(OrderService::class)->updateStatus($order->fresh(), 'preparing', $assistant);
    $orders[$kind] = ['id' => $order->id, 'token' => $order->private_token, 'number' => $order->order_number];
}
file_put_contents(__DIR__.'/preview-fixture.json', json_encode(compact('root', 'database', 'storage', 'orders'), JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
echo "Guarded design change preview ready.\n";
