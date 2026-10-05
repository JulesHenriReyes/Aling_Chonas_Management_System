<?php

require __DIR__.'/preview-bootstrap.php';
$fixture = json_decode(file_get_contents(__DIR__.'/evidence/preview-fixture.json'), true, 512, JSON_THROW_ON_ERROR);
previewApplication($fixture['database'], $fixture['storage']);
$owner = App\Models\User::where('email', 'owner@implementation.test')->sole();
$product = App\Models\Product::findOrFail($fixture['product']);
$orders = app(App\Services\OrderService::class);
$order = $orders->createPublicOrder(['customer_id' => $fixture['customer'], 'pickup_date' => now()->addDays(2)->toDateString(), 'pickup_time' => '15:00',
    'items' => [['product_id' => $product->id, 'package_option_id' => $product->options()->first()->id, 'quantity' => 1]]]);
$receipt = $fixture['storage'].'/completed-receipt.png';
file_put_contents($receipt, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aL1sAAAAASUVORK5CYII='));
$review = app(App\Services\PaymentReviewService::class);
$reference = 'COMPLETED-PUBLIC-'.$order->id;
$proof = $review->submit($order, new Illuminate\Http\UploadedFile($receipt, 'completed-receipt.png', 'image/png', null, true), $reference);
$review->accept($proof, 1000, $reference, $owner);
$orders->updateStatus($order, 'preparing', $owner);
$orders->updateStatus($order, 'ready_for_pickup', $owner);
$orders->completePickup($order, 'cash', null, $owner, true);
file_put_contents(__DIR__.'/evidence/completed-public-preview.json', json_encode(['environment' => 'Synthetic isolated SQLite preview',
    'public_url' => '/order/payment/'.$order->fresh()->private_token, 'order_id' => $order->id, 'status' => $order->fresh()->status,
    'paid' => $order->fresh()->amount_paid, 'payment_count' => $order->payments()->count()], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
echo "Public completed fixture: exact deposit and actual-pickup balance, two payments, total 2000.\n";
