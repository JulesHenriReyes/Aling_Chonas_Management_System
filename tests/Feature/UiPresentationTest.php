<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Product;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class UiPresentationTest extends TestCase
{
    use RefreshDatabase;
    use \Tests\Concerns\CreatesCatalogFixtures;

    private function snapshot(string $name, TestResponse $response): void
    {
        $response->assertOk();
        // Optional local visual review output; all fixtures use the test database.
        if ($directory = getenv('BAKERY_UI_SNAPSHOT_DIR')) {
            if (! is_dir($directory)) {
                mkdir($directory, 0777, true);
            }
            $html = str_replace('http://localhost', 'http://127.0.0.1:8000', $response->getContent());
            file_put_contents($directory.'/'.$name.'.html', $html);
        }
    }

    public function test_order_pages_preserve_actions_and_price_locking_in_every_lifecycle_state(): void
    {
        $owner = User::create(['first_name' => 'Review', 'last_name' => 'Owner', 'email' => 'review@example.test', 'password' => 'password123', 'role' => 'owner', 'is_active' => true]);
        $customer = Customer::create(['first_name' => 'Maria Alexandra', 'last_name' => 'De la Cruz Santiago', 'phone_number' => '09171234567']);
        $product = $this->catalogProduct(['product_name' => 'Custom celebration cake with buttercream flowers and a very long design description', 'price' => 123456.78, 'is_active' => true]);
        $service = app(OrderService::class);
        $data = ['customer_id' => $customer->id, 'pickup_date' => now()->addDays(3)->toDateString(), 'pickup_time' => '14:00', 'notes_text' => 'Keep the cake level. Customer will call before pickup.', 'items' => [['product_id' => $product->id, 'package_option_id' => $product->options()->first()->id, 'quantity' => 1, 'layers' => 3, 'themes' => 'Pastel blue and gold floral celebration', 'special_request' => 'Happy birthday, Maria! Please keep the inscription and every design detail visible.']]];
        $order = $service->createInternalOrder($data, $owner);
        $this->actingAs($owner);
        $pending = $this->get('/orders/'.$order->id);
        $pending->assertDontSee('name="unit_price"', false)->assertSee('value="down_payment"', false)->assertSee('Record deposit & confirm', false);
        $this->snapshot('order-pending', $pending);
        $service->recordDownPayment($order, $order->required_down_payment, 'gcash', 'TEST-REF-123', $owner);
        $confirmed = $this->get('/orders/'.$order->id);
        $confirmed->assertDontSee('name="unit_price"', false)->assertSee('value="preparing"', false)->assertSee('value="final_payment"', false);
        $this->snapshot('order-confirmed', $confirmed);
        $service->updateStatus($order, 'preparing', $owner);
        $preparing = $this->get('/orders/'.$order->id);
        $preparing->assertSee('value="ready_for_pickup"', false);
        $this->snapshot('order-preparing', $preparing);
        $service->updateStatus($order, 'ready_for_pickup', $owner);
        $ready = $this->get('/orders/'.$order->id);
        $ready->assertDontSee('value="completed"', false)->assertSee('Payment required:');
        $this->snapshot('order-ready-unpaid', $ready);
        $service->recordFinalPayment($order, $order->remaining_balance, 'cash', null, $owner);
        $paid = $this->get('/orders/'.$order->id);
        $paid->assertSee('value="completed"', false)->assertDontSee('value="final_payment"', false);
        $this->snapshot('order-ready-paid', $paid);
        $service->updateStatus($order, 'completed', $owner);
        $completed = $this->get('/orders/'.$order->id);
        $completed->assertDontSee('Cancel Order')->assertDontSee('name="unit_price"', false)->assertSee('TEST-REF-123');
        $this->snapshot('order-completed', $completed);
        $cancelledOrder = $service->createInternalOrder($data, $owner);
        $service->recordDownPayment($cancelledOrder, $cancelledOrder->required_down_payment, 'cash', null, $owner);
        $service->cancelOrder($cancelledOrder, $owner);
        $cancelled = $this->get('/orders/'.$cancelledOrder->id);
        $cancelled->assertSee('Order Cancelled')->assertDontSee('value="final_payment"', false)->assertDontSee('name="unit_price"', false);
        $this->snapshot('order-cancelled', $cancelled);
        foreach (['orders', 'dashboard', 'pickup-schedule', 'reports', 'expenses', 'customers', 'products', 'supplies', 'users'] as $page) {
            $this->snapshot($page, $this->get('/'.$page));
        }
        foreach (['customers/'.$customer->id, 'customers/'.$customer->id.'/edit', 'customers/create', 'orders/create', 'users/create', 'users/'.$owner->id.'/edit'] as $page) {
            $this->snapshot(str_replace('/', '-', $page), $this->get('/'.$page));
        }
    }

    public function test_public_receipt_remains_unpaid_and_validation_preserves_selected_items(): void
    {
        $product = $this->catalogProduct(['product_name' => 'Birthday cake', 'price' => 1000, 'is_active' => true]);
        $this->snapshot('login', $this->get('/login'));
        $this->snapshot('public-empty', $this->get('/'));
        $data = ['first_name' => 'Maria', 'last_name' => 'Santos', 'expected_total' => 2000, 'phone_number' => '09171234567', 'pickup_date' => now()->addDays(3)->toDateString(), 'pickup_time' => '14:00', 'items' => [['product_id' => $product->id, 'package_option_id' => $product->options()->first()->id, 'quantity' => 2, 'layers' => 2, 'themes' => 'Blue and gold', 'special_request' => 'Happy birthday!']]];
        $this->post('/order', $data)->assertRedirect(route('public.order.payment', \App\Models\Order::sole()->private_token));
        $success = $this->get(route('public.order.payment', \App\Models\Order::sole()->private_token));
        $success->assertSee('Pending deposit verification')->assertSee('Awaiting receipt')->assertSee('2,000.00')->assertSee('Blue and gold');
        $this->snapshot('public-success', $success);
        $this->post(route('public.order.continue'), ['items' => $data['items']])->assertRedirect(route('public.order.details'));
        $this->from(route('public.order.details'))->post('/order', array_merge($data, ['phone_number' => '123']))->assertSessionHasErrors('phone_number');
        $invalid = $this->get(route('public.order.details'));
        $invalid->assertSee('value="123"', false)->assertSee('validation-errors')->assertSee('Blue and gold', false);
        $this->snapshot('public-validation', $invalid);
    }
}

