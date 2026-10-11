<?php

namespace Tests\Feature;

use App\Models\{Customer, Product, User};
use App\Services\{OrderService, OrderReviewService, PaymentReviewService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OrderQueueFiltersTest extends TestCase
{
    use RefreshDatabase;

    public function test_confirmed_tab_separates_verified_bookings_from_deposit_and_receipt_queues(): void
    {
        $owner = User::factory()->create(['role' => 'owner', 'is_active' => true]);
        $customer = Customer::create(['first_name' => 'Sofia', 'last_name' => 'Mendoza', 'phone_number' => '09171234567']);
        $product = Product::create(['product_name' => 'Celebration cake', 'price' => 1500, 'is_active' => true]);
        $option = $product->options()->create(['layers' => 1, 'price' => 1500, 'included_contents' => 'Cake', 'is_active' => true]);
        $data = ['customer_id' => $customer->id, 'pickup_date' => now()->addDays(3)->toDateString(), 'pickup_time' => '15:00',
            'items' => [['product_id' => $product->id, 'package_option_id' => $option->id, 'quantity' => 1]]];
        $service = app(OrderService::class);
        $pending = $service->createPublicOrder($data);
        $deposit = $service->createInternalOrder($data, $owner);
        $receipt = app(OrderReviewService::class)->confirm($service->createPublicOrder($data), $owner, true);
        Storage::fake('receipts');
        app(PaymentReviewService::class)->submit($receipt, UploadedFile::fake()->image('receipt.png'), '9182736450123');
        $booked = $service->createInternalOrder($data, $owner);
        $service->recordDownPayment($booked, 750, 'cash', null, $owner);
        $preparing = $service->createInternalOrder($data, $owner);
        $service->recordDownPayment($preparing, 750, 'cash', null, $owner);
        $service->updateStatus($preparing, 'preparing', $owner);

        $confirmed = $this->actingAs($owner)->get(route('orders.index', ['status' => 'confirmed']));
        $confirmed->assertOk()->assertViewHas('orders', fn ($orders) => $orders->pluck('id')->all() === [$booked->id]);
        $confirmed->assertSee('Deposit verified')->assertDontSee('Receipt awaiting verification')->assertDontSee('Confirmed — awaiting deposit');

        $this->get(route('orders.index', ['queue' => 'deposit']))->assertOk()
            ->assertViewHas('orders', fn ($orders) => $orders->pluck('id')->all() === [$deposit->id]);
        $this->get(route('orders.index', ['queue' => 'receipts']))->assertOk()
            ->assertViewHas('orders', fn ($orders) => $orders->pluck('id')->all() === [$receipt->id]);
        $this->get(route('orders.index', ['status' => 'pending']))->assertOk()
            ->assertViewHas('orders', fn ($orders) => $orders->pluck('id')->all() === [$pending->id]);
        $this->get(route('orders.index', ['status' => 'preparing']))->assertOk()
            ->assertViewHas('orders', fn ($orders) => $orders->pluck('id')->all() === [$preparing->id]);
    }
}

