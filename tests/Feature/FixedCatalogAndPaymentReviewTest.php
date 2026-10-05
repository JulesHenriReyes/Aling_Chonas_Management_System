<?php

namespace Tests\Feature;

use App\Models\AddOn;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\FinancialReportService;
use App\Services\OrderService;
use App\Services\PaymentReviewService;
use App\Services\RefundService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class FixedCatalogAndPaymentReviewTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private Customer $customer;
    private Product $product;
    private AddOn $extra;
    private OrderService $orders;
    private PaymentReviewService $reviews;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(\Carbon\Carbon::parse('2026-10-05 04:00:00', 'UTC'));
        $this->owner = User::factory()->create(['role' => 'owner', 'is_active' => true]);
        $this->customer = Customer::create(['first_name' => 'Test', 'last_name' => 'Buyer', 'phone_number' => '09171234567']);
        $this->product = Product::create(['product_name' => 'Celebration package', 'price' => 9999, 'is_active' => true]);
        $this->product->options()->create(['layers' => 2, 'included_contents' => 'Two-layer cake and 8 cupcakes', 'price' => 1200, 'is_active' => true]);
        $this->extra = AddOn::create(['name' => 'Extra cupcakes', 'description' => '6 extra cupcakes beyond package inclusions', 'price' => 150, 'is_active' => true]);
        $this->product->addOns()->attach($this->extra);
        $this->orders = app(OrderService::class);
        $this->reviews = app(PaymentReviewService::class);
        Storage::fake('receipts');
    }

    private function data(): array
    {
        return [
            'customer_id' => $this->customer->id, 'pickup_date' => now()->addDay()->toDateString(), 'pickup_time' => '15:00',
            'items' => [[
                'product_id' => $this->product->id, 'package_option_id' => $this->product->options()->first()->id,
                'quantity' => 2, 'unit_price' => 1, 'layers' => 9,
                'themes' => 'Flowers', 'special_request' => 'Blue icing',
                'add_ons' => [['add_on_id' => $this->extra->id, 'quantity' => 2, 'unit_price' => 1]],
            ]],
        ];
    }

    private function rejected(callable $action): void
    {
        try {
            $action();
            $this->fail('Expected a validation rejection.');
        } catch (ValidationException $exception) {
            $this->assertNotEmpty($exception->errors());
        }
    }

    public function test_both_paths_use_catalog_prices_and_keep_inclusions_separate_from_extras(): void
    {
        foreach ([null, $this->owner] as $staff) {
            $order = $staff ? $this->orders->createInternalOrder($this->data(), $staff) : $this->orders->createPublicOrder($this->data());
            $line = $order->orderDetails->first();
            $this->assertEquals(1200, $line->unit_price);
            $this->assertSame(2, $line->layers);
            $this->assertSame('Two-layer cake and 8 cupcakes', $line->included_contents_snapshot);
            $this->assertSame('Flowers', $line->themes);
            $this->assertSame('Blue icing', $line->special_request);
            $this->assertSame(2, $line->addOns->first()->quantity);
            $this->assertEquals(150, $line->addOns->first()->unit_price);
            $this->assertSame(2700.0, $order->total_amount);
            $this->assertSame(2700.0, Order::find($order->id)->total_amount);
            $this->assertSame(1350.0, $order->required_down_payment);
            $this->assertSame($staff?->id, $order->user_id);
        }
    }

    public function test_snapshots_survive_catalog_changes_and_manual_price_changes_are_rejected(): void
    {
        $order = $this->orders->createPublicOrder($this->data());
        $this->product->update(['product_name' => 'Renamed', 'price' => 55]);
        $this->product->options()->first()->update(['price' => 5000, 'included_contents' => 'Different contents', 'layers' => 3]);
        $this->extra->update(['name' => 'Renamed extra', 'price' => 900]);
        $order->refresh();
        $this->assertSame(2700.0, $order->total_amount);
        $this->assertSame('Celebration package', $order->orderDetails->first()->product_name_snapshot);
        $this->assertSame('Extra cupcakes', $order->orderDetails->first()->addOns->first()->name_snapshot);
        $this->rejected(fn () => $this->orders->updateOrderDetailPrice($order, $order->orderDetails->first(), 1, $this->owner));
        $this->assertSame(2700.0, $order->fresh()->total_amount);
    }

    public function test_invalid_option_unavailable_product_inapplicable_extra_and_fractional_quantity_are_rejected(): void
    {
        $data = $this->data();
        unset($data['items'][0]['package_option_id']);
        $this->rejected(fn () => $this->orders->createPublicOrder($data));
        $data = $this->data();
        $data['items'][0]['quantity'] = 1.5;
        $this->rejected(fn () => $this->orders->createPublicOrder($data));
        $this->product->addOns()->detach();
        $this->rejected(fn () => $this->orders->createInternalOrder($this->data(), $this->owner));
        $this->product->update(['is_active' => false]);
        $this->rejected(fn () => $this->orders->createInternalOrder($this->data(), $this->owner));
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_proof_is_private_and_does_not_count_as_payment_until_exact_verification(): void
    {
        $order = $this->orders->createPublicOrder($this->data());
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $order->private_token);
        $this->assertArrayNotHasKey('private_token', $order->toArray());
        $proof = $this->reviews->submit($order, UploadedFile::fake()->image('receipt.png'), ' REF 001 ');
        Storage::disk('receipts')->assertExists($proof->file_path);
        $this->assertSame('REF001', $proof->reference_number);
        $this->assertSame(0.0, $order->fresh()->amount_paid);
        $reports = app(FinancialReportService::class);
        $this->assertSame(0.0, $reports->getPaymentCollections(today(), today()));
        $this->rejected(fn () => $this->orders->recordDownPayment($order, 1350, 'cash', null, $this->owner));
        $this->rejected(fn () => $this->reviews->accept($proof, 1350, 'WRONG', $this->owner));
        $this->rejected(fn () => $this->reviews->accept($proof, 1349, 'REF001', $this->owner));
        $this->assertDatabaseCount('payments', 0);
        $this->reviews->accept($proof, 1350, 'ref001', $this->owner);
        $this->assertSame('confirmed', $order->fresh()->status);
        $this->assertSame('verified', $proof->fresh()->status);
        $this->assertSame(1350.0, $order->fresh()->amount_paid);
        $this->rejected(fn () => $this->reviews->accept($proof, 1350, 'REF001', $this->owner));
        $this->assertDatabaseCount('payments', 1);
        $this->assertSame(1350.0, $reports->getPaymentCollections(today(), today()));
    }

    public function test_rejection_retains_history_and_allows_replacement_but_pending_proof_blocks_replacement(): void
    {
        $order = $this->orders->createPublicOrder($this->data());
        $first = $this->reviews->submit($order, UploadedFile::fake()->image('first.jpg'), 'A001');
        $this->rejected(fn () => $this->reviews->submit($order, UploadedFile::fake()->image('second.jpg'), 'A002'));
        $this->reviews->reject($first, 'Reference was not found in the business account.', $this->owner);
        $second = $this->reviews->submit($order, UploadedFile::fake()->image('second.jpg'), 'A002');
        $this->assertSame('rejected', $first->fresh()->status);
        $this->assertSame('awaiting_verification', $second->status);
        $this->assertDatabaseCount('payment_proofs', 2);
        $this->assertCount(2, Storage::disk('receipts')->allFiles());
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_duplicates_across_orders_and_deactivated_staff_are_rejected(): void
    {
        $first = $this->orders->createPublicOrder($this->data());
        $second = $this->orders->createPublicOrder($this->data());
        $proof = $this->reviews->submit($first, UploadedFile::fake()->image('one.jpg'), 'DUP-1');
        $other = $this->reviews->submit($second, UploadedFile::fake()->image('two.jpg'), 'DUP-1');
        $this->reviews->accept($proof, 1350, 'DUP-1', $this->owner);
        $this->rejected(fn () => $this->reviews->accept($other, 1350, 'dup-1', $this->owner));
        $this->assertSame('pending', $second->fresh()->status);
        $this->owner->update(['is_active' => false]);
        $this->rejected(fn () => $this->reviews->reject($other, 'Cannot verify', $this->owner));
        $this->assertDatabaseCount('payments', 1);
    }

    public function test_cash_workflow_and_readiness_before_collection_are_preserved(): void
    {
        $order = $this->orders->createInternalOrder($this->data(), $this->owner);
        $this->orders->recordDownPayment($order, 1350, 'cash', null, $this->owner);
        $this->orders->updateStatus($order, 'preparing', $this->owner);
        $this->orders->updateStatus($order, 'ready_for_pickup', $this->owner);
        $ready = $order->fresh()->ready_at;
        $this->assertNotNull($ready);
        $this->travel(3)->days();
        $this->rejected(fn () => app(RefundService::class)->markBakeryFailure($order, 'Customer collected late', $this->owner));
        $this->orders->completePickup($order, 'cash', null, $this->owner, true);
        $this->assertSame('completed', $order->fresh()->status);
        $this->assertTrue($order->fresh()->ready_at->equalTo($ready));
        $this->assertSame(2700.0, app(FinancialReportService::class)->getSales(today(), today()));
    }

    public function test_bakery_failure_refunds_all_verified_money_only_after_transfer_confirmation(): void
    {
        $order = $this->orders->createInternalOrder($this->data(), $this->owner);
        $this->orders->recordDownPayment($order, 1350, 'cash', null, $this->owner);
        // Explicit legacy full prepayment, not a supported advance-payment path.
        $order->payments()->create(['user_id' => $this->owner->id, 'amount' => 1350, 'payment_type' => 'final_payment', 'payment_method' => 'gcash', 'reference_number' => 'FINAL-1', 'payment_date' => now()]);
        $refunds = app(RefundService::class);
        $refund = $refunds->markBakeryFailure($order, 'Oven failure; cannot finish by pickup.', $this->owner, true);
        $this->assertSame('pending', $refund->status);
        $this->assertEquals(2700, $refund->amount);
        $reports = app(FinancialReportService::class);
        $this->assertSame(0.0, $reports->getCancellationIncome(today(), today()));
        $this->assertSame(2700.0, $reports->getPaymentCollections(today(), today()));
        $this->rejected(fn () => $refunds->complete($refund, ['method' => 'gcash', 'reference_number' => 'REFUND-1'], $this->owner));
        $data = ['method' => 'gcash', 'reference_number' => 'REFUND-1', 'transfer_confirmed' => '1'];
        $refunds->complete($refund, $data, $this->owner);
        $this->assertSame(0.0, $reports->getPaymentCollections(today(), today()));
        $this->assertSame('completed', $refund->fresh()->status);
        $this->assertEquals($this->owner->id, $refund->fresh()->completed_by);
        $this->assertDatabaseCount('payments', 2);
        $this->rejected(fn () => $refunds->complete($refund, $data, $this->owner));
        $this->rejected(fn () => $this->orders->recordFinalPayment($order, 1350, 'cash', null, $this->owner));
    }

    public function test_receipts_reject_non_images_and_oversized_images(): void
    {
        $order = $this->orders->createPublicOrder($this->data());
        $this->rejected(fn () => $this->reviews->submit($order, UploadedFile::fake()->create('receipt.html', 10, 'text/html'), 'BAD1'));
        $this->rejected(fn () => $this->reviews->submit($order, UploadedFile::fake()->image('large.png')->size(5121), 'BAD2'));
        $this->assertDatabaseCount('payment_proofs', 0);
        $this->assertCount(0, Storage::disk('receipts')->allFiles());
    }

    public function test_http_public_flow_has_persistent_token_access_and_private_receipt_authorization(): void
    {
        $this->get('/')->assertOk()->assertSee('Select package');
        $data = $this->data() + ['first_name' => 'Test', 'last_name' => 'Buyer', 'phone_number' => '09171234567', 'expected_total' => 2700];
        $this->postJson(route('public.order.quote'), ['items' => $data['items']])->assertOk()->assertJsonPath('total', 2700)->assertJsonPath('deposit', 1350);
        $response = $this->post(route('public.order.store'), $data);
        $order = Order::sole();
        $url = route('public.order.payment', $order->private_token);
        $response->assertRedirect($url);
        $this->flushSession();
        foreach ([1, 2] as $refresh) {
            $this->get($url)->assertOk()->assertSee('Awaiting receipt')->assertSee('GCash QR is not configured')
                ->assertDontSee('09171234567')->assertHeader('Referrer-Policy', 'no-referrer');
        }
        $this->get(route('public.order.payment', str_repeat('0', 64)))->assertNotFound();
        $this->get(route('public.order.saveLink', $order->private_token))->assertOk()->assertSee($url, false);
        $this->post(route('public.order.receipt', $order->private_token), [
            'reference_number' => 'HTTP-1', 'receipt' => UploadedFile::fake()->image('receipt.png'),
        ])->assertRedirect($url);
        $proof = $order->paymentProofs()->first();
        $this->get($url)->assertSee('Awaiting verification');
        $this->get(route('proofs.receipt', $proof))->assertRedirect(route('login'));
        $this->get('/storage/'.$proof->file_path)->assertForbidden();
        $this->actingAs($this->owner)->get(route('proofs.receipt', $proof))->assertOk()->assertHeader('Referrer-Policy', 'no-referrer');
        $this->get(route('orders.show', $order))->assertOk()->assertSee('View screenshot')->assertDontSee('Prices Adjustable');
        $this->post(route('proofs.accept', $proof), ['amount' => 1350, 'reference_number' => 'HTTP-1'])->assertSessionHasErrors('account_checked');
        $this->post(route('proofs.accept', $proof), ['amount' => 1350, 'reference_number' => 'HTTP-1', 'account_checked' => '1'])->assertSessionHasNoErrors()->assertRedirect(route('orders.show', $order));
        $this->get($url)->assertSee('Your deposit is verified');
        $this->patch('/orders/'.$order->id.'/details/'.$order->orderDetails->first()->id.'/price', ['unit_price' => 1])->assertNotFound();
    }

    public function test_catalog_and_settings_management_is_owner_only_and_photos_are_separate(): void
    {
        Storage::fake('public');
        $assistant = User::factory()->create(['role' => 'assistant']);
        $this->actingAs($assistant)->get(route('products.index'))->assertOk();
        $this->get(route('products.edit', $this->product))->assertForbidden();
        $this->post(route('options.store', $this->product), ['layers' => 3, 'price' => 2400, 'included_contents' => 'Cake and cupcakes'])->assertForbidden();
        $this->get(route('payment-settings.edit'))->assertForbidden();
        $this->post(route('payment-settings.update'), [])->assertForbidden();
        $this->actingAs($this->owner)->get(route('products.edit', $this->product))->assertOk();
        $this->get(route('add-ons.edit', $this->extra))->assertOk();
        $this->get(route('payment-settings.edit'))->assertOk();
        $this->patch(route('products.update', $this->product), [
            'product_name' => 'Updated catalog package', 'description' => 'A celebration cake', 'is_active' => 1,
            'photo' => UploadedFile::fake()->image('package.png'),
        ])->assertSessionHasNoErrors();
        Storage::disk('public')->assertExists($this->product->fresh()->photo_path);
        $this->assertDatabaseCount('order_images', 0);
        $this->post(route('payment-settings.update'), [
            'account_name' => 'TEST ACCOUNT ONLY', 'account_number' => 'TEST-ACCOUNT-NOT-REAL',
            'photo' => UploadedFile::fake()->image('test-qr.png'),
        ])->assertSessionHasNoErrors();
        $order = $this->orders->createPublicOrder($this->data());
        $this->get(route('public.order.payment', $order->private_token))->assertOk()->assertDontSee('TEST ACCOUNT ONLY')->assertDontSee('TEST-ACCOUNT-NOT-REAL')->assertSee('Save QR image');
        $this->get(route('public.order.qr', $order->private_token))->assertOk()->assertDownload();
        $this->get(route('orders.create'))->assertOk()->assertDontSee('name="unit_price"', false);
        $this->get(route('reports.index'))->assertOk()->assertSee('Verified payments less completed refunds');
    }

    public function test_http_price_changes_require_review_again_and_do_not_create_partial_records(): void
    {
        $data = $this->data() + ['first_name' => 'New', 'last_name' => 'Buyer', 'phone_number' => '09991234567', 'expected_total' => 1];
        $this->post(route('public.order.store'), $data)->assertSessionHasErrors('items');
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('customers', 1);
    }

    public function test_owner_can_manage_add_on_photos_applicability_and_options_without_repricing_orders(): void
    {
        Storage::fake('public');
        $order = $this->orders->createPublicOrder($this->data());
        $this->actingAs($this->owner)->patch(route('add-ons.update', $this->extra), [
            'name' => 'Updated extra cupcakes', 'description' => 'An extra box of twelve cupcakes',
            'price' => 400, 'products' => [$this->product->id], 'is_active' => 1,
            'photo' => UploadedFile::fake()->image('extra.png'),
        ])->assertSessionHasNoErrors();
        Storage::disk('public')->assertExists($this->extra->fresh()->photo_path);
        $this->assertSame('Updated extra cupcakes', $this->extra->fresh()->name);
        $option = $this->product->options()->first();
        $this->patch(route('options.update', [$this->product, $option]), [
            'layers' => 2, 'price' => 2400, 'included_contents' => 'Two-layer cake and twelve cupcakes', 'is_active' => 1,
        ])->assertSessionHasNoErrors();
        $this->assertSame(2700.0, $order->fresh()->total_amount);
        $other = Product::create(['product_name' => 'Other package', 'price' => 0, 'is_active' => true]);
        $this->patch(route('options.update', [$other, $option]), ['layers' => 1, 'price' => 10, 'included_contents' => 'Invalid'])->assertNotFound();
        $this->post(route('options.store', $this->product), ['layers' => 3, 'price' => 99.99, 'included_contents' => 'Cake'])->assertSessionHasErrors('price');
        $this->assertSame(2700.0, $order->fresh()->total_amount);
    }

    public function test_public_receipt_submission_is_rate_limited_and_inactive_staff_cannot_read_receipts(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.170']);
        $order = $this->orders->createPublicOrder($this->data());
        $url = route('public.order.receipt', $order->private_token);
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post($url, [])->assertSessionHasErrors('receipt');
        }
        $this->post($url, [])->assertStatus(429);
        $proof = $this->reviews->submit($order, UploadedFile::fake()->image('receipt.jpg'), 'INACTIVE-1');
        $this->owner->update(['is_active' => false]);
        $this->actingAs($this->owner)->get(route('proofs.receipt', $proof))->assertForbidden();
        $this->post(route('proofs.accept', $proof), ['amount' => 1350, 'reference_number' => 'INACTIVE-1', 'account_checked' => 1])->assertForbidden();
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_late_readiness_allows_full_refund_and_private_page_keeps_refund_history(): void
    {
        $this->travelTo(\Carbon\Carbon::parse('2026-09-24 06:00:00', 'UTC'));
        $data = $this->data();
        $data['pickup_date'] = '2026-09-24';
        $data['pickup_time'] = '15:00'; // 07:00 UTC at the bakery.
        $order = $this->orders->createPublicOrder($data);
        $proof = $this->reviews->submit($order, UploadedFile::fake()->image('receipt.jpg'), 'LATE-1');
        $this->reviews->accept($proof, 1350, 'LATE-1', $this->owner);
        $this->orders->updateStatus($order, 'preparing', $this->owner);
        $this->travelTo(\Carbon\Carbon::parse('2026-09-24 07:01:00', 'UTC'));
        $this->orders->updateStatus($order, 'ready_for_pickup', $this->owner);
        $refunds = app(RefundService::class);
        $refund = $refunds->markBakeryFailure($order, 'Order was not ready at the agreed pickup time.', $this->owner, true);
        $url = route('public.order.payment', $order->private_token);
        $this->get($url)->assertOk()->assertSee('Full refund · Pending')->assertSee('1,350.00');
        $this->travel(1)->days();
        $refunds->complete($refund, ['method' => 'gcash', 'reference_number' => 'RETURN-1', 'transfer_confirmed' => 1], $this->owner);
        $this->get($url)->assertOk()->assertSee('Full refund · Completed')->assertSee('RETURN-1');
        $this->assertSame('verified', $proof->fresh()->status);
        $this->assertDatabaseCount('payments', 1);
        $reports = app(FinancialReportService::class);
        $this->assertSame(-1350.0, $reports->getPaymentCollections(today(), today()));
        $this->assertSame(0.0, $reports->getPaymentCollections(today()->subDay(), today()));
        $this->assertSame(0.0, $reports->getCancellationIncome(today()->subDay(), today()));
    }

    public function test_historical_completed_pickup_remains_terminal_even_without_readiness_timestamp(): void
    {
        $order = $this->orders->createInternalOrder($this->data(), $this->owner);
        $this->orders->recordDownPayment($order, 1350, 'cash', null, $this->owner);
        // Historical paid and collected order; ledger is seeded directly.
        $order->payments()->create(['user_id' => $this->owner->id, 'amount' => 1350, 'payment_type' => 'final_payment', 'payment_method' => 'cash', 'payment_date' => now()]);
        // Simulate a pre-upgrade completed order with no readiness timestamp.
        $order->update(['fixed_catalog_pricing' => false, 'status' => 'completed', 'completed_at' => now(), 'ready_at' => null]);
        $this->assertNull($order->refund);
        $this->rejected(fn () => app(RefundService::class)->markBakeryFailure($order, 'A completed pickup is terminal.', $this->owner, true));
        $this->assertSame('completed', $order->fresh()->status);
        $this->assertNull($order->fresh()->refund);
        $this->assertSame(2700.0, $order->fresh()->amount_paid);
        $this->assertDatabaseCount('payments', 2);
    }
}
