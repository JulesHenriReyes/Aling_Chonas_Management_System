<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\PaymentReviewService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TwoStepOrderingAndReceiptScreenTest extends TestCase
{
    use RefreshDatabase;
    use \Tests\Concerns\CreatesCatalogFixtures;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->product = $this->catalogProduct(['product_name' => 'Birthday package', 'price' => 1000, 'is_active' => true]);
        Storage::fake('local');
        Storage::fake('public');
        Storage::fake('receipts');
    }

    private function selection(): array
    {
        return ['items' => [[
            'product_id' => $this->product->id,
            'package_option_id' => $this->product->options()->first()->id,
            'quantity' => 2,
            'themes' => 'Blue flowers',
        ]]];
    }

    public function test_public_draft_keeps_package_and_uploaded_image_while_moving_back_and_forth(): void
    {
        $this->get(route('public.order.details'))->assertRedirect(route('public.order.index'));
        $selection = $this->selection();
        $selection['items'][0]['images'] = [UploadedFile::fake()->image('inspiration.png')];
        $this->post(route('public.order.continue'), $selection)->assertRedirect(route('public.order.details'));
        $this->get(route('public.order.details'))->assertOk()->assertSee('Blue flowers')->assertSee('2,000.00');
        $this->post(route('public.order.back'), [
            'first_name' => 'Mia', 'last_name' => 'Santos', 'phone_number' => '09171234567',
            'pickup_date' => now()->addDay()->toDateString(), 'pickup_time' => '14:00',
        ])->assertRedirect(route('public.order.index'));
        $this->get(route('public.order.index'))->assertOk()->assertSee('inspiration.png');
        $revised = $this->selection();
        $revised['items'][0]['draft_key'] = session('public_order_draft.items.0.draft_key');
        $this->post(route('public.order.continue'), $revised)->assertRedirect(route('public.order.details'));
        $this->get(route('public.order.details'))->assertSee('value="Mia"', false);
        $this->get(route('public.order.details'))->assertSee('value="09171234567"', false);
        $this->post(route('public.order.store'), [
            'first_name' => 'Mia', 'last_name' => 'Santos', 'phone_number' => '09171234567',
            'pickup_date' => now()->addDay()->toDateString(), 'pickup_time' => '14:00', 'expected_total' => 2000,
        ])->assertRedirect();
        $order = Order::sole();
        $this->assertSame(2000.0, $order->total_amount);
        $this->assertSame(1000.0, $order->required_down_payment);
        $this->assertSame('Blue flowers', $order->orderDetails()->first()->themes);
        $image = $order->images()->sole();
        Storage::disk('public')->assertExists($image->file_path);
        $this->assertSame('inspiration.png', $image->original_filename);
    }

    public function test_price_is_checked_again_when_submitting_draft_and_fields_survive_validation(): void
    {
        $this->post(route('public.order.continue'), $this->selection())->assertRedirect(route('public.order.details'));
        $this->from(route('public.order.details'))->post(route('public.order.store'), [
            'first_name' => 'Mia', 'last_name' => 'Santos', 'phone_number' => '123',
            'pickup_date' => now()->addDay()->toDateString(), 'pickup_time' => '14:00', 'expected_total' => 2000,
        ])->assertSessionHasErrors('phone_number');
        $this->get(route('public.order.details'))->assertSee('value="123"', false);
        $this->product->options()->first()->update(['price' => 1200]);
        $this->from(route('public.order.details'))->post(route('public.order.store'), [
            'first_name' => 'Mia', 'last_name' => 'Santos', 'phone_number' => '09171234567',
            'pickup_date' => now()->addDay()->toDateString(), 'pickup_time' => '14:00', 'expected_total' => 2000,
        ])->assertSessionHasErrors('items');
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_staff_uses_two_steps_and_can_create_and_select_customer_inline(): void
    {
        $owner = User::factory()->create(['role' => 'owner', 'is_active' => true]);
        $this->actingAs($owner);
        $this->post(route('orders.continue'), $this->selection())->assertRedirect(route('orders.details'));
        $this->get(route('orders.details'))->assertOk()->assertSee('Search customers by name or phone')->assertDontSee('Design reference photos');
        $customer = $this->postJson(route('orders.inlineCustomer'), [
            'first_name' => 'Maria', 'last_name' => 'De la Cruz', 'phone_number' => '09171234567',
        ])->assertOk()->json();
        $this->assertSame('Maria De la Cruz', Customer::findOrFail($customer['id'])->full_name);
        $this->post(route('orders.back'), ['customer_id' => $customer['id']])->assertRedirect(route('orders.create'));
        $this->post(route('orders.continue'), $this->selection())->assertRedirect(route('orders.details'));
        $this->post(route('orders.store'), [
            'customer_id' => $customer['id'], 'pickup_date' => now()->addDay()->toDateString(),
            'pickup_time' => '15:00', 'expected_total' => 2000,
        ])->assertRedirect();
        $this->assertSame($owner->id, Order::sole()->user_id);
        $this->assertSame($customer['id'], Order::sole()->customer_id);
    }

    public function test_rejection_state_and_authenticated_screenshot_remain_separate_from_payment(): void
    {
        $owner = User::factory()->create(['role' => 'owner', 'is_active' => true]);
        $customer = Customer::create(['first_name' => 'Mia', 'last_name' => 'Santos', 'phone_number' => '09171234567']);
        $order = app(\App\Services\OrderService::class)->createPublicOrder($this->selection() + [
            'customer_id' => $customer->id, 'pickup_date' => now()->addDay()->toDateString(), 'pickup_time' => '14:00',
        ]);
        $proof = app(PaymentReviewService::class)->submit($order, UploadedFile::fake()->image('receipt.png'), 'REF-22');
        $this->get(route('proofs.receipt', $proof))->assertRedirect(route('login'));
        $this->actingAs($owner)->get(route('orders.show', $order))->assertOk()->assertSee('View screenshot')->assertSee('value="REF-22"', false);
        $this->get(route('proofs.receipt', $proof))->assertOk();
        app(PaymentReviewService::class)->reject($proof, 'Reference not found', $owner);
        $this->get(route('public.order.payment', $order->private_token))->assertOk()->assertSee('Receipt rejected')->assertSee('Reference not found')->assertSee('Submit replacement receipt');
        $this->assertSame(0.0, $order->fresh()->amount_paid);
        $this->assertSame('unpaid', $order->fresh()->payment_status);
    }

    public function test_buyer_can_replace_a_rejected_receipt_and_prior_history_remains_visible(): void
    {
        $owner = User::factory()->create(['role' => 'owner', 'is_active' => true]);
        $customer = Customer::create(['first_name' => 'Mia', 'last_name' => 'Santos', 'phone_number' => '09171234567']);
        $order = app(\App\Services\OrderService::class)->createPublicOrder($this->selection() + [
            'customer_id' => $customer->id, 'pickup_date' => now()->addDay()->toDateString(), 'pickup_time' => '14:00',
        ]);
        $page = route('public.order.payment', $order->private_token);
        $this->from($page)->post(route('public.order.receipt', $order->private_token), [
            'reference_number' => 'REF-OLD', 'receipt' => UploadedFile::fake()->create('not-an-image.txt', 1, 'text/plain'),
        ])->assertSessionHasErrors('receipt');
        $this->get($page)->assertSee('value="REF-OLD"', false);

        $this->post(route('public.order.receipt', $order->private_token), [
            'reference_number' => 'REF-OLD', 'receipt' => UploadedFile::fake()->image('first.png'),
        ])->assertRedirect($page);
        $first = $order->paymentProofs()->sole();
        app(PaymentReviewService::class)->reject($first, 'Screenshot does not show a completed transfer.', $owner);
        $this->get($page)->assertSee('Receipt rejected')->assertSee('Screenshot does not show a completed transfer.')
            ->assertSee('Submit replacement receipt')->assertSee('₱0.00');

        $this->post(route('public.order.receipt', $order->private_token), [
            'reference_number' => 'REF-NEW', 'receipt' => UploadedFile::fake()->image('replacement.png'),
        ])->assertRedirect($page);
        $this->get($page)->assertSee('Awaiting verification')->assertSee('Earlier receipt history')
            ->assertSee('REF-OLD')->assertSee('Screenshot does not show a completed transfer.');
        $this->assertDatabaseCount('payment_proofs', 2);
        $this->assertDatabaseCount('payments', 0);
        $this->assertSame('unpaid', $order->fresh()->payment_status);
    }

    public function test_buyer_can_remove_saved_reference_without_leaving_an_orphaned_upload(): void
    {
        $selection = $this->selection();
        $selection['items'][0]['images'] = [UploadedFile::fake()->image('draft.png')];
        $this->post(route('public.order.continue'), $selection)->assertRedirect(route('public.order.details'));
        $draft = session('public_order_draft');
        $image = $draft['items'][0]['staged_images'][0];
        Storage::disk('local')->assertExists($image['staged_path']);

        $revised = $this->selection();
        $revised['items'][0]['draft_key'] = $draft['items'][0]['draft_key'];
        $revised['items'][0]['remove_staged_images'] = [$image['staged_path']];
        $this->post(route('public.order.continue'), $revised)->assertRedirect(route('public.order.details'));
        Storage::disk('local')->assertMissing($image['staged_path']);
        $this->assertSame([], session('public_order_draft.items.0.staged_images'));
    }
}
