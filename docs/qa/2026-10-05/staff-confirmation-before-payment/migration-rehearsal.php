<?php

if (getenv('BAKERY_QA_MYSQL_MANIFEST')) {
    require dirname(__DIR__, 4).'/tests/Support/mariadb-bootstrap.php';
    disposableMariaDbApplication();
} else {
    require __DIR__.'/preview-bootstrap.php';
    $directory = sys_get_temp_dir().'/workflow-concurrency-review-upgrade-'.bin2hex(random_bytes(6));
    mkdir($directory);
    touch($directory.'/workflow-concurrency-upgrade.sqlite');
    previewApplication($directory.'/workflow-concurrency-upgrade.sqlite', $directory.'/storage');
}

use App\Models\{Customer, Order, Product, User};
use Illuminate\Support\Facades\{Artisan, DB, Schema};

Artisan::call('migrate', ['--force' => true]);
$migration = require database_path('migrations/2026_10_05_000001_add_order_staff_review.php');
$migration->down(); // Rehearse the exact pre-upgrade schema only in the guarded fixture database.
$owner = User::factory()->create(['role' => 'owner', 'is_active' => true]);
$customer = Customer::create(['first_name' => 'Migration', 'last_name' => 'Fixture', 'phone_number' => '09171234567']);
$product = Product::create(['product_name' => 'Historical package', 'price' => 2000, 'is_active' => true]);
foreach (['pending', 'confirmed', 'completed', 'cancelled'] as $status) {
    $order = Order::create(['order_number' => 'UPGRADE-'.strtoupper($status), 'customer_id' => $customer->id, 'user_id' => null,
        'status' => $status, 'pickup_date' => '2026-12-01', 'pickup_time' => '15:00', 'private_token' => bin2hex(random_bytes(32)),
        'completed_at' => $status === 'completed' ? '2026-09-30 07:00:00' : null,
        'cancelled_at' => $status === 'cancelled' ? '2026-09-30 08:00:00' : null,
        'cancellation_kind' => $status === 'cancelled' ? 'bakery_failure' : null]);
    $order->orderDetails()->create(['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 2000]);
    if ($status !== 'pending') $order->payments()->create(['user_id' => $owner->id, 'amount' => 1000, 'payment_type' => 'down_payment', 'payment_method' => 'cash', 'payment_date' => '2026-09-30 06:00:00']);
    $order->paymentProofs()->create(['file_path' => 'historical-fixture.png', 'reference_number' => 'OLD-'.$status]);
    if ($status === 'cancelled') $order->refund()->create(['amount' => 1000, 'reason' => 'Historical failure', 'requested_by' => $owner->id]);
}
$tables = ['users', 'customers', 'products', 'orders', 'order_details', 'payments', 'payment_proofs', 'refunds'];
$before = [];
foreach ($tables as $table) $before[$table] = DB::table($table)->get()->map(fn ($row) => (array) $row)->all();
$migration->up();
foreach ($tables as $table) {
    $after = DB::table($table)->get()->map(function ($row) use ($table) {
        $row = (array) $row;
        if ($table === 'orders') {
            if ($row['review_status'] !== null || $row['reviewed_by'] !== null || $row['reviewed_at'] !== null) throw new RuntimeException('False legacy approval created.');
            unset($row['review_status'], $row['reviewed_by'], $row['reviewed_at']);
        }
        return $row;
    })->all();
    if ($before[$table] !== $after) throw new RuntimeException('Old values changed in '.$table);
}
$proof = ['engine' => DB::connection()->getDriverName(), 'old_tables_unchanged' => $tables,
    'review_columns_present' => Schema::hasColumns('orders', ['review_status', 'reviewed_by', 'reviewed_at']),
    'legacy_audit_fields_null' => true, 'old_order_rows_checked' => count($before['orders'])];
file_put_contents(__DIR__.'/verification/evidence/migration-'.$proof['engine'].'.json', json_encode($proof, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
echo json_encode($proof, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
