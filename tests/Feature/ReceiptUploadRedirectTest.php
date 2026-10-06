<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\OrderReviewService;
use App\Services\OrderService;
use App\Services\PaymentReviewService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ReceiptUploadRedirectTest extends TestCase
{
    use RefreshDatabase;

    private Order $order;
    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->create(['role' => 'owner', 'is_active' => true]);
        $customer = Customer::create(['first_name' => 'Upload', 'last_name' => 'Buyer', 'phone_number' => '09171234567']);
        $product = Product::create(['product_name' => 'Upload cake', 'price' => 2000, 'is_active' => true]);
        $option = $product->options()->create(['layers' => 1, 'price' => 2000, 'included_contents' => 'Cake', 'is_active' => true]);
        $this->order = app(OrderService::class)->createPublicOrder([
            'customer_id' => $customer->id, 'pickup_date' => now()->addDays(3)->toDateString(), 'pickup_time' => '15:00',
            'items' => [['product_id' => $product->id, 'package_option_id' => $option->id, 'quantity' => 1]],
        ]);
        app(OrderReviewService::class)->confirm($this->order, $this->owner, true);
        Storage::fake('receipts');
    }

    public function test_invalid_buyer_upload_returns_to_its_order_even_with_an_admin_referrer(): void
    {
        $page = route('public.order.payment', $this->order->private_token);
        foreach ([false, true] as $loggedIn) {
            if ($loggedIn) $this->actingAs($this->owner);
            $response = $this->from(route('dashboard'))->post(route('public.order.receipt', $this->order->private_token), [
                'reference_number' => 'KEEP-THIS-REFERENCE',
                'receipt' => UploadedFile::fake()->create('invalid.txt', 1, 'text/plain'),
            ]);
            $response->assertRedirect($page)->assertSessionHasErrors('receipt')
                ->assertSessionHasInput('reference_number', 'KEEP-THIS-REFERENCE');
        }
        $this->assertDatabaseCount('payment_proofs', 0);
        $this->assertDatabaseCount('payments', 0);
        $this->assertSame([], Storage::disk('receipts')->allFiles());
    }

    public function test_missing_upload_with_stale_previous_page_returns_to_its_private_order(): void
    {
        $this->get(route('login'))->assertOk();
        $this->post(route('public.order.receipt', $this->order->private_token), ['reference_number' => 'MISSING-FILE'])
            ->assertRedirect(route('public.order.payment', $this->order->private_token))
            ->assertSessionHasErrors('receipt')->assertSessionHasInput('reference_number', 'MISSING-FILE');
        $this->assertDatabaseCount('payment_proofs', 0);
    }

    public function test_service_rejection_keeps_the_buyer_on_the_order_and_json_clients_get_422(): void
    {
        app(PaymentReviewService::class)->submit($this->order, UploadedFile::fake()->image('first.png'), 'FIRST');
        $this->from(route('supplies.index'))->post(route('public.order.receipt', $this->order->private_token), [
            'reference_number' => 'SECOND', 'receipt' => UploadedFile::fake()->image('second.png'),
        ])->assertRedirect(route('public.order.payment', $this->order->private_token))->assertSessionHasErrors('receipt');
        $this->postJson(route('public.order.receipt', $this->order->private_token), ['reference_number' => 'JSON-FAIL'])
            ->assertUnprocessable()->assertJsonValidationErrors('receipt');
        $this->assertDatabaseCount('payment_proofs', 1);
        $this->assertDatabaseCount('payments', 0);
        $this->assertCount(1, Storage::disk('receipts')->allFiles());
    }
}
