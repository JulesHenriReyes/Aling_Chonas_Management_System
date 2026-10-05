<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Order;
use App\Models\PaymentProof;
use App\Models\PaymentSetting;
use App\Models\Product;
use App\Models\User;
use App\Services\FinancialReportService;
use App\Services\OrderReviewService;
use App\Services\OrderService;
use App\Services\PaymentReviewService;
use App\Services\RefundService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class StaffConfirmationBeforePaymentTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private User $assistant;
    private Customer $customer;
    private Product $product;
    private OrderService $orders;
    private OrderReviewService $reviews;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->create(['role' => 'owner', 'is_active' => true]);
        $this->assistant = User::factory()->create(['role' => 'assistant', 'is_active' => true]);
        $this->customer = Customer::create(['first_name' => 'Review', 'last_name' => 'Buyer', 'phone_number' => '09171234567']);
        $this->product = Product::create(['product_name' => 'Review cake', 'price' => 2000, 'is_active' => true]);
        $this->product->options()->create(['layers' => 1, 'price' => 2000, 'included_contents' => 'Cake', 'is_active' => true]);
        $this->orders = app(OrderService::class);
        $this->reviews = app(OrderReviewService::class);
        foreach (['public', 'local', 'receipts'] as $disk) Storage::fake($disk);
        Storage::disk('public')->put('business/review-qr.png', 'synthetic-qr');
        PaymentSetting::create(['account_name' => 'Synthetic bakery', 'account_number' => '09171234567', 'qr_path' => 'business/review-qr.png', 'updated_by' => $this->owner->id]);
    }

    private function data(): array
    {
        return ['customer_id' => $this->customer->id, 'pickup_date' => now()->addDays(3)->toDateString(), 'pickup_time' => '15:00',
            'expected_total' => 2000, 'items' => [['product_id' => $this->product->id,
                'package_option_id' => $this->product->options()->first()->id, 'quantity' => 1]]];
    }

    private function order(bool $public = true): Order
    {
        return $public ? $this->orders->createPublicOrder($this->data()) : $this->orders->createInternalOrder($this->data(), $this->owner);
    }

    private function state(): array
    {
        $state = [];
        foreach (['orders', 'payments', 'payment_proofs', 'gcash_references', 'refunds'] as $table) {
            $state[$table] = DB::table($table)->get()->map(fn ($row) => (array) $row)->all();
        }
        $state['receipts'] = Storage::disk('receipts')->allFiles();

        return $state;
    }

    private function blocked(callable $action): void
    {
        $before = $this->state();
        try {
            $action();
            $this->fail('Expected a rejected action.');
        } catch (ValidationException $error) {
            $this->assertNotEmpty($error->errors());
        }
        $this->assertSame($before, $this->state());
    }

    private function legacyProof(Order $order): PaymentProof
    {
        $order->forceFill(['review_status' => null, 'reviewed_by' => null, 'reviewed_at' => null])->save();
        Storage::disk('receipts')->put('legacy.jpg', 'synthetic');

        return $order->paymentProofs()->create(['file_path' => 'legacy.jpg', 'reference_number' => 'LEGACY-RECEIVED']);
    }

    public function test_public_submission_requires_review_and_cannot_self_approve(): void
    {
        $this->post(route('public.order.store'), $this->data() + ['first_name' => 'Review', 'last_name' => 'Buyer',
            'phone_number' => '09171234567', 'review_status' => 'approved', 'reviewed_by' => $this->owner->id,
            'reviewed_at' => now()->toDateTimeString(), 'status' => 'confirmed'])
            ->assertRedirect()->assertSessionHas('success');
        $order = Order::firstOrFail();
        $this->assertSame('pending', $order->status);
        $this->assertSame('pending', $order->review_status);
        $this->assertNull($order->reviewed_by);
        $this->assertNull($order->reviewed_at);
        $this->assertNull($order->user_id);
        $this->get(route('public.order.payment', $order->private_token))->assertOk()
            ->assertSee('Awaiting staff confirmation')->assertDontSee('Pay your GCash deposit')
            ->assertDontSee('Business GCash payment QR')->assertDontSee('name="receipt"', false)
            ->assertDontSee('Synthetic bakery');
        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseCount('payment_proofs', 0);
        $this->assertDatabaseCount('refunds', 0);
    }

    public function test_unreviewed_qr_receipt_and_staff_deposit_paths_leave_no_side_effects(): void
    {
        $public = $this->order();
        $internal = $this->order(false);
        $before = $this->state();
        $this->get(route('public.order.qr', $public->private_token))->assertForbidden();
        $this->post(route('public.order.receipt', $public->private_token), ['receipt' => UploadedFile::fake()->image('receipt.jpg'), 'reference_number' => 'TOO-EARLY'])->assertSessionHasErrors('receipt');
        $this->actingAs($this->owner)->post(route('orders.payments.store', $internal), ['payment_type' => 'down_payment', 'amount' => 1000, 'payment_method' => 'cash'])->assertSessionHasErrors('status');
        $this->assertSame($before, $this->state());
        $this->blocked(fn () => app(PaymentReviewService::class)->submit($public, UploadedFile::fake()->image('receipt.jpg'), 'EARLY-SERVICE'));
        $this->blocked(fn () => $this->orders->recordDownPayment($internal, 1000, 'cash', null, $this->owner));
    }

    public function test_owner_and_assistant_can_confirm_without_money_and_replay_preserves_audit(): void
    {
        foreach ([$this->owner, $this->assistant] as $staff) {
            $order = $this->order();
            $this->actingAs($staff)->post(route('orders.confirm', $order), ['feasibility_confirmed' => 1])->assertRedirect()->assertSessionHasNoErrors();
            $order->refresh();
            $this->assertSame('confirmed', $order->status);
            $this->assertSame('approved', $order->review_status);
            $this->assertSame($staff->id, $order->reviewed_by);
            $this->assertNotNull($order->reviewed_at);
            $reviewTime = $order->reviewed_at->toDateTimeString();
            $this->reviews->confirm($order, $this->owner, true);
            $this->assertSame($staff->id, $order->fresh()->reviewed_by);
            $this->assertSame($reviewTime, $order->fresh()->reviewed_at->toDateTimeString());
            $this->assertSame(0.0, $order->fresh()->amount_paid);
            $this->get(route('public.order.payment', $order->private_token))->assertSee('Confirmed')->assertSee('awaiting deposit')->assertSee('Pay your GCash deposit')->assertSee('Business GCash payment QR');
            $this->get(route('public.order.qr', $order->private_token))->assertOk();
        }
        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseCount('refunds', 0);
    }

    public function test_confirmation_requires_acknowledgment_valid_pickup_and_active_staff(): void
    {
        $order = $this->order();
        $before = $this->state();
        $this->post(route('orders.confirm', $order), ['feasibility_confirmed' => 1])->assertRedirect(route('login'));
        $this->actingAs($this->assistant)->post(route('orders.confirm', $order), [])->assertSessionHasErrors('feasibility_confirmed');
        $this->actingAs($this->assistant)->patch(route('orders.updateStatus', $order), ['status' => 'confirmed'])->assertSessionHasErrors('status');
        $this->assertSame($before, $this->state());
        $this->assistant->update(['is_active' => false]);
        $this->actingAs($this->assistant)->post(route('orders.confirm', $order), ['feasibility_confirmed' => 1])->assertForbidden();
        $this->blocked(fn () => $this->reviews->confirm($order, $this->assistant, true));
        $order->update(['pickup_date' => now()->subDay()->toDateString()]);
        $this->blocked(fn () => $this->reviews->confirm($order, $this->owner, true));
    }

    public function test_decline_requires_owner_and_reason_and_remains_terminal(): void
    {
        $order = $this->order();
        $this->actingAs($this->assistant)->post(route('orders.decline', $order), ['decline_reason' => 'Capacity unavailable'])->assertForbidden();
        $this->actingAs($this->owner)->post(route('orders.decline', $order), [])->assertSessionHasErrors('decline_reason');
        $this->actingAs($this->owner)->post(route('orders.decline', $order), ['decline_reason' => 'The requested design cannot be made safely.'])->assertSessionHasNoErrors();
        $order->refresh();
        $this->assertSame('staff_rejected', $order->cancellation_kind);
        $this->assertSame('rejected', $order->review_status);
        $this->assertSame($this->owner->id, $order->reviewed_by);
        $before = $this->state();
        $this->reviews->decline($order, 'Replay must not change the reason', $this->owner);
        $this->assertSame($before, $this->state());
        $this->blocked(fn () => $this->reviews->confirm($order, $this->owner, true));
        $this->get(route('public.order.payment', $order->private_token))->assertSee('Request declined')->assertSee('cannot be made safely')->assertDontSee('Pay your GCash deposit');
        $this->get(route('public.order.qr', $order->private_token))->assertForbidden();
        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseCount('refunds', 0);
    }

    public function test_receipt_review_and_replacement_do_not_repeat_approval_or_create_payment(): void
    {
        $order = $this->order();
        $this->reviews->confirm($order, $this->assistant, true);
        $review = $order->fresh()->reviewed_at->toDateTimeString();
        $payments = app(PaymentReviewService::class);
        $proof = $payments->submit($order, UploadedFile::fake()->image('first.jpg'), 'REF-FIRST');
        $this->assertSame('confirmed', $order->fresh()->status);
        $this->assertSame(0.0, $order->fresh()->amount_paid);
        $this->get(route('public.order.payment', $order->private_token))->assertSee('Please do not pay again')->assertDontSee('Pay your GCash deposit');
        $this->get(route('public.order.qr', $order->private_token))->assertForbidden();
        $this->blocked(fn () => $payments->submit($order, UploadedFile::fake()->image('duplicate.jpg'), 'OTHER'));
        $payments->reject($proof, 'The receipt is unreadable; check the actual transfer first.', $this->owner);
        $this->get(route('public.order.payment', $order->private_token))->assertSee('Correct your payment receipt')->assertSee('do not pay again');
        $replacement = $payments->submit($order, UploadedFile::fake()->image('replacement.jpg'), 'REF-REPLACEMENT');
        $payments->accept($replacement, 1000, 'REF-REPLACEMENT', $this->owner);
        $this->assertSame($review, $order->fresh()->reviewed_at->toDateTimeString());
        $this->assertSame($this->assistant->id, $order->fresh()->reviewed_by);
        $this->assertSame('confirmed', $order->fresh()->status);
        $this->assertSame(1000.0, $order->fresh()->amount_paid);
        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseCount('payment_proofs', 2);
        $this->blocked(fn () => $payments->accept($replacement, 1000, 'REF-REPLACEMENT', $this->owner));
    }

    public function test_preparation_needs_verified_exact_deposit_and_owner_financial_permissions(): void
    {
        $order = $this->order(false);
        $this->reviews->confirm($order, $this->assistant, true);
        $this->blocked(fn () => $this->orders->updateStatus($order, 'preparing', $this->assistant));
        $this->actingAs($this->assistant)->post(route('orders.payments.store', $order), ['payment_type' => 'down_payment', 'amount' => 1000, 'payment_method' => 'cash'])->assertForbidden();
        $this->blocked(fn () => $this->orders->recordDownPayment($order, 999, 'cash', null, $this->owner));
        $this->orders->recordDownPayment($order, 1000, 'cash', null, $this->owner);
        $this->orders->updateStatus($order, 'preparing', $this->assistant);
        $this->orders->updateStatus($order, 'ready_for_pickup', $this->assistant);
        $this->orders->completePickup($order, 'cash', null, $this->owner, true);
        $this->assertSame('completed', $order->fresh()->status);
        $this->assertSame(2000.0, $order->fresh()->amount_paid);
        $this->assertDatabaseCount('payments', 2);
    }

    public function test_review_and_decline_have_no_financial_effect_and_queues_are_distinct(): void
    {
        $pending = $this->order(false);
        $unpaid = $this->order(false);
        $receiptOrder = $this->order();
        $booked = $this->order(false);
        $declined = $this->order(false);
        foreach ([$unpaid, $receiptOrder, $booked] as $order) $this->reviews->confirm($order, $this->owner, true);
        $this->reviews->decline($declined, 'Pickup capacity unavailable.', $this->owner);
        app(PaymentReviewService::class)->submit($receiptOrder, UploadedFile::fake()->image('pending.jpg'), 'PENDING-ONLY');
        $report = app(FinancialReportService::class);
        $this->assertSame(0.0, $report->getPaymentCollections(today(), today()));
        $this->assertSame(0.0, $report->getCancellationIncome(today(), today()));
        $this->assertSame(0.0, $report->getSales(today(), today()));
        $this->orders->recordDownPayment($booked, 1000, 'cash', null, $this->owner);
        $expected = ['review' => [$pending->id], 'deposit' => [$unpaid->id], 'receipts' => [$receiptOrder->id], 'booked' => [$booked->id]];
        foreach ($expected as $queue => $ids) $this->assertSame($ids, Order::workflowQueue($queue)->pluck('id')->all());
        $this->actingAs($this->assistant)->get('/dashboard')->assertOk()->assertSee('Confirmed')->assertSee('awaiting deposit: 1')->assertSee('Receipt awaiting verification: 1');
        $this->get('/orders?queue=deposit')->assertOk()->assertSee($unpaid->order_number)->assertDontSee($booked->order_number);
        $this->get('/pickup-schedule')->assertOk()->assertSee('awaiting deposit');
    }

    public function test_legacy_proof_requires_owner_and_can_be_verified_after_explicit_feasible_approval(): void
    {
        $order = $this->order();
        $proof = $this->legacyProof($order);
        $this->actingAs($this->assistant)->post(route('orders.confirm', $order), ['feasibility_confirmed' => 1])->assertForbidden();
        $this->blocked(fn () => $this->reviews->decline($order, 'Cannot fulfil', $this->owner));
        $this->blocked(fn () => app(RefundService::class)->markBakeryFailure($order, 'Cannot fulfil', $this->owner, true));
        $this->blocked(fn () => $this->orders->cancelOrder($order, $this->owner));
        $this->reviews->confirm($order, $this->owner, true);
        $this->get(route('public.order.payment', $order->private_token))->assertSee('do not pay again')->assertDontSee('Pay your GCash deposit');
        app(PaymentReviewService::class)->accept($proof, 1000, 'LEGACY-RECEIVED', $this->owner);
        $this->assertSame(1000.0, $order->fresh()->amount_paid);
        $this->assertDatabaseCount('payment_proofs', 1);
    }

    public function test_legacy_received_transfer_can_be_reconciled_to_full_refund_without_approval(): void
    {
        $order = $this->order();
        $proof = $this->legacyProof($order);
        $this->blocked(fn () => $this->orders->refundLegacyDeposit($order, $proof, 1000, 'LEGACY-RECEIVED', 'Impossible request', $this->owner));
        $refund = $this->orders->refundLegacyDeposit($order, $proof, 1000, 'LEGACY-RECEIVED', 'The requested design cannot be fulfilled.', $this->owner, true);
        $order->refresh();
        $this->assertSame('cancelled', $order->status);
        $this->assertSame('bakery_failure', $order->cancellation_kind);
        $this->assertNull($order->review_status);
        $this->assertNull($order->reviewed_by);
        $this->assertNull($order->reviewed_at);
        $this->assertSame('verified', $proof->fresh()->status);
        $this->assertEquals(1000, $refund->amount);
        $this->assertSame('pending', $refund->status);
        $this->assertDatabaseCount('payments', 1);
        $this->get(route('public.order.payment', $order->private_token))->assertSee('Full refund')->assertDontSee('Pay your GCash deposit');
        $this->blocked(fn () => $this->orders->refundLegacyDeposit($order, $proof, 1000, 'LEGACY-RECEIVED', 'Replay', $this->owner, true));
    }

    public function test_legacy_refund_route_requires_owner_account_checks_and_exact_matching_funds(): void
    {
        $order = $this->order();
        $proof = $this->legacyProof($order);
        $url = route('proofs.reconcileRefund', $proof);
        $data = ['amount' => 1000, 'reference_number' => 'LEGACY-RECEIVED', 'reason' => 'The design cannot be fulfilled.',
            'account_checked' => 1, 'bakery_failure_confirmed' => 1];
        $before = $this->state();
        $this->post($url, $data)->assertRedirect(route('login'));
        $this->actingAs($this->assistant)->post($url, $data)->assertForbidden();
        $inactive = User::factory()->create(['role' => 'owner', 'is_active' => false]);
        $this->actingAs($inactive)->post($url, $data)->assertForbidden();
        $this->assertSame($before, $this->state());
        foreach ([['account_checked' => 0], ['bakery_failure_confirmed' => 0], ['amount' => 999], ['reference_number' => 'MISMATCH']] as $invalid) {
            $this->actingAs($this->owner)->post($url, array_replace($data, $invalid))->assertSessionHasErrors();
            $this->assertSame($before, $this->state());
        }
        $this->actingAs($this->owner)->post($url, $data)->assertRedirect(route('orders.show', $order))->assertSessionHasNoErrors();
        $this->assertSame('cancelled', $order->fresh()->status);
        $this->assertNull($order->fresh()->reviewed_at);
        $this->assertEquals(1000, $order->fresh()->refund->amount);
        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseCount('refunds', 1);
    }

    public function test_legacy_no_transfer_decline_requires_explicit_account_check(): void
    {
        $order = $this->order();
        $this->legacyProof($order);
        $this->reviews->decline($order, 'No matching transfer was received; pickup unavailable.', $this->owner, true);
        $this->assertSame('staff_rejected', $order->fresh()->cancellation_kind);
        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseCount('payment_proofs', 1);
    }

    public function test_previously_paid_work_is_preserved_and_unpaid_legacy_confirmation_needs_review(): void
    {
        $paid = $this->order(false);
        $paid->forceFill(['status' => 'confirmed', 'review_status' => null])->save();
        $paid->payments()->create(['user_id' => $this->owner->id, 'amount' => 1000, 'payment_type' => 'down_payment', 'payment_method' => 'cash', 'payment_date' => now()]);
        $this->orders->updateStatus($paid, 'preparing', $this->assistant);
        $this->assertNull($paid->fresh()->reviewed_at);
        $unpaid = $this->order(false);
        $unpaid->forceFill(['status' => 'confirmed', 'review_status' => null])->save();
        $this->blocked(fn () => $this->orders->recordDownPayment($unpaid, 1000, 'cash', null, $this->owner));
        $this->blocked(fn () => $this->orders->updateStatus($unpaid, 'preparing', $this->assistant));
        $this->reviews->confirm($unpaid, $this->owner, true);
        $this->orders->recordDownPayment($unpaid, 1000, 'cash', null, $this->owner);
        $this->assertSame('confirmed', $unpaid->fresh()->status);
    }
}
