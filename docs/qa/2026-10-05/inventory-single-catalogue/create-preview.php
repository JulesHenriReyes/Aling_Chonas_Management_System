<?php

require __DIR__.'/../document-qa-fixes/preview-bootstrap.php';
$directory = sys_get_temp_dir().'/workflow-concurrency-inventory-single-catalogue-'.bin2hex(random_bytes(6));
mkdir($directory);
$fixture = ['database' => $directory.'/workflow-concurrency-preview.sqlite', 'storage' => $directory.'/storage',
    'url' => 'http://127.0.0.1:8140', 'environment' => 'Synthetic isolated inventory preview'];
touch($fixture['database']);
$app = documentQaApplication($fixture);
if (Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]) !== 0) throw new RuntimeException('Preview migration failed');
foreach (['owner', 'assistant'] as $role) {
    App\Models\User::factory()->create(['first_name' => 'Preview', 'last_name' => ucfirst($role),
        'email' => $role.'@inventory-qa.test', 'role' => $role, 'is_active' => true, 'password' => 'preview-only-password']);
}
foreach ([['Flour', 10, 'kg'], ['Sugar', 3, 'kg'], ['Cocoa', 2, 'kg'], ['Egg', 24, 'piece'],
    ['Vegetable oil', 0, 'kg'], ['Baking powder', 500, 'g'], ['Cake boxes', 8, 'piece']] as [$name, $quantity, $unit]) {
    $supply = App\Models\Supply::create(['supply_name' => $name, 'category' => $name === 'Cake boxes' ? 'packaging' : 'ingredients',
        'unit' => $unit, 'current_quantity' => $quantity, 'reorder_level' => $name === 'Baking powder' ? 100 : 3, 'is_active' => true]);
    app(App\Services\InventoryService::class)->establishBaseline($supply);
    $fixture['supply_ids'][$name] = $supply->id;
}
App\Models\Supply::create(['supply_name' => 'Inactive flour', 'category' => 'ingredients', 'unit' => 'kg',
    'current_quantity' => 10, 'reorder_level' => 1, 'is_active' => false]);
file_put_contents(__DIR__.'/preview-fixture.json', json_encode($fixture, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
echo 'Isolated inventory refinement preview created.';
