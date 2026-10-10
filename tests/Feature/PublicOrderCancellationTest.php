<?php

namespace Tests\Feature;

use App\Models\{Customer, Order, Payment, PaymentProof, User};
use App\Services\{FinancialReportService, OrderService, ReportPeriod};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicOrderCancellationTest extends TestCase
{
    use RefreshDatabase;
    use \Tests\Concerns\CreatesCatalogFixtures;

    private function order(): Order
    {
        $customer = Customer::create(['first_name' => 'Maria', 'last_name' => 'Santos', 'phone_number' => '09171234567']);
        $product = $this->catalogProduct(['product_name' => 'Celebration cake', 'price' => 1000, 'is_active' => true]);

        return app(OrderService::class)->createPublicOrder([
            'customer_id' => $customer->id,
            'pickup_date' => now()->addDays(3)->toDateString(), 'pickup_time' => '14:00',
            'items' => [['product_id' => $product->id, 'package_option_id' => $product->options()->first()->id, 'quantity' => 1]],
        ]);
    }

    private function payment(Order $order, float $amount, string $type = 'down_payment'): Payment
    {
        $owner = User::factory()->create(['role' => 'owner']);

        if ($amount === 500.0 && $type === 'down_payment') {
            $this->confirmPaymentFixture($order);
            $proof = PaymentProof::create(['order_id' => $order->id, 'file_path' => 'private/receipt.jpg',
                'reference_number' => 'VERIFIED-'.$order->id]);

            return app(OrderService::class)->recordDownPayment($order, $amount, 'gcash', $proof->reference_number, $owner, null, $proof);
        }

        return $order->payments()->create(['user_id' => $owner->id, 'amount' => $amount,
            'payment_type' => $type, 'payment_method' => 'cash', 'payment_date' => now()]);
    }

    private function cancel(Order $order, array $extra = [])
    {
        return $this->post(route('public.order.cancel', $order->private_token), ['confirm_cancellation' => '1'] + $extra);
    }

    public function test_unpaid_order_cancels_immediately_and_replays_preserve_timestamp(): void
    {
        $order = $this->order();
        $this->get(route('public.order.payment', $order->private_token))->assertSee('Cancel order');
        $this->cancel($order)->assertRedirect(route('public.order.payment', $order->private_token))->assertSessionHasNoErrors();
        $order->refresh();
        $this->assertSame('cancelled', $order->status);
        $this->assertSame('customer', $order->cancellation_kind);
        $timestamp = $order->cancelled_at->toISOString();
        $this->travel(1)->hours();
        $this->cancel($order)->assertSessionHasNoErrors();
        $this->assertSame($timestamp, $order->fresh()->cancelled_at->toISOString());
        $this->assertDatabaseCount('payments', 0);
        $this->assertFalse(\Illuminate\Support\Facades\Schema::hasTable('refunds'));
        $this->get(route('public.order.payment', $order->private_token))->assertDontSee('<details data-order-cancellation', false);
    }

    public function test_verified_deposit_is_retained_in_the_original_ledger_and_reports_once(): void
    {
        $order = $this->order();
        $payment = $this->payment($order, 500);
        $order->update(['status' => 'preparing', 'review_status' => 'approved']);
        $original = $payment->fresh()->getAttributes();
        $this->get(route('public.order.payment', $order->private_token))->assertSee('It will not be refunded.');
        $this->cancel($order)->assertSessionHasNoErrors();
        $this->cancel($order)->assertSessionHasNoErrors();
        $this->assertSame($original, $payment->fresh()->getAttributes());
        $this->assertDatabaseCount('payments', 1);
        $this->assertFalse(\Illuminate\Support\Facades\Schema::hasTable('refunds'));
        $reports = app(FinancialReportService::class);
        $period = ReportPeriod::dates(now()->toDateString(), now()->toDateString());
        $this->assertEquals(500.0, $reports->events($period, 'gross_collections')->sum('amount'));
        $this->assertEquals(500.0, $reports->events($period, 'cancellation_income')->sum('amount'));
        $this->assertEquals(0.0, $reports->events($period, 'sales')->sum('amount'));
    }

    public function test_reported_transfer_cannot_be_bypassed_by_a_customer(): void
    {
        foreach (['awaiting_verification', 'rejected'] as $status) {
            $order = $this->order();
            PaymentProof::create(['order_id' => $order->id, 'file_path' => 'private/receipt.jpg',
                'reference_number' => 'TRANSFER-'.$order->id, 'status' => $status]);
            $this->get(route('public.order.payment', $order->private_token))->assertDontSee('<details data-order-cancellation', false);
            $this->cancel($order, ['no_funds_checked' => '1'])->assertSessionHasErrors('cancellation');
            $this->assertSame('pending', $order->fresh()->status);
        }
    }

    public function test_additional_unreviewed_receipt_blocks_even_a_verified_deposit(): void
    {
        $order = $this->order();
        $this->payment($order, 500);
        PaymentProof::create(['order_id' => $order->id, 'file_path' => 'private/receipt.jpg', 'reference_number' => 'EXTRA']);
        $this->cancel($order)->assertSessionHasErrors('cancellation');
        $this->assertNotSame('cancelled', $order->fresh()->status);
    }

    public function test_fully_paid_irregular_and_completed_orders_are_protected(): void
    {
        foreach ([1000, 100, 700] as $amount) {
            $order = $this->order();
            $this->payment($order, $amount);
            $this->cancel($order)->assertSessionHasErrors('cancellation');
            $this->assertSame('pending', $order->fresh()->status);
        }
        $order = $this->order();
        $order->update(['status' => 'completed', 'completed_at' => now()]);
        $this->cancel($order)->assertSessionHasErrors('cancellation');
        $this->assertSame('completed', $order->fresh()->status);
    }

    public function test_confirmation_is_required_and_state_is_rechecked_at_submission(): void
    {
        $order = $this->order();
        $url = route('public.order.cancel', $order->private_token);
        $this->post($url)->assertSessionHasErrors('confirm_cancellation');
        $this->assertSame('pending', $order->fresh()->status);
        $this->get(route('public.order.payment', $order->private_token))->assertSee('Cancel order');
        $this->payment($order, 1000);
        $this->cancel($order)->assertSessionHasErrors('cancellation');
        $this->assertSame('pending', $order->fresh()->status);
    }

    public function test_invalid_tokens_and_staff_orders_cannot_use_public_cancellation(): void
    {
        $order = $this->order();
        $this->post(route('public.order.cancel', str_repeat('a', 64)), ['confirm_cancellation' => '1'])->assertNotFound();
        $order->update(['user_id' => User::factory()->create(['role' => 'owner'])->id]);
        $this->cancel($order)->assertNotFound();
        $this->assertSame('pending', $order->fresh()->status);
    }
}
