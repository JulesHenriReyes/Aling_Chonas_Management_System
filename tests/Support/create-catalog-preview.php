<?php

// Run only against a fresh, isolated SQLite preview database, never the user's DB.
require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (!app()->environment('testing') || config('database.default') !== 'sqlite'
    || !str_contains(config('database.connections.sqlite.database'), 'catalog-preview-')) {
    throw new RuntimeException('An isolated catalog-preview SQLite database is required.');
}
Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
if (App\Models\User::exists()) {
    throw new RuntimeException('Preview database is already populated; reuse the existing preview.');
}
$owner = App\Models\User::factory()->create(['first_name' => 'Preview', 'last_name' => 'Owner', 'email' => 'catalog-owner@example.test', 'password' => 'Preview-only-2026', 'role' => 'owner']);
$customer = App\Models\Customer::create(['first_name' => 'Preview', 'last_name' => 'Buyer', 'phone_number' => '09170000000']);
$package = App\Models\Product::create(['product_name' => 'Floral celebration cake', 'price' => 1200, 'is_active' => true, 'description' => 'A celebration cake with buttercream flowers and cupcakes to share.']);
foreach ([1 => [1200, 6], 2 => [2000, 8], 3 => [3000, 12]] as $layers => [$price, $cupcakes]) {
    $package->options()->create(['layers' => $layers, 'price' => $price, 'included_contents' => "$layers-layer cake and $cupcakes cupcakes", 'is_active' => true]);
}
$extra = App\Models\AddOn::create(['name' => 'Six extra cupcakes', 'description' => 'Six additional cupcakes, beyond those included in your cake package.', 'price' => 300, 'is_active' => true]);
$package->addOns()->attach($extra);
$kakanin = App\Models\AddOn::create(['name' => 'Kakanin sharing tray', 'description' => 'An extra tray for the celebration table.', 'price' => 400, 'is_active' => true]);
$package->addOns()->attach($kakanin);
echo "Isolated preview ready. Owner: catalog-owner@example.test; password: Preview-only-2026\n";
