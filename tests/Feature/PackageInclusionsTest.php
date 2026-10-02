<?php

namespace Tests\Feature;

use App\Models\AddOn;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PackageInclusionsTest extends TestCase
{
    use RefreshDatabase;

    private Product $product;
    private AddOn $item;
    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->create(['role' => 'owner', 'is_active' => true]);
        $this->product = Product::create(['product_name' => 'Celebration package', 'price' => 0, 'is_active' => true]);
        $this->product->options()->create(['layers' => 1, 'price' => 1000, 'included_contents' => 'Original finish and decorations', 'is_active' => true]);
        $this->item = AddOn::create(['name' => 'Cupcake', 'description' => 'One frosted cupcake', 'price' => 50, 'is_active' => true]);
        $this->item->products()->sync([$this->product->id]);
    }

    private function saveOption(array $overrides = [])
    {
        $option = $this->product->options()->first();
        return $this->actingAs($this->owner)->patch(route('options.update', [$this->product, $option]), array_replace([
            '_option' => (string) $option->id, 'layers' => 1, 'price' => 1000, 'is_active' => 1,
            'included_contents' => 'Buttercream finish',
            'included_items' => [['add_on_id' => $this->item->id, 'quantity' => 6]],
        ], $overrides));
    }

    private function selection(): array
    {
        return ['items' => [[
            'product_id' => $this->product->id, 'package_option_id' => $this->product->options()->first()->id,
            'quantity' => 3, 'unit_price' => 1,
            'included_items' => [['add_on_id' => $this->item->id, 'quantity' => 999, 'price' => -100]],
            'included_items_snapshot' => [['name' => 'Forged inclusion', 'quantity' => 999]],
            'included_contents_snapshot' => 'Forged text',
            'add_ons' => [['add_on_id' => $this->item->id, 'quantity' => 2, 'unit_price' => 0]],
        ]]];
    }

    public function test_owner_can_add_edit_and_remove_multiple_included_items_without_changing_paid_applicability(): void
    {
        $second = AddOn::create(['name' => 'Candle', 'description' => 'One candle', 'price' => 10, 'is_active' => false]);
        $this->saveOption(['included_items' => [
            ['add_on_id' => $this->item->id, 'quantity' => 6], ['add_on_id' => $second->id, 'quantity' => 1],
        ]])->assertSessionHasNoErrors()->assertRedirect();
        $option = $this->product->options()->first();
        $this->assertCount(2, $option->includedItems);
        $this->assertSame('Buttercream finish', $option->included_contents);
        $this->get(route('products.edit', $this->product))->assertOk()->assertSee('Included per package')->assertSee('Quantity per package');
        $this->saveOption(['included_items' => [['add_on_id' => $second->id, 'quantity' => 2]], 'included_contents' => ''])->assertSessionHasNoErrors();
        $this->assertSame(2, (int) $option->fresh()->includedItems->sole()->pivot->quantity);
        $this->assertSame([$this->product->id], $this->item->products()->pluck('products.id')->all());
        $this->saveOption(['included_items' => []])->assertSessionHasNoErrors();
        $this->assertCount(0, $option->fresh()->includedItems);
        $this->actingAs(User::factory()->create(['role' => 'assistant', 'is_active' => true]))
            ->patch(route('options.update', [$this->product, $option]), ['layers' => 1])->assertForbidden();
    }

    public function test_invalid_and_duplicate_rows_are_rejected_atomically_and_input_is_preserved(): void
    {
        $this->saveOption()->assertSessionHasNoErrors();
        foreach ([0, -1, 1.5, 'two', 1000] as $quantity) {
            $this->saveOption(['included_items' => [['add_on_id' => $this->item->id, 'quantity' => $quantity]]])
                ->assertSessionHasErrors('included_items.0.quantity');
        }
        $this->saveOption(['included_items' => [['add_on_id' => 999999, 'quantity' => 1]]])
            ->assertSessionHasErrors('included_items.0.add_on_id');
        $this->saveOption(['included_items' => [
            ['add_on_id' => $this->item->id, 'quantity' => 2], ['add_on_id' => (string) $this->item->id, 'quantity' => 3],
        ]])->assertSessionHasErrors('included_items.0.add_on_id')->assertSessionHasInput('included_items.1.quantity', 3);
        $this->assertSame(6, (int) $this->product->options()->first()->includedItems->sole()->pivot->quantity);
        $this->assertDatabaseCount('package_option_inclusions', 1);
    }

    public function test_new_layer_option_can_have_only_structured_items_and_keeps_other_options_unchanged(): void
    {
        $this->actingAs($this->owner)->post(route('options.store', $this->product), [
            'layers' => 2, 'price' => 1800, 'is_active' => 1,
            'included_items' => [['add_on_id' => $this->item->id, 'quantity' => 12]],
        ])->assertSessionHasNoErrors()->assertRedirect();
        $newOption = $this->product->options()->where('layers', 2)->sole();
        $this->assertSame('', $newOption->included_contents);
        $this->assertSame(12, (int) $newOption->includedItems->sole()->pivot->quantity);
        $this->assertSame('Original finish and decorations', $this->product->options()->where('layers', 1)->sole()->included_contents);
        $selection = $this->selection();
        $selection['items'][0]['package_option_id'] = $newOption->id;
        $this->postJson(route('public.order.quote'), $selection)->assertOk()->assertJsonPath('total', 5500)
            ->assertJsonPath('lines.0.included_items_snapshot.0.quantity', 12);
    }

    public function test_paid_extra_selection_is_independent_of_package_visibility_and_included_items(): void
    {
        $this->saveOption()->assertSessionHasNoErrors();
        $this->patch(route('add-ons.update', $this->item), [
            'name' => 'Cupcake', 'description' => 'One frosted cupcake', 'price' => 50, 'is_active' => 1,
        ])->assertSessionHasNoErrors();
        $this->assertCount(0, $this->item->fresh()->products);
        $this->assertTrue($this->product->fresh()->is_active);
        $this->get(route('public.order.index'))->assertOk()->assertViewHas('products', function ($products) {
            return $products->sole()->addOns->isEmpty() && $products->sole()->options->sole()->includedItems->count() === 1;
        });
        $this->postJson(route('public.order.quote'), $this->selection())->assertUnprocessable()->assertJsonValidationErrors('items.0.add_ons.0.add_on_id');
        $selection = $this->selection();
        $selection['items'][0]['add_ons'] = [];
        $this->postJson(route('public.order.quote'), $selection)->assertOk()->assertJsonPath('total', 3000)
            ->assertJsonPath('lines.0.included_items_snapshot.0.quantity', 6);
        $this->get(route('add-ons.edit', $this->item))->assertSee('Offer this paid extra with');
    }

    public function test_public_and_staff_orders_use_included_quantities_per_package_and_paid_extras_once_per_line(): void
    {
        $this->saveOption();
        $this->app['auth']->forgetGuards();
        $this->post(route('public.order.continue'), $this->selection())->assertRedirect(route('public.order.details'));
        $this->get(route('public.order.details'))->assertOk()->assertSee('Cupcake × 6 per package')
            ->assertSee('18 across 3 packages')->assertSee('3,100.00')->assertSee('whole order line')->assertDontSee('Forged');
        $this->post(route('public.order.store'), [
            'first_name' => 'Preview', 'last_name' => 'Buyer', 'phone_number' => '09170000000',
            'pickup_date' => now()->addDay()->toDateString(), 'pickup_time' => '14:00', 'expected_total' => 3100,
        ])->assertSessionHasNoErrors()->assertRedirect();
        $public = Order::sole();
        $this->get(route('public.order.payment', $public->private_token))->assertOk()->assertSee('18 across 3 packages')->assertSee('3 packages');

        $this->actingAs($this->owner);
        $secondLine = $this->selection()['items'][0];
        $secondLine['quantity'] = 2;
        $secondLine['add_ons'] = [];
        $staffSelection = $this->selection();
        $staffSelection['items'][] = $secondLine;
        $this->post(route('orders.continue'), $staffSelection)->assertRedirect(route('orders.details'));
        $this->get(route('orders.details'))->assertOk()->assertSee('18 across 3 packages')->assertSee('12 across 2 packages')->assertSee('5,100.00');
        $this->post(route('orders.store'), [
            'customer_id' => $public->customer_id, 'pickup_date' => now()->addDay()->toDateString(),
            'pickup_time' => '15:00', 'expected_total' => 5100,
        ])->assertSessionHasNoErrors()->assertRedirect();
        $staff = Order::whereNotNull('user_id')->sole();
        foreach ([$public, $staff] as $order) {
            $line = $order->orderDetails()->first();
            $this->assertSame(6, $line->included_items_snapshot[0]['quantity']);
            $this->assertSame('Cupcake', $line->included_items_snapshot[0]['name']);
            $this->assertSame('Buttercream finish', $line->included_contents_snapshot);
            $this->assertSame('1000.00', $line->unit_price);
            $this->assertSame('50.00', $line->addOns->sole()->unit_price);
            $this->assertSame(2, $line->addOns->sole()->quantity);
        }
        $this->assertSame(3100.0, $public->fresh()->total_amount);
        $this->assertSame(1550.0, $public->fresh()->required_down_payment);
        $this->assertSame(5100.0, $staff->fresh()->total_amount);
        $this->get(route('orders.show', $staff))->assertOk()->assertSee('12 across 2 packages');
    }

    public function test_catalog_changes_do_not_rewrite_saved_inclusions_or_prices(): void
    {
        $this->saveOption();
        $customer = Customer::create(['first_name' => 'Preview', 'last_name' => 'Buyer', 'phone_number' => '09170000000']);
        $order = app(OrderService::class)->createPublicOrder($this->selection() + [
            'customer_id' => $customer->id, 'pickup_date' => now()->addDay()->toDateString(), 'pickup_time' => '14:00',
        ]);
        $snapshot = $order->orderDetails->sole()->getAttributes();
        $this->item->update(['name' => 'Renamed item', 'description' => 'New description', 'price' => 100, 'is_active' => false]);
        $this->saveOption(['price' => 2000, 'included_contents' => 'New finish', 'included_items' => []]);
        $this->assertSame($snapshot, $order->fresh()->orderDetails->sole()->getAttributes());
        $this->get(route('public.order.payment', $order->private_token))->assertSee('Cupcake × 6 per package')->assertSee('Buttercream finish')->assertDontSee('New finish')->assertDontSee('Renamed item');
        $this->assertSame(3100.0, $order->fresh()->total_amount);
    }

    public function test_migration_does_not_parse_legacy_text_or_backfill_order_inclusions(): void
    {
        $customer = Customer::create(['first_name' => 'Legacy', 'last_name' => 'Buyer', 'phone_number' => '09170000000']);
        $order = app(OrderService::class)->createPublicOrder($this->selection() + [
            'customer_id' => $customer->id, 'pickup_date' => now()->addDay()->toDateString(), 'pickup_time' => '14:00',
        ]);
        $migration = require database_path('migrations/2026_09_27_000001_add_package_included_items.php');
        $migration->down();
        $before = DB::table('order_details')->get()->toArray();
        $options = DB::table('package_options')->get()->toArray();
        $migration->up();
        $this->assertEquals($options, DB::table('package_options')->get()->toArray());
        $this->assertNull($order->fresh()->orderDetails->sole()->included_items_snapshot);
        $this->assertDatabaseCount('package_option_inclusions', 0);
        $this->get(route('public.order.payment', $order->private_token))->assertSee('Original finish and decorations');
        $migration->down();
        $this->assertEquals($before, DB::table('order_details')->get()->toArray());
        $migration->up();
    }
}
