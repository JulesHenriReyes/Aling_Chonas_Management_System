<?php

namespace Tests\Feature;

use App\Models\{Customer, Order, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB, Route, Schema};
use Tests\TestCase;

class RefundFeatureRemovalTest extends TestCase
{
    use RefreshDatabase;

    private function order(User $owner): Order
    {
        $buyer = Customer::create(['first_name' => 'Test', 'last_name' => 'Buyer', 'phone_number' => '09171234567']);

        $order = Order::create(['order_number' => 'NO-REFUNDS', 'customer_id' => $buyer->id, 'user_id' => null,
            'status' => 'pending', 'pickup_date' => now()->addDays(3)->toDateString(), 'pickup_time' => '15:00']);
        $order->forceFill(['private_token' => str_repeat('a', 64)])->save();

        return $order;
    }

    public function test_removed_endpoints_and_screens_work_without_a_refunds_table(): void
    {
        $owner = User::factory()->create(['role' => 'owner', 'is_active' => true]);
        $order = $this->order($owner);
        $this->assertFalse(Schema::hasTable('refunds'));
        foreach (['orders.bakeryFailure', 'refunds.complete', 'proofs.reconcileRefund'] as $name) {
            $this->assertFalse(Route::has($name));
        }
        $this->actingAs($owner);
        foreach (['/orders/'.$order->id.'/bakery-failure', '/refunds/1/complete', '/payment-proofs/1/reconcile-refund'] as $path) {
            $this->post($path, [])->assertNotFound();
        }
        $this->get(route('orders.show', $order))->assertOk()->assertDontSee('Bakery cannot fulfil this order');
        $this->get(route('public.order.payment', $order->private_token))->assertOk()->assertDontSee('Full refund');
        $this->get(route('reports.index'))->assertOk()->assertDontSee('Completed refunds')->assertDontSee('Outstanding refunds');
        $this->get(route('reports.export'))->assertOk();
        $this->assertSame('pending', $order->fresh()->status);
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_migration_preserves_existing_records_instead_of_silently_deleting_them(): void
    {
        $owner = User::factory()->create(['role' => 'owner', 'is_active' => true]);
        $order = $this->order($owner);
        $migration = require database_path('migrations/2026_10_07_000001_remove_refunds_feature.php');
        $migration->down();
        DB::table('refunds')->insert(['order_id' => $order->id, 'requested_by' => $owner->id,
            'amount' => 1000, 'reason' => 'Historical fixture', 'status' => 'pending']);
        try {
            $migration->up();
            $this->fail('A populated refunds table must not be deleted.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('No records have been deleted', $exception->getMessage());
            $this->assertDatabaseCount('refunds', 1);
            $this->assertEquals(1000, DB::table('refunds')->value('amount'));
        } finally {
            // Remove only this isolated SQLite test fixture, then restore the current test schema.
            DB::table('refunds')->delete();
            $migration->up();
        }
        $this->assertFalse(Schema::hasTable('refunds'));
        $this->assertSame('pending', $order->fresh()->status);
    }
}
