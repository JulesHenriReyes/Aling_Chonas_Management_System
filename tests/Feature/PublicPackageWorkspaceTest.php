<?php

namespace Tests\Feature;

use App\Models\{AddOn, Order, Product};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class PublicPackageWorkspaceTest extends TestCase
{
    use RefreshDatabase;
    use \Tests\Concerns\CreatesCatalogFixtures;

    private function line(Product $product, int $quantity = 1): array
    {
        return ['product_id' => $product->id, 'package_option_id' => $product->options()->first()->id, 'quantity' => $quantity, 'themes' => 'Blue flowers'];
    }

    public function test_catalog_navigates_to_dedicated_customization_and_multiline_edit_is_idempotent(): void
    {
        $cake = $this->catalogProduct(['product_name' => 'Floral cake', 'price' => 1000, 'is_active' => true]);
        $key = (string) Str::uuid(); $url = route('public.package.customize', [$cake, $key]);
        $this->get('/')->assertOk()->assertSee('Select package')->assertDontSee('name="items[', false);
        $this->get($url)->assertOk()->assertSee('Save package to order')->assertSee('Layer option', false);
        $data = ['items' => [$this->line($cake)]];
        $this->post($url, $data)->assertRedirect(); $this->post($url, $data)->assertRedirect();
        $this->assertCount(1, session('public_order_draft.items'));
        $second = (string) Str::uuid(); $secondUrl = route('public.package.customize', [$cake, $second]);
        $this->get($secondUrl)->assertOk(); $this->post($secondUrl, ['items' => [$this->line($cake, 2)]])->assertRedirect();
        $this->assertCount(2, session('public_order_draft.items'));
        $data['items'][0]['themes'] = 'Pink'; $this->post($url, $data)->assertRedirect();
        $this->assertSame('Pink', session('public_order_draft.items.0.themes'));
        $this->assertEquals(2, session('public_order_draft.items.1.quantity'));
        $this->get('/order/details')->assertOk()->assertSee('3,000.00');
        $this->post(route('public.package.remove',$key))->assertRedirect();
        $this->post(route('public.package.remove',$key))->assertRedirect();
        $this->assertCount(1, session('public_order_draft.items'));
        $this->assertSame($second, session('public_order_draft.items.0.draft_key'));
    }

    public function test_validation_keeps_uploads_and_submitted_order_associates_images_and_extras_with_each_line(): void
    {
        Storage::fake('local'); Storage::fake('public');
        $cake = $this->catalogProduct(['product_name' => 'Cake', 'price' => 1000, 'is_active' => true]);
        $extra = AddOn::create(['name' => 'Cupcakes', 'description' => 'Extra cupcakes', 'price' => 100, 'is_active' => true]); $cake->addOns()->attach($extra);
        $key = (string) Str::uuid(); $url = route('public.package.customize', [$cake, $key]);
        $this->get($url)->assertOk();
        $data = ['items' => [array_merge($this->line($cake, 0), ['images' => [UploadedFile::fake()->image('blue.png')], 'add_ons' => [['add_on_id' => $extra->id, 'quantity' => 2]]])]];
        $this->from($url)->post($url, $data)->assertSessionHasErrors('items.0.quantity');
        $this->get($url)->assertSee('blue.png');
        $editor = session('public_package_editors.'.$key); $this->assertCount(1,$editor['staged_images']);
        Storage::disk('local')->assertExists($editor['staged_images'][0]['staged_path']);
        unset($data['items'][0]['images']); $data['items'][0]['quantity'] = 1;
        $this->post($url,$data)->assertRedirect();
        $second = (string) Str::uuid(); $secondUrl=route('public.package.customize',[$cake,$second]); $this->get($secondUrl);
        $this->post($secondUrl,['items'=>[array_merge($this->line($cake,2),['themes'=>'Green','images'=>[UploadedFile::fake()->image('green.png')]])]])->assertRedirect();
        $checkout=['submission_key'=>session('public_order_draft.submission_key'),'first_name'=>'Preview','last_name'=>'Buyer','phone_number'=>'09171234567','pickup_date'=>now()->addDays(2)->toDateString(),'pickup_time'=>'14:00','expected_total'=>3200];
        $response=$this->post('/order',$checkout); $response->assertRedirect();
        $this->post('/order',$checkout)->assertRedirect($response->headers->get('Location'));
        $this->assertDatabaseCount('orders',1);
        $order=Order::sole(); $this->assertSame(3200.0,$order->total_amount);
        $lines=$order->orderDetails()->orderBy('id')->get();
        $this->assertCount(1,$lines[0]->addOns); $this->assertCount(0,$lines[1]->addOns);
        $this->assertSame('blue.png',$lines[0]->images->sole()->original_filename);
        $this->assertSame('green.png',$lines[1]->images->sole()->original_filename);
        $this->assertDatabaseCount('payments',0);
    }

    public function test_autosaved_upload_is_deduplicated_and_catalog_availability_revalidated(): void
    {
        Storage::fake('local');
        $cake=$this->catalogProduct(['product_name'=>'Cake','price'=>1000,'is_active'=>true]); $key=(string)Str::uuid();
        $url=route('public.package.customize',[$cake,$key]); $this->get($url);
        $file=UploadedFile::fake()->image('reference.png'); $data=['items'=>[$this->line($cake)+['images'=>[$file]]]];
        $this->postJson(route('public.package.draft',[$cake,$key]),$data)->assertOk();
        $this->postJson(route('public.package.draft',[$cake,$key]),$data)->assertOk();
        $this->assertCount(1,session('public_package_editors.'.$key.'.staged_images'));
        unset($data['items'][0]['images']); $this->post($url,$data)->assertRedirect();
        $cake->options()->update(['is_active'=>false]);
        $this->get('/order/details')->assertRedirect(route('public.order.index'));
        $this->get('/')->assertOk()->assertSee('Remove');
        $this->assertDatabaseCount('orders',0);
    }
}
