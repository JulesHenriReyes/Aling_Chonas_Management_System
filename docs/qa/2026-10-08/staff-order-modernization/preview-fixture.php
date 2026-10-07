<?php
require dirname(__DIR__, 2).'/2026-10-05/staff-confirmation-before-payment/preview-bootstrap.php';
use App\Models\{AddOn, Customer, Product, User};
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
$root = sys_get_temp_dir().'/bakery-staff-workspace-preview-'.bin2hex(random_bytes(12));
mkdir($root, 0777, true);
$database = $root.'/workflow-concurrency-staff.sqlite';
touch($database);
$storage = $root.'/storage';
$app = previewApplication($database, $storage);
Artisan::call('migrate:fresh', ['--force' => true]); // Already guarded against actual PDO and temporary storage.
$owner = User::factory()->create(['first_name' => 'Preview', 'last_name' => 'Owner', 'email' => 'owner@example.test', 'password' => 'password123', 'role' => 'owner', 'is_active' => true]);
$assistant = User::factory()->create(['email' => 'assistant@example.test', 'password' => 'password123', 'role' => 'assistant', 'is_active' => true]);
$buyer = Customer::create(['first_name' => 'Maria Alexandra', 'last_name' => 'De la Cruz Santiago', 'phone_number' => '09171234567']);
Customer::create(['first_name' => 'Maria', 'last_name' => 'Reyes', 'phone_number' => '09181234567']);
$svg = '<svg xmlns="http://www.w3.org/2000/svg" width="600" height="400" viewBox="0 0 600 400"><rect width="600" height="400" fill="#f5ece0"/><ellipse cx="300" cy="337" rx="165" ry="22" fill="#d4c4b0"/><rect x="155" y="168" width="290" height="154" rx="25" fill="#d6a391"/><ellipse cx="300" cy="175" rx="145" ry="40" fill="#ffe1d2"/><rect x="202" y="98" width="196" height="90" rx="15" fill="#dbb3a2"/><ellipse cx="300" cy="103" rx="98" ry="29" fill="#ffe9da"/><path d="M290 69h20v38h-20" fill="#7d4a41"/><circle cx="300" cy="57" r="13" fill="#d89a43"/><path d="M169 217q130 40 266 0M213 139q92 20 174 0" fill="none" stroke="#fff0e5" stroke-width="11"/></svg>';
mkdir($storage.'/app/public/demo', 0777, true);
file_put_contents($storage.'/app/public/demo/cake.svg', $svg);
$free = AddOn::create(['name' => 'Kutsinta (Box of 12)', 'description' => 'Freshly steamed rice cakes, included free with each package.', 'price' => 0, 'is_active' => true, 'photo_path' => 'demo/cake.svg']);
$extra = AddOn::create(['name' => 'Extra cupcakes with buttercream flowers', 'description' => 'Box of six cupcakes.', 'price' => 150, 'is_active' => true, 'photo_path' => 'demo/cake.svg']);
$products = [];
foreach (['Chiffon Celebration Bundle with Free Kutsinta and Buttercream Flower Decorations', 'Chocolate Party Package'] as $index => $name) {
    $product = Product::create(['product_name' => $name, 'description' => 'A celebration cake with soft chiffon layers, hand-piped flowers, and complimentary treats for the whole family.', 'price' => 1000 + 200 * $index, 'is_active' => true, 'photo_path' => 'demo/cake.svg']);
    foreach ([2 => 1000 + 200 * $index, 3 => 1450 + 200 * $index] as $layers => $price) {
        $option = $product->options()->create(['layers' => $layers, 'included_contents' => 'Cake and eight complimentary cupcakes', 'price' => $price, 'is_active' => true]);
        $option->includedItems()->attach($free->id, ['quantity' => 1]);
    }
    $product->addOns()->attach($extra->id);
    $products[] = $product->id;
}
$image = imagecreatetruecolor(160, 100);
imagefill($image, 0, 0, imagecolorallocate($image, 236, 187, 191));
imagefilledellipse($image, 80, 52, 86, 70, imagecolorallocate($image, 251, 226, 214));
imagepng($image, $root.'/design.png');
imagedestroy($image);
$fixture = compact('database', 'storage', 'root', 'products') + ['owner_id' => $owner->id, 'assistant_id' => $assistant->id, 'customer_id' => $buyer->id, 'upload' => $root.'/design.png', 'pickup_date' => \App\Support\PickupCalendar::today()->addDays(3)->toDateString()];
file_put_contents(__DIR__.'/preview-fixture.json', json_encode($fixture, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
echo json_encode($fixture, JSON_PRETTY_PRINT), PHP_EOL;
