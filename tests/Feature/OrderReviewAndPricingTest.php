<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class OrderReviewAndPricingTest extends TestCase
{
    use RefreshDatabase;
    use \Tests\Concerns\CreatesCatalogFixtures;

    private User $owner;
    private Customer $customer;
    private Product $cake;
    private Product $cupcakes;
    private OrderService $orderService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::create([
            'first_name' => 'Chona',
            'last_name' => 'Hinay',
            'email' => 'owner@test.com',
            'password' => 'password123',
            'role' => 'owner',
        ]);
        $this->customer = Customer::create([
            'first_name' => 'Maria',
            'last_name' => 'Santos',
            'phone_number' => '09171234567',
        ]);
        $this->cake = $this->catalogProduct(['product_name' => 'Celebration Cake', 'price' => 1000.00, 'is_active' => true]);
        $this->cupcakes = $this->catalogProduct(['product_name' => 'Cupcakes', 'price' => 500.00, 'is_active' => true]);
        $this->orderService = app(OrderService::class);
    }

    public function test_public_and_staff_creation_paths_preserve_the_correct_creator_intent(): void
    {
        $publicOrder = $this->orderService->createPublicOrder($this->orderData());
        $staffOrder = $this->orderService->createInternalOrder($this->orderData(), $this->owner);

        $this->assertNull($publicOrder->user_id);
        $this->assertSame($this->owner->id, $staffOrder->user_id);

        $this->expectException(ValidationException::class);
        $this->orderService->createInternalOrder($this->orderData(), null);
    }

    public function test_pending_public_prices_are_fixed_immediately_and_cannot_be_manually_adjusted(): void
    {
        $order = $this->orderService->createPublicOrder($this->orderData());
        $detail = $order->orderDetails->firstWhere('product_id', $this->cake->id);
        $this->assertSame(1000.00, (float) $detail->unit_price);
        $this->assertSame(1500.00, $order->total_amount);
        $this->assertSame(750.00, $order->required_down_payment);
        $this->expectException(ValidationException::class);
        $this->orderService->updateOrderDetailPrice($order, $detail, 1200.00, $this->owner);
    }

    public function test_confirmed_order_retains_option_price_after_catalog_price_changes(): void
    {
        $order = $this->orderService->createInternalOrder($this->orderData(), $this->owner);
        $detail = $order->orderDetails->firstWhere('product_id', $this->cake->id);
        $this->orderService->recordDownPayment($order, 750.00, 'cash', null, $this->owner);
        $this->cake->options()->first()->update(['price' => 2000.00]);
        $detail->refresh();
        $order->refresh();
        $this->assertSame(1000.00, (float) $detail->unit_price);
        $this->assertSame(1500.00, $order->total_amount);
        $this->expectException(ValidationException::class);
        $this->orderService->updateOrderDetailPrice($order, $detail, 1200.00, $this->owner);
    }

    public function test_invalid_status_transitions_and_terminal_state_changes_are_rejected(): void
    {
        $order = $this->orderService->createInternalOrder($this->orderData(), $this->owner);

        try {
            $this->orderService->updateStatus($order, 'preparing', $this->owner);
            $this->fail('Pending orders must not skip confirmation.');
        } catch (ValidationException) {
            $this->assertSame('pending', $order->fresh()->status);
        }

        $this->orderService->recordDownPayment($order, 750.00, 'cash', null, $this->owner);

        try {
            $this->orderService->updateStatus($order, 'ready_for_pickup', $this->owner);
            $this->fail('Confirmed orders must not skip preparation.');
        } catch (ValidationException) {
            $this->assertSame('confirmed', $order->fresh()->status);
        }

        $this->orderService->updateStatus($order, 'preparing', $this->owner);
        $this->orderService->recordFinalPayment($order, 750.00, 'cash', null, $this->owner);

        try {
            $this->orderService->updateStatus($order, 'completed', $this->owner);
            $this->fail('Preparing orders must not skip ready for pickup.');
        } catch (ValidationException) {
            $this->assertSame('preparing', $order->fresh()->status);
        }

        $this->orderService->updateStatus($order, 'ready_for_pickup', $this->owner);
        $this->orderService->updateStatus($order, 'completed', $this->owner);

        $this->expectException(ValidationException::class);
        $this->orderService->updateStatus($order, 'preparing', $this->owner);
    }

    public function test_cancelled_orders_are_terminal(): void
    {
        $order = $this->orderService->createInternalOrder($this->orderData(), $this->owner);
        $this->orderService->cancelOrder($order, $this->owner);

        $this->expectException(ValidationException::class);
        $this->orderService->updateStatus($order, 'confirmed', $this->owner);
    }

    private function orderData(): array
    {
        return [
            'customer_id' => $this->customer->id,
            'pickup_date' => now()->addDays(3)->toDateString(),
            'pickup_time' => '14:00',
            'items' => [
                ['product_id' => $this->cake->id, 'package_option_id' => $this->cake->options()->first()->id, 'quantity' => 1],
                ['product_id' => $this->cupcakes->id, 'package_option_id' => $this->cupcakes->options()->first()->id, 'quantity' => 1],
            ],
        ];
    }
}
