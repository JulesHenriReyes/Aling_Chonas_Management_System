<?php

namespace Tests\Feature;

use App\Models\AddOn;
use App\Models\Product;
use App\Models\User;
use App\Services\CatalogPricingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CatalogWorkspaceRefinementTest extends TestCase
{
    use RefreshDatabase;

    private function package(string $name): Product
    {
        return Product::create(['product_name' => $name, 'price' => 0, 'is_active' => true]);
    }

    public function test_global_scope_applies_to_current_and_future_packages_and_selected_scope_stops_it(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'owner', 'is_active' => true]));
        $existing = $this->package('Existing package');
        $data = ['name' => 'Candles', 'description' => 'Extra birthday candles', 'price' => 100, 'is_active' => 1, 'availability_scope' => 'all'];
        $this->post(route('add-ons.store'), $data)->assertSessionHasNoErrors();
        $extra = AddOn::firstOrFail();
        $future = $this->package('Future package');
        $this->assertTrue($extra->all_packages);
        $this->assertTrue($existing->addOns()->whereKey($extra->id)->exists());
        $this->assertTrue($future->addOns()->whereKey($extra->id)->exists());
        $option = $future->options()->create(['layers' => 1, 'price' => 800, 'included_contents' => '', 'is_active' => true]);
        $quote = app(CatalogPricingService::class)->quote([['product_id' => $future->id, 'package_option_id' => $option->id, 'quantity' => 1, 'add_ons' => [['add_on_id' => $extra->id, 'quantity' => 1]]]]);
        $this->assertEquals(900, $quote['total']);
        $data['availability_scope'] = 'selected';
        $data['products'] = [$existing->id];
        $this->patch(route('add-ons.update', $extra), $data)->assertSessionHasNoErrors();
        $this->assertFalse($extra->fresh()->all_packages);
        $this->assertFalse($future->addOns()->whereKey($extra->id)->exists());
        $this->assertFalse($this->package('Later package')->addOns()->whereKey($extra->id)->exists());
        try {
            app(CatalogPricingService::class)->quote([['product_id' => $future->id, 'package_option_id' => $option->id, 'quantity' => 1, 'add_ons' => [['add_on_id' => $extra->id, 'quantity' => 1]]]]);
            $this->fail('An extra must be rejected after its package association is removed.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('items.0.add_ons.0.add_on_id', $exception->errors());
        }
    }

    public function test_independent_price_save_preserves_unavailable_option_and_toggle_is_scoped_to_its_package(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'owner', 'is_active' => true]));
        $package = $this->package('Hidden layer');
        $option = $package->options()->create(['layers' => 1, 'price' => 800, 'included_contents' => '', 'is_active' => false]);
        $this->patchJson(route('options.update', [$package, $option]), ['layers' => 1, 'price' => 900])->assertOk()->assertJsonPath('message', 'Layer option and inclusions saved.');
        $this->assertFalse($option->fresh()->is_active);
        $this->assertEquals(900, $option->fresh()->price);
        $this->patchJson(route('options.toggle', [$package, $option]))->assertOk()->assertJsonPath('available', true);
        $this->patchJson(route('options.toggle', [$this->package('Other package'), $option]))->assertNotFound();
        $this->assertTrue($option->fresh()->is_active);
    }

    public function test_new_option_starts_available_and_assistants_cannot_change_its_availability(): void
    {
        $package = $this->package('New layer');
        $this->actingAs(User::factory()->create(['role' => 'owner', 'is_active' => true]));
        $response = $this->postJson(route('options.store', $package), ['layers' => 2, 'price' => 1200])->assertOk()->assertJsonStructure(['action', 'html']);
        $this->assertStringContainsString('Make layer unavailable', $response->json('html'));
        $this->assertStringContainsString('Add a layer option', $response->json('html'));
        $option = $package->options()->firstOrFail();
        $this->assertTrue($option->is_active);
        $this->actingAs(User::factory()->create(['role' => 'assistant', 'is_active' => true]));
        $this->patchJson(route('options.toggle', [$package, $option]))->assertForbidden();
        $this->assertTrue($option->fresh()->is_active);
    }
}
