<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use App\Services\OrderService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class OrderAndPaymentBusinessRulesTest extends TestCase
{
    use RefreshDatabase;
    use \Tests\Concerns\CreatesCatalogFixtures;

    protected User $owner;
    protected User $assistant;
    protected Customer $customer;
    protected Product $cake;
    protected Product $cupcakes;
    protected OrderService $orderService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->orderService = new OrderService();

        $this->owner = User::create([
            'first_name' => 'Chona',
            'last_name' => 'Hinay',
            'email' => 'owner@test.com',
            'password' => 'password123',
            'role' => 'owner',
        ]);

        $this->assistant = User::create([
            'first_name' => 'Blyte',
            'last_name' => 'Hinay',
            'email' => 'assistant@test.com',
            'password' => 'password123',
            'role' => 'assistant',
        ]);

        $this->customer = Customer::create([
            'first_name' => 'Maria',
            'last_name' => 'Santos',
            'phone_number' => '09171234567',
        ]);

        $this->cake = $this->catalogProduct([
            'product_name' => 'Custom Birthday Cake',
            'price' => 1000.00,
            'is_active' => true,
        ]);

        $this->cupcakes = $this->catalogProduct([
            'product_name' => 'Box of 12 Cupcakes',
            'price' => 500.00,
            'is_active' => true,
        ]);
    }

    protected function createSampleOrder(): Order
    {
        return $this->orderService->createInternalOrder([
            'customer_id' => $this->customer->id,
            'pickup_date' => now()->addDays(3)->toDateString(),
            'pickup_time' => '14:00',
            'items' => [
                ['product_id' => $this->cake->id, 'package_option_id' => $this->cake->options()->first()->id, 'quantity' => 1, 'layers' => 2, 'themes' => 'Floral'],
                ['product_id' => $this->cupcakes->id, 'package_option_id' => $this->cupcakes->options()->first()->id, 'quantity' => 1, 'themes' => 'Pastel'],
            ],
        ], $this->owner); // Total: 1000 + 500 = 1500.00
    }

    public function test_exact_deposit_is_accepted_after_staff_confirmation(): void
    {
        $order = $this->createSampleOrder();
        $this->assertEquals(1500.00, $order->total_amount);
        $this->assertEquals(750.00, $order->required_down_payment);
        $this->assertEquals('pending', $order->status);

        $this->confirmPaymentFixture($order);
        $payment = $this->orderService->recordDownPayment(
            $order,
            750.00,
            'cash',
            null,
            $this->owner
        );

        $order->refresh();
        $this->assertEquals(750.00, $payment->amount);
        $this->assertEquals('down_payment', $payment->payment_type);
        $this->assertEquals('confirmed', $order->status);
        $this->assertEquals('partially_paid', $order->payment_status);
        $this->assertEquals(750.00, $order->remaining_balance);
    }

    public function test_incorrect_down_payment_rejected(): void
    {
        $order = $this->createSampleOrder();

        $this->expectException(ValidationException::class);
        $this->confirmPaymentFixture($order);
        $this->orderService->recordDownPayment(
            $order,
            500.00, // Expected 750.00
            'cash',
            null,
            $this->owner
        );
    }

    public function test_duplicate_down_payment_rejected(): void
    {
        $order = $this->createSampleOrder();

        $this->confirmPaymentFixture($order);
        $this->orderService->recordDownPayment(
            $order,
            750.00,
            'cash',
            null,
            $this->owner
        );

        $this->expectException(ValidationException::class);
        $this->confirmPaymentFixture($order);
        $this->orderService->recordDownPayment(
            $order,
            750.00,
            'cash',
            null,
            $this->owner
        );
    }

    public function test_generic_status_update_cannot_bypass_staff_review(): void
    {
        $order = $this->createSampleOrder();
        $this->assertEquals('pending', $order->status);

        $this->expectException(ValidationException::class);
        // A generic status update cannot replace the explicit feasibility review.
        $this->orderService->updateStatus($order, 'confirmed', $this->assistant);
    }

    public function test_final_payment_accepted_when_exact_balance_is_paid(): void
    {
        $order = $this->createSampleOrder();

        // 1. Pay 50% deposit
        $this->confirmPaymentFixture($order);
        $this->orderService->recordDownPayment($order, 750.00, 'cash', null, $this->owner);
        $order->refresh();

        // 2. Collect the balance after readiness at actual pickup
        $this->orderService->updateStatus($order, 'preparing', $this->assistant);
        $this->orderService->updateStatus($order, 'ready_for_pickup', $this->assistant);
        $payment = $this->orderService->recordFinalPayment(
            $order,
            750.00,
            'gcash',
            'GCASH-REF-999',
            $this->owner,
            null,
            true
        );

        $order->refresh();
        $this->assertEquals('final_payment', $payment->payment_type);
        $this->assertEquals(1500.00, $order->amount_paid);
        $this->assertEquals(0.00, $order->remaining_balance);
        $this->assertEquals('fully_paid', $order->payment_status);
    }

    public function test_payment_exceeding_balance_rejected(): void
    {
        $order = $this->createSampleOrder();
        $this->confirmPaymentFixture($order);
        $this->orderService->recordDownPayment($order, 750.00, 'cash', null, $this->owner);

        $this->orderService->updateStatus($order, 'preparing', $this->assistant);
        $this->orderService->updateStatus($order, 'ready_for_pickup', $this->assistant);

        $this->expectException(ValidationException::class);
        // Remaining balance is 750.00, paying 800.00 should fail at collection
        $this->orderService->recordFinalPayment(
            $order,
            800.00,
            'cash',
            null,
            $this->owner,
            null,
            true
        );
    }

    public function test_cancelled_order_preserves_payment_and_rejects_further_payment(): void
    {
        $order = $this->createSampleOrder();
        $this->confirmPaymentFixture($order);
        $this->orderService->recordDownPayment($order, 750.00, 'gcash', 'GCASH-12345', $this->owner);

        // Owner records customer cancellation
        $this->orderService->cancelOrder($order, $this->owner);
        $order->refresh();

        $this->assertEquals('cancelled', $order->status);
        $this->assertNotNull($order->cancelled_at);
        $this->assertCount(1, $order->payments);
        $this->assertEquals(750.00, $order->amount_paid); // Retained non-refundable down payment

        // Attempting another payment must be rejected
        $this->expectException(ValidationException::class);
        $this->orderService->recordFinalPayment($order, 750.00, 'cash', null, $this->owner);
    }

    public function test_completed_at_is_recorded_and_not_overwritten(): void
    {
        $order = $this->createSampleOrder();
        $this->confirmPaymentFixture($order);
        $this->orderService->recordDownPayment($order, 750.00, 'cash', null, $this->owner);
        $this->orderService->updateStatus($order, 'preparing', $this->assistant);
        $this->orderService->updateStatus($order, 'ready_for_pickup', $this->assistant);

        $completedTime = Carbon::now()->subHour();
        Carbon::setTestNow($completedTime);

        $this->orderService->recordFinalPayment($order, 750.00, 'cash', null, $this->owner, null, true);
        $order->refresh();

        $this->assertEquals('completed', $order->status);
        $this->assertNotNull($order->completed_at);
        $originalCompletedAt = $order->completed_at->toDateTimeString();

        // Later update
        Carbon::setTestNow(now()->addDay());
        $order->notes_text = 'Customer requested special box tag';
        $order->save();
        $order->refresh();

        $this->assertEquals($originalCompletedAt, $order->completed_at->toDateTimeString());
    }

    public function test_cancelled_at_is_recorded_and_not_overwritten(): void
    {
        $order = $this->createSampleOrder();

        $cancelTime = Carbon::now()->subHours(2);
        Carbon::setTestNow($cancelTime);

        $this->orderService->cancelOrder($order, $this->owner);
        $order->refresh();

        $this->assertEquals('cancelled', $order->status);
        $this->assertNotNull($order->cancelled_at);
        $originalCancelledAt = $order->cancelled_at->toDateTimeString();

        // Later update
        Carbon::setTestNow(now()->addDays(2));
        $order->notes_text = 'Customer called to explain cancellation';
        $order->save();
        $order->refresh();

        $this->assertEquals($originalCancelledAt, $order->cancelled_at->toDateTimeString());
    }
}
