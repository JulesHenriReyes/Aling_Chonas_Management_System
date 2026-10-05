<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use App\Services\OrderService;
use App\Services\PaymentReviewService;
use App\Services\RefundService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ImplementationPolicyTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $assistant;

    private Customer $customer;

    private Product $product;

    private OrderService $orders;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->create(['role' => 'owner', 'is_active' => true]);
        $this->assistant = User::factory()->create(['role' => 'assistant', 'is_active' => true]);
        $this->customer = Customer::create(['first_name' => 'Policy', 'last_name' => 'Buyer', 'phone_number' => '09171234567']);
        $this->product = Product::create(['product_name' => 'Policy cake', 'price' => 2000, 'is_active' => true]);
        $this->product->options()->create(['layers' => 1, 'price' => 2000, 'included_contents' => 'Cake', 'is_active' => true]);
        $this->orders = app(OrderService::class);
        Storage::fake('public');
        Storage::fake('local');
        Storage::fake('receipts');
    }

    private function payload(): array
    {
        return ['customer_id' => $this->customer->id, 'pickup_date' => now()->addDays(2)->toDateString(), 'pickup_time' => '15:00',
            'expected_total' => 2000, 'items' => [['product_id' => $this->product->id,
                'package_option_id' => $this->product->options()->first()->id, 'quantity' => 1]]];
    }

    private function order(bool $public = false): Order
    {
        return $public ? $this->orders->createPublicOrder($this->payload()) : $this->orders->createInternalOrder($this->payload(), $this->owner);
    }

    private function snapshot(): array
    {
        $snapshot = [];
        foreach (['customers', 'orders', 'order_details', 'order_images', 'payments', 'payment_proofs', 'refunds', 'gcash_references'] as $table) {
            $snapshot[$table] = DB::table($table)->get()->map(fn ($row) => (array) $row)->all();
        }
        foreach (['public', 'local', 'receipts'] as $disk) {
            $snapshot['files-'.$disk] = Storage::disk($disk)->allFiles();
        }

        return $snapshot;
    }

    private function denied(callable $action, string $exception = ValidationException::class): void
    {
        $before = $this->snapshot();
        try {
            $action();
            $this->fail('Expected rejection before mutation.');
        } catch (\Throwable $error) {
            $this->assertInstanceOf($exception, $error);
        }
        $this->assertSame($before, $this->snapshot());
    }

    private function ready(Order $order): void
    {
        $this->confirmPaymentFixture($order);
        $this->orders->recordDownPayment($order, 1000, 'cash', null, $this->owner);
        $this->orders->updateStatus($order, 'preparing', $this->assistant);
        $this->orders->updateStatus($order, 'ready_for_pickup', $this->assistant);
    }

    public function test_assistant_valid_direct_requests_and_bookmarks_are_denied_without_record_file_or_draft_writes(): void
    {
        $order = $this->order();
        $public = $this->order(true);
        $this->confirmPaymentFixture($public);
        $proof = app(PaymentReviewService::class)->submit($public, UploadedFile::fake()->image('receipt.png'), 'PROOF-1');
        $failed = $this->order();
        $this->confirmPaymentFixture($failed);
        $this->orders->recordDownPayment($failed, 1000, 'cash', null, $this->owner);
        $refund = app(RefundService::class)->markBakeryFailure($failed, 'Oven failure', $this->owner, true);
        $customer = ['first_name' => 'New', 'last_name' => 'Buyer', 'phone_number' => '09181234567'];
        $requests = [
            ['GET', '/customers/create', []], ['GET', '/customers/'.$this->customer->id.'/edit', []],
            ['POST', '/customers', $customer], ['PATCH', '/customers/'.$this->customer->id, $customer],
            ['GET', '/orders/create', []], ['GET', '/orders/create/details', []],
            ['POST', '/orders/create/details', $this->payload()], ['POST', '/orders/create/details/back', ['pickup_time' => '15:00']],
            ['POST', '/orders/create/customer', $customer], ['POST', '/orders', $this->payload()],
            ['POST', '/orders/'.$order->id.'/images', ['image' => UploadedFile::fake()->image('cake.png')]],
            ['POST', '/orders/'.$order->id.'/cancel', []],
            ['POST', '/orders/'.$order->id.'/payments', ['payment_type' => 'down_payment', 'amount' => 1000, 'payment_method' => 'cash']],
            ['POST', '/orders/'.$order->id.'/complete-pickup', ['payment_method' => 'cash', 'pickup_confirmed' => '1']],
            ['POST', '/payment-proofs/'.$proof->id.'/accept', ['amount' => 1000, 'reference_number' => 'PROOF-1', 'account_checked' => '1']],
            ['POST', '/payment-proofs/'.$proof->id.'/reject', ['reason' => 'Not received']],
            ['POST', '/orders/'.$order->id.'/bakery-failure', ['reason' => 'Oven failure', 'bakery_failure_confirmed' => '1']],
            ['POST', '/refunds/'.$refund->id.'/complete', ['method' => 'cash', 'reference_number' => 'RETURN-1', 'transfer_confirmed' => '1']],
            ['GET', '/reports', []], ['GET', '/reports/records', ['kind' => 'sales']], ['GET', '/reports/export', []],
        ];
        $before = $this->snapshot();
        $this->actingAs($this->assistant)->withSession(['staff_order_draft' => ['items' => $this->payload()['items']]]);
        foreach ($requests as [$method, $url, $data]) {
            $this->call($method, $url, $data)->assertForbidden()->assertSessionHas('staff_order_draft');
            $this->assertSame($before, $this->snapshot(), $method.' '.$url);
        }
        foreach (['/dashboard', '/customers', '/customers/'.$this->customer->id, '/products', '/orders', '/orders/'.$public->id,
            '/payment-proofs/'.$proof->id.'/receipt', '/supplies', '/expenses', '/pickup-schedule'] as $url) {
            $this->get($url)->assertOk();
        }
        $this->get('/orders/'.$public->id)->assertDontSee(route('proofs.accept', $proof), false)->assertDontSee(route('reports.index'), false);
        $this->get('/orders')->assertDontSee(route('orders.create'), false);
        $this->get('/customers')->assertDontSee(route('customers.create'), false)->assertDontSee(route('customers.edit', $this->customer), false);
    }

    public function test_assistant_service_bypasses_include_indirect_cancellation_and_financial_operations(): void
    {
        $order = $this->order();
        $public = $this->order(true);
        $reviews = app(PaymentReviewService::class);
        $refunds = app(RefundService::class);
        $this->confirmPaymentFixture($public);
        $proof = $reviews->submit($public, UploadedFile::fake()->image('receipt.png'), 'SERVICE-1');
        $this->confirmPaymentFixture($order);
        $this->orders->recordDownPayment($order, 1000, 'cash', null, $this->owner);
        $refund = $refunds->markBakeryFailure($order, 'Oven failure', $this->owner, true);
        $active = $this->order();
        foreach ([
            fn () => $this->orders->createInternalOrder($this->payload(), $this->assistant),
            fn () => $this->orders->attachImage($active, 'fake.png', 'fake.png', null, $this->assistant),
            fn () => $this->orders->recordDownPayment($active, 1000, 'cash', null, $this->assistant),
            fn () => $this->orders->recordFinalPayment($active, 1000, 'cash', null, $this->assistant, null, true),
            fn () => $this->orders->completePickup($active, 'cash', null, $this->assistant, true),
            fn () => $this->orders->cancelOrder($active, $this->assistant),
            fn () => $this->orders->updateStatus($active, 'cancelled', $this->assistant),
            fn () => $reviews->accept($proof, 1000, 'SERVICE-1', $this->assistant),
            fn () => $reviews->reject($proof, 'Not received', $this->assistant),
            fn () => $refunds->markBakeryFailure($active, 'Oven failure', $this->assistant, true),
            fn () => $refunds->complete($refund, ['method' => 'cash', 'reference_number' => 'RETURN', 'transfer_confirmed' => '1'], $this->assistant),
        ] as $action) {
            $this->denied($action, AuthorizationException::class);
        }
    }

    public function test_exact_deposit_and_early_final_payment_rejections_leave_ledger_and_references_unchanged(): void
    {
        $order = $this->order();
        foreach ([999.99, 1000.001, 1000.01, 2000] as $amount) {
            $this->denied(fn () => $this->orders->recordDownPayment($order, $amount, 'gcash', 'EXCESS', $this->owner));
        }
        $this->denied(fn () => $this->orders->recordFinalPayment($order, 1000, 'gcash', 'EARLY', $this->owner, null, true));
        $this->confirmPaymentFixture($order);
        $this->orders->recordDownPayment($order, 1000, 'cash', null, $this->owner);
        $this->denied(fn () => $this->orders->recordDownPayment($order, 1000, 'cash', null, $this->owner));
        foreach (['confirmed', 'preparing'] as $state) {
            if ($state === 'preparing') {
                $this->orders->updateStatus($order, $state, $this->assistant);
            }
            $this->denied(fn () => $this->orders->recordFinalPayment($order, 1000, 'gcash', 'EARLY', $this->owner, null, true));
            $before = $this->snapshot();
            $this->actingAs($this->owner)->post('/orders/'.$order->id.'/payments', ['payment_type' => 'final_payment', 'amount' => 1000,
                'payment_method' => 'gcash', 'reference_number' => 'EARLY', 'pickup_confirmed' => '1'])->assertSessionHasErrors('status');
            $this->post('/orders/'.$order->id.'/complete-pickup', ['payment_method' => 'gcash', 'reference_number' => 'EARLY', 'pickup_confirmed' => '1'])->assertSessionHasErrors('status');
            $this->assertSame($before, $this->snapshot());
            $this->get('/orders/'.$order->id)->assertDontSee('name="pickup_confirmed"', false);
        }
    }

    public function test_ready_pickup_requires_acknowledgment_uses_saved_balance_and_completes_atomically(): void
    {
        $order = $this->order();
        $this->ready($order);
        $this->denied(fn () => $this->orders->recordFinalPayment($order, 1000, 'cash', null, $this->owner));
        $this->denied(fn () => $this->orders->updateStatus($order, 'completed', $this->assistant));
        $before = $this->snapshot();
        $this->actingAs($this->owner)->post('/orders/'.$order->id.'/complete-pickup', ['payment_method' => 'cash'])->assertSessionHasErrors('pickup_confirmed');
        $this->assertSame($before, $this->snapshot());
        $this->post('/orders/'.$order->id.'/complete-pickup', ['amount' => 1, 'status' => 'confirmed', 'payment_method' => 'gcash',
            'reference_number' => 'COLLECT-1', 'pickup_confirmed' => '1'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('completed', $order->fresh()->status);
        $this->assertSame(2000.0, $order->fresh()->amount_paid);
        $this->assertNotNull($order->fresh()->completed_at);
        $this->assertDatabaseCount('payments', 2);
        $this->denied(fn () => $this->orders->completePickup($order, 'gcash', 'REPLAY', $this->owner, true));
        $this->denied(fn () => $this->orders->cancelOrder($order, $this->owner));
    }

    public function test_a_post_payment_write_failure_rolls_back_payment_reference_and_completion(): void
    {
        $order = $this->order();
        $this->ready($order);
        $firstWriteObserved = false;
        Event::listen('eloquent.created: '.Payment::class, function ($payment) use (&$firstWriteObserved) {
            if ($payment->payment_type === 'final_payment') {
                $firstWriteObserved = DB::table('payments')->where('id', $payment->id)->exists();
                throw new \RuntimeException('Injected failure after payment insertion');
            }
        });
        $this->denied(fn () => $this->orders->completePickup($order, 'gcash', 'ROLLBACK', $this->owner, true), \RuntimeException::class);
        $this->assertTrue($firstWriteObserved);
        $this->assertSame('ready_for_pickup', $order->fresh()->status);
    }

    public function test_full_refund_uses_received_money_and_allows_genuine_failure_after_on_time_readiness(): void
    {
        $refunds = app(RefundService::class);
        $unpaid = $this->order();
        $this->assertNull($refunds->markBakeryFailure($unpaid, 'Cannot bake', $this->owner, true));
        $this->assertDatabaseCount('refunds', 0);
        $paid = $this->order();
        $this->ready($paid);
        $this->denied(fn () => $refunds->markBakeryFailure($paid, 'Customer is late', $this->owner));
        $this->assertTrue($paid->fresh()->ready_at->lte($paid->pickupDeadline()));
        $refund = $refunds->markBakeryFailure($paid, 'Cake damaged after readiness; cannot supply a replacement.', $this->owner, true);
        $this->assertEquals(1000, $refund->amount);
        $this->assertSame('pending', $refund->status);
        $this->denied(fn () => $refunds->complete($refund, ['method' => 'cash', 'reference_number' => 'RETURN'], $this->owner));
        $data = ['method' => 'cash', 'reference_number' => 'RETURN', 'transfer_confirmed' => '1'];
        $refunds->complete($refund, $data, $this->owner);
        $this->assertSame('completed', $refund->fresh()->status);
        $this->denied(fn () => $refunds->complete($refund, $data, $this->owner));
        $this->denied(fn () => $refunds->markBakeryFailure($paid, 'Again', $this->owner, true));
        $collected = $this->order();
        $this->ready($collected);
        $this->orders->completePickup($collected, 'cash', null, $this->owner, true);
        $this->denied(fn () => $refunds->markBakeryFailure($collected, 'Cannot reverse completed pickup', $this->owner, true));
    }

    public function test_legacy_fully_paid_orders_are_preserved_refunded_fully_or_collected_without_another_charge(): void
    {
        foreach (['failure', 'collection'] as $case) {
            $order = $this->order();
            $this->ready($order);
            // Explicit legacy fixture; the new workflow cannot prepay this balance.
            $order->payments()->create(['user_id' => $this->owner->id, 'amount' => 1000, 'payment_type' => 'final_payment',
                'payment_method' => 'cash', 'payment_date' => now()->subDay()]);
            $ledger = $order->payments()->get()->toArray();
            if ($case === 'failure') {
                $refund = app(RefundService::class)->markBakeryFailure($order, 'Legacy paid order cannot be supplied', $this->owner, true);
                $this->assertEquals(2000, $refund->amount);
            } else {
                $this->orders->updateStatus($order, 'completed', $this->assistant);
                $this->assertSame('completed', $order->fresh()->status);
            }
            $this->assertSame($ledger, $order->payments()->get()->toArray());
        }
    }

    public function test_excessive_public_proof_is_not_accepted_as_a_deposit_or_refund(): void
    {
        $order = $this->order(true);
        $this->confirmPaymentFixture($order);
        $proof = app(PaymentReviewService::class)->submit($order, UploadedFile::fake()->image('receipt.png'), 'OVER-1');
        $this->denied(fn () => app(PaymentReviewService::class)->accept($proof, 2000, 'OVER-1', $this->owner));
        $this->assertSame('awaiting_verification', $proof->fresh()->status);
        app(PaymentReviewService::class)->accept($proof, 1000, 'OVER-1', $this->owner);
        $this->assertSame('confirmed', $order->fresh()->status);
        $this->assertDatabaseCount('refunds', 0);
    }

    public function test_guest_and_inactive_staff_do_not_mutate_internal_records(): void
    {
        $order = $this->order();
        $payload = ['payment_type' => 'down_payment', 'amount' => 1000, 'payment_method' => 'cash'];
        $before = $this->snapshot();
        $this->post('/orders/'.$order->id.'/payments', $payload)->assertRedirect('/login');
        $this->owner->update(['is_active' => false]);
        $this->actingAs($this->owner)->post('/orders/'.$order->id.'/payments', $payload)->assertForbidden();
        $this->get('/reports')->assertForbidden();
        $this->assertSame($before, $this->snapshot());
    }
}
