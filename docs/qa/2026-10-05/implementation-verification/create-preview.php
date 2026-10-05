<?php

require __DIR__.'/preview-bootstrap.php';
$directory = sys_get_temp_dir().'/workflow-concurrency-implementation-preview-'.bin2hex(random_bytes(6));
mkdir($directory, 0777, true);
$database = $directory.'/workflow-concurrency-preview.sqlite';
touch($database);
$app = previewApplication($database, $directory.'/storage');
set_exception_handler(function (Throwable $error) { fwrite(STDERR, $error->getMessage().PHP_EOL); exit(1); });
Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
$owner = App\Models\User::factory()->create(['first_name' => 'Preview', 'last_name' => 'Owner', 'email' => 'owner@implementation.test', 'role' => 'owner', 'is_active' => true, 'password' => 'preview-test-only']);
$assistant = App\Models\User::factory()->create(['first_name' => 'Preview', 'last_name' => 'Assistant', 'email' => 'assistant@implementation.test', 'role' => 'assistant', 'is_active' => true, 'password' => 'preview-test-only']);
$customer = App\Models\Customer::create(['first_name' => 'Maria Alexandra', 'last_name' => 'De la Cruz', 'phone_number' => '0322345678']);
$products = [];
foreach (['Floral celebration cake', 'Chocolate birthday cake', 'Pastel cake and cupcakes', 'Classic family celebration'] as $index => $name) {
    $product = App\Models\Product::create(['product_name' => $name, 'description' => 'Freshly baked celebration package with a saved design and inclusions.', 'price' => 2000 + $index * 100, 'is_active' => true]);
    $option = $product->options()->create(['layers' => 1, 'included_contents' => 'Celebration cake and 6 cupcakes', 'price' => 2000 + $index * 100, 'is_active' => true]);
    $included = App\Models\AddOn::create(['name' => 'Included cupcakes '.$index, 'description' => 'Cupcakes included in the saved package', 'price' => 0, 'is_active' => true]);
    $option->includedItems()->attach($included->id, ['quantity' => 6]);
    $products[] = $product;
}
$product = $products[0];
$data = ['customer_id' => $customer->id, 'pickup_date' => App\Support\PickupCalendar::todayString(), 'pickup_time' => '15:00',
    'items' => [['product_id' => $product->id, 'package_option_id' => $product->options()->first()->id, 'quantity' => 1,
        'themes' => 'Blue buttercream flowers', 'special_request' => 'Happy birthday, Maria!']], 'notes_text' => 'Keep the cake level during pickup.'];
$orders = app(App\Services\OrderService::class);
$refunds = app(App\Services\RefundService::class);
$manifest = ['environment' => 'Synthetic isolated SQLite preview', 'url' => 'http://127.0.0.1:8124', 'database' => $database, 'storage' => $directory.'/storage', 'orders' => [], 'public' => [], 'product' => $product->id, 'customer' => $customer->id];
foreach (['pending', 'confirmed', 'preparing', 'ready', 'completed', 'cancelled', 'failure-pending', 'failure-completed'] as $state) {
    $order = $orders->createInternalOrder($data, $owner);
    if ($state !== 'pending') $orders->recordDownPayment($order, 1000, 'cash', null, $owner);
    if (in_array($state, ['preparing', 'ready', 'completed'])) $orders->updateStatus($order, 'preparing', $assistant);
    if (in_array($state, ['ready', 'completed'])) $orders->updateStatus($order, 'ready_for_pickup', $assistant);
    if ($state === 'completed') $orders->completePickup($order, 'cash', null, $owner, true);
    if ($state === 'cancelled') $orders->cancelOrder($order, $owner);
    if (str_starts_with($state, 'failure-')) {
        $refund = $refunds->markBakeryFailure($order, 'Oven failure; this cake cannot be supplied.', $owner, true);
        if ($state === 'failure-completed') $refunds->complete($refund, ['method' => 'cash', 'reference_number' => 'RETURN-PREVIEW', 'transfer_confirmed' => '1'], $owner);
    }
    $manifest['orders'][$state] = $order->id;
}
// Public bearer links belong only to synthetic orders in disposable storage.
foreach (['pending', 'awaiting', 'rejected', 'verified', 'cancelled', 'failure-pending', 'failure-completed'] as $state) {
    $order = $orders->createPublicOrder($data);
    if ($state !== 'pending') {
        $png = $directory.'/receipt.png';
        file_put_contents($png, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aL1sAAAAASUVORK5CYII='));
        $proof = app(App\Services\PaymentReviewService::class)->submit($order, new Illuminate\Http\UploadedFile($png, 'receipt.png', 'image/png', null, true), 'PREVIEW-'.$state);
        if ($state === 'rejected') app(App\Services\PaymentReviewService::class)->reject($proof, 'Reference not found. Contact the bakery if the transfer succeeded.', $owner);
        elseif ($state !== 'awaiting') app(App\Services\PaymentReviewService::class)->accept($proof, 1000, 'PREVIEW-'.$state, $owner);
    }
    if ($state === 'cancelled') $orders->cancelOrder($order, $owner);
    if (str_starts_with($state, 'failure-')) {
        $refund = $refunds->markBakeryFailure($order, 'Cake cannot be supplied after oven failure.', $owner, true);
        if ($state === 'failure-completed') $refunds->complete($refund, ['method' => 'cash', 'reference_number' => 'PUBLIC-RETURN', 'transfer_confirmed' => '1'], $owner);
    }
    $manifest['public'][$state] = '/order/payment/'.$order->private_token;
}
foreach (['Flour', 'Sugar', 'Cake boxes', 'Butter'] as $index => $name) {
    $supply = App\Models\Supply::create(['supply_name' => $name, 'category' => $index === 2 ? 'packaging' : 'ingredients', 'unit' => $index === 2 ? 'piece' : 'kg', 'reorder_level' => 3, 'current_quantity' => 10, 'is_active' => true]);
    app(App\Services\InventoryService::class)->establishBaseline($supply);
    $manifest['supply'] ??= $supply->id;
}
for ($index = 0; $index < 6; $index++) {
    $expense = app(App\Services\ExpenseService::class)->create(['submission_key' => (string) Illuminate\Support\Str::uuid(), 'description' => 'Preview ingredient purchase '.($index + 1), 'category' => 'ingredients', 'amount' => 125.50, 'expense_date' => App\Support\PickupCalendar::todayString()], $assistant);
    $manifest['expense'] ??= $expense->id;
}
file_put_contents(__DIR__.'/evidence/preview-fixture.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
echo json_encode(['url' => $manifest['url'], 'database' => $database, 'orders' => count($manifest['orders']), 'public_states' => count($manifest['public'])]);
