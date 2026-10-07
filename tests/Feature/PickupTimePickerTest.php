<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Order;
use App\Models\User;
use App\Services\OrderService;
use App\Support\PickupCalendar;
use App\Support\PickupHours;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PickupTimePickerTest extends TestCase
{
    use RefreshDatabase;
    use \Tests\Concerns\CreatesCatalogFixtures;

    private User $owner;
    private array $data;

    protected function setUp(): void
    {
        parent::setUp();
        config(['bakery.pickup_opens_at' => '08:00', 'bakery.pickup_closes_at' => '18:00']);
        $this->owner = User::factory()->create(['role' => 'owner', 'is_active' => true]);
        $customer = Customer::create(['first_name' => 'Clock', 'last_name' => 'Buyer', 'phone_number' => '09171234567']);
        $product = $this->catalogProduct(['product_name' => 'Pickup clock cake', 'price' => 1000, 'is_active' => true]);
        $this->data = ['customer_id' => $customer->id, 'first_name' => 'Clock', 'last_name' => 'Buyer', 'phone_number' => '09171234567',
            'pickup_date' => PickupCalendar::today()->addDay()->toDateString(), 'pickup_time' => '15:28', 'expected_total' => 1000,
            'notes_text' => 'Keep the selected pickup time.', 'items' => [['product_id' => $product->id, 'package_option_id' => $product->options()->first()->id, 'quantity' => 1]]];
    }

    public static function allowedTimes(): array
    {
        return array_map(fn ($time) => [$time], ['08:00', '11:59', '12:00', '17:59', '18:00']);
    }

    #[DataProvider('allowedTimes')]
    public function test_boundaries_and_noon_are_accepted_by_both_http_and_service_paths(string $time): void
    {
        $data = array_replace($this->data, ['pickup_time' => $time]);
        $this->actingAs($this->owner)->post('/orders', $data)->assertSessionHasNoErrors()->assertRedirect();
        $this->post('/order', $data)->assertSessionHasNoErrors()->assertRedirect();
        $service = app(OrderService::class);
        $service->createInternalOrder($data, $this->owner);
        $service->createPublicOrder($data);
        $this->assertDatabaseCount('orders', 4);
        $this->assertSame([$time], Order::all()->pluck('pickup_time')->map(fn ($saved) => substr($saved, 0, 5))->unique()->values()->all());
    }

    public static function rejectedTimes(): array
    {
        return [['07:59'], ['18:01'], ['00:00'], ['23:59'], ['8:00'], ['08:60'], ['12:00 PM'], ['08:00:00'], ['not a time'], [['08:00']], [null]];
    }

    #[DataProvider('rejectedTimes')]
    public function test_crafted_times_fail_without_posting_an_order_at_any_entry(mixed $time): void
    {
        $data = array_replace($this->data, ['pickup_time' => $time]);
        $this->actingAs($this->owner)->post('/orders', $data)->assertSessionHasErrors('pickup_time');
        $this->post('/order', $data)->assertSessionHasErrors('pickup_time');
        $service = app(OrderService::class);
        foreach ([fn () => $service->createInternalOrder($data, $this->owner), fn () => $service->createPublicOrder($data)] as $create) {
            try {
                $create();
                $this->fail('An invalid pickup time was accepted.');
            } catch (ValidationException $exception) {
                $this->assertSame([PickupHours::message()], $exception->errors()['pickup_time']);
            }
        }
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('order_details', 0);
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_options_and_rules_share_configured_hours_and_only_offer_closing_minutes(): void
    {
        $options = PickupHours::options();
        $this->assertSame(['08', '09', '10', '11'], array_column($options['AM'], 'value'));
        $this->assertSame(['12', '13', '14', '15', '16', '17', '18'], array_column($options['PM'], 'value'));
        $this->assertSame(['00'], $options['PM'][6]['minutes']);
        $this->assertCount(60, $options['AM'][0]['minutes']);
        $this->assertSame('12:00 PM', PickupHours::label('12:00'));
        config(['bakery.pickup_opens_at' => '09:15', 'bakery.pickup_closes_at' => '17:45']);
        $rules = \App\Http\Requests\CatalogOrderRules::order()['pickup_time'];
        foreach (['09:15' => true, '17:45' => true, '09:14' => false, '17:46' => false] as $time => $allowed) {
            $this->assertSame($allowed, Validator::make(['pickup_time' => $time], ['pickup_time' => $rules])->passes());
        }
        $this->assertSame('15', PickupHours::options()['AM'][0]['minutes'][0]);
        $this->assertSame('45', array_last(PickupHours::options()['PM'][5]['minutes']));
    }

    public function test_details_keep_time_and_contact_values_after_errors_and_back_navigation(): void
    {
        $this->actingAs($this->owner)->post('/orders/create/details', ['items' => $this->data['items']])->assertRedirect();
        $this->post('/orders/create/details/back', $this->data)->assertRedirect();
        $staff = $this->get('/orders/create/details')->assertOk()->assertSee('value="15:28"', false)->assertSee('data-pickup-time', false);
        $this->savePreview('staff', $staff);
        $this->post('/order/details', ['items' => $this->data['items']])->assertRedirect();
        $this->post('/order/details/back', $this->data)->assertRedirect();
        $public = $this->get('/order/details')->assertOk()->assertSee('value="15:28"', false)->assertSee('value="Clock"', false);
        $this->savePreview('public', $public);
        $bad = array_replace($this->data, ['pickup_time' => '07:59']);
        $this->from('/order/details')->post('/order', $bad)->assertSessionHasErrors(['pickup_time' => PickupHours::message()]);
        $error = $this->get('/order/details')->assertOk()->assertSee('value="07:59"', false)->assertSee('Keep the selected pickup time.')->assertSee('data-time-error', false)->assertSee(PickupHours::message());
        $this->savePreview('public-error', $error);
        $this->from('/order/details')->post('/order', array_replace($this->data, ['pickup_time' => ['08:00']]))->assertSessionHasErrors('pickup_time');
        $this->get('/order/details')->assertOk()->assertSee(PickupHours::message());
    }

    private function savePreview(string $name, \Illuminate\Testing\TestResponse $response): void
    {
        if ($directory = getenv('BAKERY_PICKUP_PREVIEW_DIR')) {
            if (! is_dir($directory)) mkdir($directory, 0777, true);
            // Synthetic fixtures only. Assets come from the running app; no live orders are posted.
            $html = str_replace('http://localhost', 'http://127.0.0.1:8000', $response->getContent());
            if ($assets = getenv('BAKERY_PICKUP_PREVIEW_ASSETS')) {
                $html = str_replace(['http://127.0.0.1:8000/js/', 'http://127.0.0.1:8000/css/'], [$assets.'/js/', $assets.'/css/'], $html);
            }
            file_put_contents($directory.'/'.$name.'.html', $html);
            if ($name === 'public') {
                file_put_contents($directory.'/native.html', preg_replace('/<script defer src="[^"]+\/pickup-time-picker\.js[^"]*"><\/script>/', '', $html));
            }
        }
    }
}
