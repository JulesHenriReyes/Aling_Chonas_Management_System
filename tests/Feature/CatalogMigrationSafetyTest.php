<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CatalogMigrationSafetyTest extends TestCase
{
    public function test_upgrade_and_rollback_preserve_historical_orders_payments_and_catalog(): void
    {
        config(['database.connections.migration_audit' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true]]);
        DB::setDefaultConnection('migration_audit');
        foreach (glob(database_path('migrations/*.php')) as $file) {
            if (!str_contains(basename($file), '2026_09_24_')) {
                (require $file)->up();
            }
        }
        $staff = DB::table('users')->insertGetId(['first_name' => 'Legacy', 'last_name' => 'Owner', 'email' => 'legacy@example.test', 'password' => 'existing-hash', 'role' => 'owner']);
        $buyer = DB::table('customers')->insertGetId(['first_name' => 'Legacy', 'last_name' => 'Buyer', 'phone_number' => '09171234567']);
        $product = DB::table('products')->insertGetId(['product_name' => 'Original cake', 'price' => 900, 'is_active' => true]);
        $order = DB::table('orders')->insertGetId(['order_number' => 'LEGACY-001', 'customer_id' => $buyer, 'user_id' => null, 'status' => 'cancelled', 'pickup_date' => '2026-09-20', 'pickup_time' => '15:00', 'cancelled_at' => '2026-09-19 12:00:00']);
        $detail = DB::table('order_details')->insertGetId(['order_id' => $order, 'product_id' => $product, 'quantity' => 2, 'unit_price' => 1234.56, 'layers' => 3, 'themes' => 'Historical design']);
        foreach (['LEGACY REF', 'legacyref'] as $reference) {
            DB::table('payments')->insert(['order_id' => $order, 'user_id' => $staff, 'amount' => 617.28, 'payment_type' => 'down_payment', 'payment_method' => 'gcash', 'reference_number' => $reference, 'payment_date' => '2026-09-19 11:00:00']);
        }
        $oldOrders = DB::table('orders')->get()->toArray();
        $oldPayments = DB::table('payments')->get()->toArray();
        $oldDetails = DB::table('order_details')->get()->toArray();
        $oldProducts = DB::table('products')->get()->toArray();
        $migration = require database_path('migrations/2026_09_24_000001_add_catalog_options_and_payment_review.php');
        $migration->up();
        $this->assertEquals($oldPayments, DB::table('payments')->get()->toArray());
        $this->assertEquals(1234.56, DB::table('order_details')->where('id', $detail)->value('unit_price'));
        $this->assertSame(3, DB::table('order_details')->where('id', $detail)->value('layers'));
        $this->assertSame('Original cake', DB::table('order_details')->where('id', $detail)->value('product_name_snapshot'));
        $this->assertNull(DB::table('order_details')->where('id', $detail)->value('included_contents_snapshot'));
        $this->assertSame(0, DB::table('package_options')->count());
        $this->assertSame(0, DB::table('refunds')->count());
        $this->assertSame(0, DB::table('payment_settings')->count());
        $this->assertSame('customer', DB::table('orders')->where('id', $order)->value('cancellation_kind'));
        $this->assertFalse((bool) DB::table('orders')->where('id', $order)->value('fixed_catalog_pricing'));
        $this->assertNull(DB::table('orders')->where('id', $order)->value('ready_at'));
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', DB::table('orders')->where('id', $order)->value('private_token'));
        $this->assertSame(1, DB::table('gcash_references')->where('reference_number', 'LEGACYREF')->count());
        $migration->down();
        $this->assertFalse(Schema::hasTable('payment_proofs'));
        $this->assertEquals($oldOrders, DB::table('orders')->get()->toArray());
        $this->assertEquals($oldPayments, DB::table('payments')->get()->toArray());
        $this->assertEquals($oldDetails, DB::table('order_details')->get()->toArray());
        $this->assertEquals($oldProducts, DB::table('products')->get()->toArray());
        DB::purge('migration_audit');
    }
}
