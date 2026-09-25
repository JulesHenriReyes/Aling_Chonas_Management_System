<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PublicOrderingTest extends TestCase
{
    use RefreshDatabase;
    use \Tests\Concerns\CreatesCatalogFixtures;

    private Product $cake;
    private Product $cupcakes;
    private Product $inactiveProduct;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cake = $this->catalogProduct([
            'product_name' => 'Custom Celebration Cake',
            'price' => 1000.00,
            'is_active' => true,
        ]);
        $this->cupcakes = $this->catalogProduct([
            'product_name' => 'Custom Cupcakes',
            'price' => 500.00,
            'is_active' => true,
        ]);
        $this->inactiveProduct = $this->catalogProduct([
            'product_name' => 'Retired Seasonal Cake',
            'price' => 800.00,
            'is_active' => false,
        ]);
    }

    public function test_guest_can_view_active_products_but_not_inactive_products(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Custom Celebration Cake')
            ->assertSee('Custom Cupcakes')
            ->assertDontSee('Retired Seasonal Cake');
    }

    public function test_guest_can_submit_multiple_customized_products_as_a_pending_public_order(): void
    {
        $response = $this->post('/order', $this->validPayload([
            [
                'product_id' => $this->cake->id, 'package_option_id' => $this->cake->options()->first()->id,
                'quantity' => 1,
                'layers' => 2,
                'themes' => 'Floral garden',
                'special_request' => 'Write Happy Birthday Mia',
            ],
            [
                'product_id' => $this->cupcakes->id, 'package_option_id' => $this->cupcakes->options()->first()->id,
                'quantity' => 2,
                'themes' => 'Pastel pink',
                'special_request' => 'Use gold toppers',
            ],
        ]));

        $response->assertRedirect(route('public.order.payment', Order::latest('id')->first()->private_token));

        $order = Order::with(['customer', 'orderDetails.product', 'payments'])->sole();
        $this->assertNull($order->user_id);
        $this->assertSame('pending', $order->status);
        $this->assertCount(2, $order->orderDetails);
        $this->assertCount(0, $order->payments);
        $this->assertSame(2000.00, $order->total_amount);

        $cakeDetail = $order->orderDetails->firstWhere('product_id', $this->cake->id);
        $cupcakeDetail = $order->orderDetails->firstWhere('product_id', $this->cupcakes->id);
        $this->assertSame(2, $cakeDetail->layers);
        $this->assertSame('Floral garden', $cakeDetail->themes);
        $this->assertSame('Write Happy Birthday Mia', $cakeDetail->special_request);
        $this->assertSame(2, $cupcakeDetail->layers);
        $this->assertSame('Pastel pink', $cupcakeDetail->themes);
        $this->assertSame('Use gold toppers', $cupcakeDetail->special_request);

        $this->get(route('public.order.payment', $order->private_token))->assertOk()->assertSee($order->order_number);
    }

    public function test_customer_matching_uses_normalized_phone_and_exact_names(): void
    {
        $customer = Customer::create([
            'first_name' => 'Maria',
            'middle_name' => 'Clara',
            'last_name' => 'Santos',
            'phone_number' => '09171234567',
        ]);

        $payload = $this->validPayload([['product_id' => $this->cake->id, 'package_option_id' => $this->cake->options()->first()->id, 'quantity' => 1]]);
        $payload['phone_number'] = '+63 917-123-4567';
        $this->post('/order', $payload)->assertRedirect(route('public.order.payment', Order::latest('id')->first()->private_token));

        $this->assertDatabaseCount('customers', 1);
        $this->assertSame($customer->id, Order::sole()->customer_id);
    }

    public function test_public_submission_creates_a_new_normalized_customer_when_no_exact_match_exists(): void
    {
        $payload = $this->validPayload([['product_id' => $this->cake->id, 'package_option_id' => $this->cake->options()->first()->id, 'quantity' => 1]]);
        $payload['phone_number'] = '0917-987-6543';
        $payload['middle_name'] = null;

        $this->post('/order', $payload)->assertRedirect(route('public.order.payment', Order::latest('id')->first()->private_token));

        $customer = Customer::sole();
        $this->assertSame('09179876543', $customer->phone_number);
        $this->assertSame('Maria', $customer->first_name);
        $this->assertNull($customer->middle_name);
    }

    public function test_public_image_upload_is_saved_against_its_matching_order_detail(): void
    {
        Storage::fake('public');

        $payload = $this->validPayload([[
            'product_id' => $this->cake->id, 'package_option_id' => $this->cake->options()->first()->id,
            'quantity' => 1,
            'images' => [UploadedFile::fake()->image('floral-reference.jpg')],
        ]]);

        $this->post('/order', $payload)->assertRedirect(route('public.order.payment', Order::latest('id')->first()->private_token));

        $order = Order::with(['orderDetails.images', 'images'])->sole();
        $detail = $order->orderDetails->sole();
        $image = $detail->images->sole();

        $this->assertSame($order->id, $image->order_id);
        $this->assertSame($detail->id, $image->order_detail_id);
        $this->assertNull($image->uploaded_by);
        Storage::disk('public')->assertExists($image->file_path);
    }

    public function test_cross_order_image_association_is_rejected(): void
    {
        $firstCustomer = Customer::create(['first_name' => 'A', 'last_name' => 'Buyer', 'phone_number' => '09170000001']);
        $secondCustomer = Customer::create(['first_name' => 'B', 'last_name' => 'Buyer', 'phone_number' => '09170000002']);
        $service = app(OrderService::class);

        $orderA = $service->createPublicOrder($this->orderData($firstCustomer));
        $orderB = $service->createPublicOrder($this->orderData($secondCustomer));

        $this->expectException(ValidationException::class);
        $service->attachImage($orderA, 'order_images/example.jpg', 'example.jpg', $orderB->orderDetails->sole());
    }

    public function test_old_success_url_redirects_and_guests_cannot_reach_internal_routes(): void
    {
        $this->get('/order/success')->assertRedirect(route('public.order.index'));

        foreach (['/dashboard', '/customers', '/products', '/orders', '/supplies', '/expenses', '/reports', '/pickup-schedule', '/users'] as $path) {
            $this->get($path)->assertRedirect(route('login'));
        }
    }

    public function test_internal_order_lookup_is_not_available_to_a_public_guest(): void
    {
        $customer = Customer::create(['first_name' => 'Maria', 'last_name' => 'Santos', 'phone_number' => '09171234567']);
        $order = app(OrderService::class)->createPublicOrder($this->orderData($customer));

        $this->get('/orders/'.$order->id)->assertRedirect(route('login'));
    }

    public function test_public_order_submissions_are_rate_limited(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.100']);

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post('/order', $this->validPayload([['product_id' => $this->cake->id, 'package_option_id' => $this->cake->options()->first()->id, 'quantity' => 1]]))
                ->assertRedirect(route('public.order.payment', Order::latest('id')->first()->private_token));
        }

        $this->post('/order', $this->validPayload([['product_id' => $this->cake->id, 'package_option_id' => $this->cake->options()->first()->id, 'quantity' => 1]]))
            ->assertStatus(429);
    }

    private function validPayload(array $items): array
    {
        return [
            'first_name' => 'Maria',
            'middle_name' => 'Clara',
            'last_name' => 'Santos',
            'phone_number' => '09171234567',
            'pickup_date' => now()->addDays(3)->toDateString(),
            'pickup_time' => '14:00',
            'items' => $items,
            'expected_total' => collect($items)->sum(fn ($item) => Product::find($item['product_id'])->options()->first()->price * $item['quantity']),
        ];
    }

    private function orderData(Customer $customer): array
    {
        return [
            'customer_id' => $customer->id,
            'pickup_date' => now()->addDays(3)->toDateString(),
            'pickup_time' => '14:00',
            'items' => [['product_id' => $this->cake->id, 'package_option_id' => $this->cake->options()->first()->id, 'quantity' => 1]],
        ];
    }
}
