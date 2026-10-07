<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Order;
use App\Models\PaymentProof;
use App\Models\Product;
use App\Models\User;
use App\Services\OrderReviewService;
use App\Services\OrderService;
use App\Services\PaymentReviewService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ReceiptOrderIdentityTest extends TestCase
{
    private User $owner;
    private Customer $customer;
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        // These reset/DDL cases need no surrounding transaction so SQLite can
        // actually disable foreign keys. TestCase already guards this database.
        $this->artisan('migrate:fresh', ['--force' => true])->assertSuccessful();
        $this->owner = User::factory()->create(['role' => 'owner', 'is_active' => true]);
        $this->customer = Customer::create(['first_name' => 'Repeat', 'last_name' => 'Buyer', 'phone_number' => '09171234567']);
        $this->product = Product::create(['product_name' => 'Identity cake', 'price' => 2000, 'is_active' => true]);
        $this->product->options()->create(['layers' => 1, 'price' => 2000, 'included_contents' => 'Cake', 'is_active' => true]);
        Storage::fake('receipts');
    }

    protected function tearDown(): void
    {
        RefreshDatabaseState::$migrated = false;
        RefreshDatabaseState::$inMemoryConnections = [];
        parent::tearDown();
    }

    private function order(): Order
    {
        return app(OrderService::class)->createPublicOrder([
            'customer_id' => $this->customer->id, 'pickup_date' => now()->addDays(3)->toDateString(),
            'pickup_time' => '15:00', 'expected_total' => 2000,
            'items' => [['product_id' => $this->product->id,
                'package_option_id' => $this->product->options()->first()->id, 'quantity' => 1]],
        ]);
    }

    private function receipt(Order $order, string $reference): PaymentProof
    {
        app(OrderReviewService::class)->confirm($order, $this->owner, true);

        return app(PaymentReviewService::class)->submit($order, UploadedFile::fake()->image('receipt.png'), $reference);
    }

    public function test_repeat_customer_does_not_inherit_receipts_and_new_uploads_work(): void
    {
        $first = $this->order();
        $proof = $this->receipt($first, 'FIRST-RECEIPT');
        $second = $this->order();
        $this->assertNotSame($first->receipt_key, $second->receipt_key);
        $this->assertFalse($second->hasReportedTransfer());
        $this->assertCount(0, $second->load('paymentProofs')->paymentProofs);
        $this->actingAs($this->owner)->get(route('orders.show', $second))->assertOk()
            ->assertDontSee(route('proofs.receipt', $proof), false);
        $newProof = $this->receipt($second, 'SECOND-RECEIPT');
        $this->assertSame($second->receipt_key, $newProof->order_receipt_key);
        $this->actingAs($this->owner)->get(route('proofs.receipt', $newProof))->assertOk();
        app(PaymentReviewService::class)->accept($newProof, 1000, 'SECOND-RECEIPT', $this->owner);
        $this->assertSame(1000.0, $second->fresh()->amount_paid);
    }

    public function test_recycled_id_cannot_expose_or_verify_an_old_receipt_even_in_the_same_second(): void
    {
        $this->freezeTime();
        $oldOrder = $this->order();
        $proof = $this->receipt($oldOrder, 'OLD-RECEIPT');
        // Simulate a manual partial reset with foreign-key checks bypassed.
        Schema::disableForeignKeyConstraints();
        try {
            DB::table('order_details')->truncate();
            DB::table('orders')->truncate();
        } finally {
            Schema::enableForeignKeyConstraints();
        }
        $replacement = $this->order();
        $this->assertSame($oldOrder->id, $replacement->id);
        $this->assertSame($oldOrder->created_at->toDateTimeString(), $replacement->created_at->toDateTimeString());
        $this->assertNotSame($oldOrder->receipt_key, $replacement->receipt_key);
        $this->assertFalse($replacement->hasReportedTransfer());
        $this->assertCount(0, $replacement->load('paymentProofs')->paymentProofs);
        $this->assertFalse(Order::whereKey($replacement->id)->whereHas('paymentProofs')->exists());
        $this->assertNull(PaymentProof::find($proof->id));
        $this->actingAs($this->owner)->get(route('orders.show', $replacement))->assertOk()
            ->assertDontSee(route('proofs.receipt', $proof), false);
        $this->get(route('public.order.payment', $replacement->private_token))->assertOk()
            ->assertDontSee('OLD-RECEIPT')->assertDontSee(route('proofs.receipt', $proof), false);
        $this->get(route('proofs.receipt', $proof))->assertNotFound();
        $this->post(route('proofs.accept', $proof), ['amount' => 1000, 'reference_number' => 'OLD-RECEIPT', 'account_checked' => 1])
            ->assertNotFound();
        $this->post(route('proofs.reject', $proof), ['reason' => 'Stale receipt'])->assertNotFound();
        $this->post('/payment-proofs/'.$proof->id.'/reconcile-refund', [])->assertNotFound();
        try {
            app(PaymentReviewService::class)->accept($proof, 1000, 'OLD-RECEIPT', $this->owner);
            $this->fail('Stale receipt must not be verified through the service.');
        } catch (ModelNotFoundException $exception) {
            $this->assertSame(PaymentProof::class, $exception->getModel());
        }
        $this->assertSame(0.0, $replacement->fresh()->amount_paid);
        $this->assertDatabaseCount('payment_proofs', 1);
        Storage::disk('receipts')->assertExists($proof->file_path);
        $newProof = $this->receipt($replacement, 'REPLACEMENT-RECEIPT');
        $this->assertCount(1, $replacement->fresh()->paymentProofs);
        $this->assertSame($newProof->id, $replacement->fresh()->paymentProofs->sole()->id);
    }

    public function test_migration_preserves_valid_history_and_leaves_stale_or_unknown_history_unbound(): void
    {
        $order = $this->order();
        $valid = $this->receipt($order, 'VALID-HISTORY');
        $stale = $order->paymentProofs()->create(['file_path' => 'stale.png', 'reference_number' => 'STALE-HISTORY']);
        DB::table('payment_proofs')->where('id', $stale->id)->update(['created_at' => $order->created_at->copy()->subDay()]);
        $unknown = $order->paymentProofs()->create(['file_path' => 'unknown.png', 'reference_number' => 'UNKNOWN-HISTORY']);
        DB::table('payment_proofs')->where('id', $unknown->id)->update(['created_at' => null]);
        Schema::disableForeignKeyConstraints();
        try {
            $orphan = DB::table('payment_proofs')->insertGetId(['order_id' => 99999, 'file_path' => 'orphan.png',
                'reference_number' => 'ORPHAN-HISTORY', 'status' => 'rejected', 'created_at' => now()]);
        } finally {
            Schema::enableForeignKeyConstraints();
        }
        $migration = require database_path('migrations/2026_10_05_000002_bind_receipts_to_order_identity.php');
        $migration->down();
        $before = DB::table('payment_proofs')->orderBy('id')->get()->map(fn ($row) => (array) $row)->all();
        $migration->up();
        $after = DB::table('payment_proofs')->orderBy('id')->get()->map(function ($row) {
            $row = (array) $row;
            unset($row['order_receipt_key']);

            return $row;
        })->all();
        $this->assertSame($before, $after);
        $this->assertSame($order->fresh()->receipt_key, PaymentProof::findOrFail($valid->id)->order_receipt_key);
        foreach ([$stale->id, $unknown->id, $orphan] as $id) {
            $this->assertNull(PaymentProof::find($id));
            $this->assertNull(DB::table('payment_proofs')->where('id', $id)->value('order_receipt_key'));
        }
        $this->assertCount(1, $order->fresh()->paymentProofs);
        $this->actingAs($this->owner)->get(route('proofs.receipt', $valid))->assertOk();
    }
}
