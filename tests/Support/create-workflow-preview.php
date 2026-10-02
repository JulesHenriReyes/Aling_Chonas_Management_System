<?php

// Explicitly isolated browser fixture. Never runs against the application database.
require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (!app()->environment('testing') || config('database.default') !== 'sqlite'
    || !str_contains(config('database.connections.sqlite.database'), 'workflow-preview-')) {
    throw new RuntimeException('An isolated workflow-preview SQLite database is required.');
}
Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
if (App\Models\User::exists()) throw new RuntimeException('Preview already exists; reuse it.');
$owner = App\Models\User::factory()->create(['first_name'=>'Preview','last_name'=>'Owner','email'=>'workflow-owner@example.test','password'=>'Preview-only-2026','role'=>'owner']);
App\Models\User::factory()->create(['first_name'=>'Preview','last_name'=>'Assistant','email'=>'workflow-assistant@example.test','password'=>'Preview-only-2026','role'=>'assistant']);
(new Database\Seeders\BakeryCatalogSeeder)->run();
$inventory=app(App\Services\InventoryService::class);
foreach(App\Models\Supply::all() as $supply) $inventory->establishBaseline($supply);
$supplies=App\Models\Supply::take(3)->get();
$inventory->post(['submission_key'=>(string)Illuminate\Support\Str::uuid(),'type'=>'receipt','operation_date'=>'2026-09-15','supplier'=>'Bakery wholesale supply','delivery_reference'=>'DEL-0915','notes'=>'Weekly delivery','lines'=>$supplies->map(fn($s)=>['supply_id'=>$s->id,'quantity'=>5])->all()],$owner);
$inventory->post(['submission_key'=>(string)Illuminate\Support\Str::uuid(),'type'=>'usage','operation_date'=>'2026-09-16','notes'=>'Morning production','lines'=>[['supply_id'=>$supplies[0]->id,'quantity'=>2]]],$owner);
$inventory->post(['submission_key'=>(string)Illuminate\Support\Str::uuid(),'type'=>'stocktake','operation_date'=>'2026-09-17','notes'=>'Physical count after damaged packaging was discarded','lines'=>[['supply_id'=>$supplies[1]->id,'quantity'=>0,'expected_version'=>$supplies[1]->fresh()->stock_version]]],$owner);
App\Models\Supply::create(['supply_name'=>'Cake boxes · 10 inch','category'=>'packaging','unit'=>'piece','current_quantity'=>8,'reorder_level'=>12,'is_active'=>true]);
App\Models\Supply::create(['supply_name'=>'Retired ribbon colour','category'=>'packaging','unit'=>'metre','current_quantity'=>3,'reorder_level'=>1,'is_active'=>false]);
foreach(App\Models\Supply::all() as $supply) $inventory->establishBaseline($supply);
$expenses=app(App\Services\ExpenseService::class);
foreach([['Flour and sugar delivery','ingredients',1850,'2026-09-15'],['Cake boxes and cupcake liners','packaging',650,'2026-09-16'],['Replacement mixer attachment','equipment',1200,'2026-09-20'],['Bakery cleaning supplies','miscellaneous',280,'2026-09-22'],['October ingredient top-up','ingredients',1500,'2026-10-01']] as [$description,$category,$amount,$date]) $expenses->create(['submission_key'=>(string)Illuminate\Support\Str::uuid(),'description'=>$description,'category'=>$category,'amount'=>$amount,'expense_date'=>$date],$owner);
$void=$expenses->create(['submission_key'=>(string)Illuminate\Support\Str::uuid(),'description'=>'Duplicate packaging invoice','category'=>'packaging','amount'=>650,'expense_date'=>'2026-09-16'],$owner);
$expenses->void($void,0,'Duplicate supplier invoice',$owner);
$customer=App\Models\Customer::create(['first_name'=>'Maria','last_name'=>'Santos','phone_number'=>'09170000000']);
$products=App\Models\Product::with('options')->get();
foreach([5,10,15,20,25,30] as $index=>$day) {
    $product=$products[$index%$products->count()]; $option=$product->options->first();
    $order=App\Models\Order::create(['order_number'=>'PREVIEW-'.($index+1),'customer_id'=>$customer->id,'user_id'=>$owner->id,'status'=>'completed','completed_at'=>sprintf('2026-09-%02d 08:00:00',$day),'pickup_date'=>sprintf('2026-09-%02d',$day),'pickup_time'=>'16:00','fixed_catalog_pricing'=>true]);
    $order->orderDetails()->create(['product_id'=>$product->id,'package_option_id'=>$option->id,'product_name_snapshot'=>$product->product_name,'quantity'=>1,'unit_price'=>$option->price,'layers'=>$option->layers,'included_contents_snapshot'=>$option->included_contents]);
    App\Models\Payment::create(['order_id'=>$order->id,'user_id'=>$owner->id,'amount'=>$option->price,'payment_type'=>'final_payment','payment_method'=>$index%2?'gcash':'cash','payment_date'=>sprintf('2026-09-%02d 07:30:00',$day)]);
}
echo 'Preview ready: workflow-owner@example.test / Preview-only-2026'.PHP_EOL;
