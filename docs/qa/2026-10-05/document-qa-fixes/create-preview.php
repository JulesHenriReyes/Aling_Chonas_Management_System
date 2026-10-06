<?php

require __DIR__.'/preview-bootstrap.php';
$directory = sys_get_temp_dir().'/workflow-concurrency-document-qa-'.bin2hex(random_bytes(6));
mkdir($directory);
$fixture = ['database' => $directory.'/workflow-concurrency-preview.sqlite', 'storage' => $directory.'/storage',
    'url' => 'http://127.0.0.1:8136', 'environment' => 'Synthetic isolated preview'];
touch($fixture['database']);
$app = documentQaApplication($fixture);
if (Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]) !== 0) throw new RuntimeException('Preview migration failed');
foreach (['owner', 'assistant'] as $role) {
    $user = App\Models\User::factory()->create(['first_name' => 'Preview', 'last_name' => ucfirst($role),
        'email' => $role.'@document-qa.test', 'role' => $role, 'is_active' => true, 'password' => 'preview-only-password']);
    if ($role === 'owner') $owner = $user;
}
foreach ([['Flour', 10, 'ingredients', 'kg'], ['Sugar', 3, 'ingredients', 'kg'],
    ['Butter', 0, 'ingredients', 'kg'], ['Cake boxes', 8, 'packaging', 'piece']] as [$name, $quantity, $category, $unit]) {
    $supply = App\Models\Supply::create(['supply_name' => $name, 'category' => $category, 'unit' => $unit,
        'current_quantity' => $quantity, 'reorder_level' => 3, 'is_active' => true]);
    app(App\Services\InventoryService::class)->establishBaseline($supply);
    if ($name === 'Flour') $fixture['supply_id'] = $supply->id;
}
$customer = App\Models\Customer::create(['first_name' => 'Preview', 'last_name' => 'Buyer', 'phone_number' => '09171234567']);
$product = App\Models\Product::create(['product_name' => 'Preview cake', 'price' => 2000, 'is_active' => true]);
$option = $product->options()->create(['layers' => 1, 'price' => 2000, 'included_contents' => 'Cake', 'is_active' => true]);
$order = app(App\Services\OrderService::class)->createPublicOrder(['customer_id' => $customer->id,
    'pickup_date' => now()->addDays(3)->toDateString(), 'pickup_time' => '15:00',
    'items' => [['product_id' => $product->id, 'package_option_id' => $option->id, 'quantity' => 1]]]);
app(App\Services\OrderReviewService::class)->confirm($order, $owner, true);
Illuminate\Support\Facades\Storage::disk('public')->put('business/document-qa.png',
    base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aL1sAAAAASUVORK5CYII='));
App\Models\PaymentSetting::create(['account_name' => 'Synthetic preview', 'account_number' => '09171234567',
    'qr_path' => 'business/document-qa.png', 'updated_by' => $owner->id]);
$fixture['public_page'] = '/order/payment/'.$order->private_token;
file_put_contents(__DIR__.'/preview-fixture.json', json_encode($fixture, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
echo 'Isolated inventory and upload preview created.';
