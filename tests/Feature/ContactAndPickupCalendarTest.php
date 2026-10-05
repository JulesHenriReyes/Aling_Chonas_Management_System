<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\OrderService;
use App\Support\PhilippineContact;
use App\Support\PickupCalendar;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ContactAndPickupCalendarTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Customer $customer;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->create(['role' => 'owner', 'is_active' => true]);
        $this->customer = Customer::create(['first_name' => 'Saved', 'last_name' => 'Buyer', 'phone_number' => '09171234567']);
        $this->product = Product::create(['product_name' => 'Contact cake', 'price' => 2000, 'is_active' => true]);
        $this->product->options()->create(['layers' => 1, 'price' => 2000, 'included_contents' => 'Cake', 'is_active' => true]);
    }

    private function data(): array
    {
        return ['first_name' => 'New', 'last_name' => 'Buyer', 'phone_number' => '09181234567', 'customer_id' => $this->customer->id,
            'expected_total' => 2000, 'pickup_date' => PickupCalendar::today()->addDay()->toDateString(), 'pickup_time' => '15:00',
            'notes_text' => 'Keep these details', 'items' => [['product_id' => $this->product->id,
                'package_option_id' => $this->product->options()->first()->id, 'quantity' => 1, 'themes' => 'Blue flowers']]];
    }

    public function test_invalid_contacts_are_rejected_at_every_entry_without_changing_customer_or_order_records(): void
    {
        $this->actingAs($this->owner);
        $original = $this->customer->fresh()->getAttributes();
        foreach (['abcdefgh', '', '123', '81234567', '2345678', '+1 202 555 0100', '0917abc4567', '+63 2 123'] as $index => $phone) {
            $data = array_replace($this->data(), ['phone_number' => $phone]);
            $response = $this->post('/customers', $data)->assertSessionHasErrors('phone_number');
            if ($phone !== '') {
                $response->assertSessionHasInput('phone_number', $phone);
            } else {
                $this->assertNull(session()->getOldInput('phone_number'));
            }
            $this->patch('/customers/'.$this->customer->id, $data)->assertSessionHasErrors('phone_number');
            $this->postJson('/orders/create/customer', $data)->assertUnprocessable()->assertJsonValidationErrors('phone_number');
            $this->withServerVariables(['REMOTE_ADDR' => '127.0.1.'.($index + 1)])->post('/order', $data)->assertSessionHasErrors('phone_number');
            $this->assertDatabaseCount('customers', 1);
            $this->assertDatabaseCount('orders', 0);
            $this->assertSame($original, $this->customer->fresh()->getAttributes());
        }
    }

    public function test_mobile_and_complete_manila_and_provincial_landlines_normalize_and_match_across_entry_points(): void
    {
        $this->actingAs($this->owner);
        foreach ([['+63 917 123 4567', '09171234567'], ['(02) 8123-4567', '0281234567'],
            ['032 234 5678', '0322345678'], ['+63 32 234 5678', '0322345678']] as $index => [$phone, $normalized]) {
            $data = array_replace($this->data(), ['first_name' => 'Valid'.$index, 'phone_number' => $phone]);
            $this->post('/customers', $data)->assertSessionHasNoErrors();
            $saved = Customer::where('first_name', $data['first_name'])->sole();
            $this->assertSame($normalized, $saved->phone_number);
            $this->postJson('/orders/create/customer', $data)->assertOk()->assertJsonPath('id', $saved->id);
            $this->patch('/customers/'.$saved->id, $data)->assertSessionHasNoErrors();
            $this->withServerVariables(['REMOTE_ADDR' => '127.0.2.'.($index + 1)])->post('/order', $data)->assertSessionHasNoErrors();
            $this->assertSame($saved->id, Order::latest('id')->first()->customer_id);
        }
        $this->assertDatabaseCount('customers', 5);
        $this->assertDatabaseCount('orders', 4);
    }

    public function test_equivalent_historical_international_landline_matches_without_rewriting_or_guessing_an_old_contact(): void
    {
        $saved = Customer::create(['first_name' => 'Legacy', 'last_name' => 'Landline', 'phone_number' => '+63 (32) 234-5678']);
        $original = $saved->fresh()->getAttributes();
        $match = Customer::findOrCreateMatching(['first_name' => 'Legacy', 'last_name' => 'Landline', 'phone_number' => '0322345678']);
        $this->assertSame($saved->id, $match->id);
        $this->assertSame($original, $saved->fresh()->getAttributes());
        $this->assertNull(PhilippineContact::normalize('2345678'));
        $invalid = Customer::create(['first_name' => 'Legacy', 'last_name' => 'Invalid', 'phone_number' => 'letters-only']);
        $valid = Customer::findOrCreateMatching(['first_name' => 'Legacy', 'last_name' => 'Invalid', 'phone_number' => '09181234567']);
        $this->assertNotSame($invalid->id, $valid->id);
        $this->assertSame('letters-only', $invalid->fresh()->phone_number);
    }

    public function test_invalid_contact_keeps_draft_details_and_per_line_staged_image_associations(): void
    {
        Storage::fake('local');
        $data = $this->data();
        $data['items'][0]['images'] = [UploadedFile::fake()->image('flowers.png')];
        $this->post('/order/details', ['items' => $data['items']])->assertRedirect('/order/details');
        $draft = session('public_order_draft');
        $image = $draft['items'][0]['staged_images'][0]['staged_path'];
        $this->from('/order/details')->post('/order', array_replace($data, ['phone_number' => 'letters-only']))
            ->assertSessionHasErrors('phone_number')->assertSessionHasInput('notes_text', 'Keep these details');
        $this->assertSame($draft['items'], session('public_order_draft')['items']);
        Storage::disk('local')->assertExists($image);
        $this->get('/order/details')->assertOk()->assertSee('value="letters-only"', false)->assertSee('Blue flowers')->assertSee('area code');
        $this->assertDatabaseCount('orders', 0);
    }

    public static function boundaryDates(): array
    {
        return [
            ['2026-10-04 15:59:59', '2026-10-04'], ['2026-10-04 16:00:00', '2026-10-05'],
            ['2026-10-04 17:00:00', '2026-10-05'], ['2026-10-04 16:00:01', '2026-10-05'],
            ['2026-10-31 16:00:00', '2026-11-01'], ['2026-12-31 16:00:00', '2027-01-01'],
        ];
    }

    #[DataProvider('boundaryDates')]
    public function test_pickup_local_today_matches_dashboard_schedule_http_service_and_date_inputs(string $utc, string $today): void
    {
        $this->travelTo(Carbon::parse($utc, 'UTC'));
        $this->assertSame($today, PickupCalendar::todayString());
        $data = array_replace($this->data(), ['pickup_date' => $today]);
        $yesterday = PickupCalendar::today()->subDay()->toDateString();
        $this->actingAs($this->owner)->post('/orders', array_replace($data, ['pickup_date' => $yesterday]))->assertSessionHasErrors('pickup_date');
        $this->post('/order', array_replace($data, ['pickup_date' => $yesterday]))->assertSessionHasErrors('pickup_date');
        try {
            app(OrderService::class)->createInternalOrder(array_replace($data, ['pickup_date' => $yesterday]), $this->owner);
            $this->fail('Yesterday must be rejected in the service.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('pickup_date', $exception->errors());
        }
        $this->assertDatabaseCount('orders', 0);
        $order = app(OrderService::class)->createInternalOrder($data, $this->owner);
        $old = Order::create(['order_number' => 'OLD-PICKUP', 'customer_id' => $this->customer->id, 'user_id' => $this->owner->id,
            'pickup_date' => $yesterday, 'pickup_time' => '15:00', 'status' => 'pending']);
        $this->get('/dashboard')->assertViewHas('todayPickups', fn ($orders) => $orders->modelKeys() === [$order->id]);
        $this->get('/pickup-schedule')->assertViewHas('orders', fn ($groups) => $groups->flatten()->pluck('id')->all() === [$order->id]);
        $this->post('/orders/create/details', ['items' => $data['items']])->assertRedirect('/orders/create/details');
        $this->get('/orders/create/details')->assertSee('min="'.$today.'"', false);
        $this->post('/order/details', ['items' => $data['items']])->assertRedirect('/order/details');
        $this->get('/order/details')->assertSee('min="'.$today.'"', false);
        $this->assertSame($yesterday, $old->fresh()->pickup_date->toDateString());
    }
}
