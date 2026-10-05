<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Expense;
use App\Models\Order;
use App\Models\Product;
use App\Models\Supply;
use App\Models\User;
use App\Services\FinancialReportService;
use App\Services\OrderService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinancialReportingTest extends TestCase
{
    use RefreshDatabase;
    use \Tests\Concerns\CreatesCatalogFixtures;

    protected User $owner;
    protected Customer $customer;
    protected Product $cake;
    protected OrderService $orderService;
    protected FinancialReportService $reportService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->orderService = new OrderService();
        $this->reportService = new FinancialReportService();

        $this->owner = User::create([
            'first_name' => 'Chona',
            'last_name' => 'Hinay',
            'email' => 'owner@test.com',
            'password' => 'password123',
            'role' => 'owner',
        ]);

        $this->customer = Customer::create([
            'first_name' => 'Maria',
            'last_name' => 'Santos',
            'phone_number' => '09171234567',
        ]);

        $this->cake = $this->catalogProduct([
            'product_name' => 'Celebration Cake',
            'price' => 2000.00,
            'is_active' => true,
        ]);
    }

    public function test_financial_summary_and_operational_net_income(): void
    {
        $today = Carbon::parse('2026-09-22 10:00:00', 'Asia/Manila')->utc();
        Carbon::setTestNow($today);

        // 1. Order 1: ₱2000 cake, completed today
        $order1 = $this->orderService->createInternalOrder([
            'customer_id' => $this->customer->id,
            'pickup_date' => $today->toDateString(),
            'pickup_time' => '15:00',
            'items' => [
                ['product_id' => $this->cake->id, 'package_option_id' => $this->cake->options()->first()->id, 'quantity' => 1],
            ],
        ], $this->owner);

        // Down payment: ₱1000 cash
        $this->confirmPaymentFixture($order1);
        $this->orderService->recordDownPayment($order1, 1000.00, 'cash', null, $this->owner, $today);
        // Final payment: ₱1000 gcash
        $this->orderService->updateStatus($order1, 'preparing', $this->owner);
        $this->orderService->updateStatus($order1, 'ready_for_pickup', $this->owner);
        // Complete order after it is ready for pickup.
$this->orderService->recordFinalPayment($order1, 1000.00, 'gcash', 'REF-1000', $this->owner, $today, true);


        // 2. Order 2: ₱2000 cake, paid 50% deposit (₱1000) then cancelled today
        $order2 = $this->orderService->createInternalOrder([
            'customer_id' => $this->customer->id,
            'pickup_date' => $today->copy()->addDay()->toDateString(),
            'pickup_time' => '12:00',
            'items' => [
                ['product_id' => $this->cake->id, 'package_option_id' => $this->cake->options()->first()->id, 'quantity' => 1],
            ],
        ], $this->owner);

        $this->confirmPaymentFixture($order2);
        $this->orderService->recordDownPayment($order2, 1000.00, 'gcash', 'REF-500', $this->owner, $today);
        $this->orderService->cancelOrder($order2, $this->owner);

        // 3. Record business expense: ₱800 on ingredients
        Expense::create([
            'user_id' => $this->owner->id,
            'description' => 'Butter and Flour wholesale purchase',
            'category' => 'ingredients',
            'amount' => 800.00,
            'expense_date' => $today->toDateString(),
        ]);

        // Run reporting service for today
        $report = $this->reportService->getFinancialSummary($today->toDateString(), $today->toDateString());

        // Assertions based on approved definitions:
        // Sales = Value of completed orders (Order 1 = ₱2000)
        $this->assertEquals(2000.00, $report['sales']);

        // Payment Collections = Total cash/gcash received (₱1000 + ₱1000 + ₱1000 = ₱3000)
        $this->assertEquals(3000.00, $report['payment_collections']);

        // Cancellation Income = Non-refundable deposits from cancelled orders (Order 2 = ₱1000)
        $this->assertEquals(1000.00, $report['cancellation_income']);

        // Expenses = ₱800
        $this->assertEquals(800.00, $report['expenses']);

        // Operational Net Income = (Sales + Cancellation Income) - Expenses
        // (2000 + 1000) - 800 = ₱2200
        $this->assertEquals(2200.00, $report['operational_net_income']);
    }

    public function test_low_stock_supplies_query(): void
    {
        // Normal supply
        Supply::create([
            'supply_name' => 'Sugar',
            'category' => 'ingredients',
            'unit' => 'kg',
            'current_quantity' => 20.00,
            'reorder_level' => 5.00,
            'is_active' => true,
        ]);

        // Low stock supply
        $lowSupply = Supply::create([
            'supply_name' => 'Vanilla Extract',
            'category' => 'ingredients',
            'unit' => 'bottle',
            'current_quantity' => 2.00,
            'reorder_level' => 3.00,
            'is_active' => true,
        ]);

        $lowStockList = $this->reportService->getLowStockSupplies();

        $this->assertCount(1, $lowStockList);
        $this->assertEquals($lowSupply->id, $lowStockList->first()->id);
        $this->assertTrue($lowSupply->is_low_stock);
    }

    public function test_cancellation_income_only_includes_retained_down_payments(): void
    {
        $today = Carbon::parse('2026-09-22 10:00:00');
        Carbon::setTestNow($today);

        $order = $this->orderService->createInternalOrder([
            'customer_id' => $this->customer->id,
            'pickup_date' => $today->copy()->addDay()->toDateString(),
            'pickup_time' => '12:00',
            'items' => [['product_id' => $this->cake->id, 'package_option_id' => $this->cake->options()->first()->id, 'quantity' => 1]],
        ], $this->owner);

        $this->confirmPaymentFixture($order);
        $this->orderService->recordDownPayment($order, 1000.00, 'cash', null, $this->owner, $today);
        $this->orderService->cancelOrder($order, $this->owner);

        $this->assertSame(1000.00, $this->reportService->getCancellationIncome($today, $today));
    }

    public function test_legacy_cancellation_preserves_existing_deposit_reporting_and_full_ledger(): void
    {
        $today = Carbon::parse('2026-09-22 10:00:00');
        Carbon::setTestNow($today);

        $order = $this->orderService->createInternalOrder([
            'customer_id' => $this->customer->id,
            'pickup_date' => $today->copy()->addDay()->toDateString(),
            'pickup_time' => '12:00',
            'items' => [['product_id' => $this->cake->id, 'package_option_id' => $this->cake->options()->first()->id, 'quantity' => 1]],
        ], $this->owner);

        $this->confirmPaymentFixture($order);
        $this->orderService->recordDownPayment($order, 1000.00, 'cash', null, $this->owner, $today);
        // Explicit historical fixture; preserve existing classification, not a new retention decision.
        $order->payments()->create(['user_id' => $this->owner->id, 'amount' => 1000, 'payment_type' => 'final_payment', 'payment_method' => 'cash', 'payment_date' => $today]);
        $this->orderService->cancelOrder($order, $this->owner);

        $this->assertSame(1000.00, $this->reportService->getCancellationIncome($today, $today));
        $this->assertSame(2000.00, $order->fresh()->amount_paid);
        $this->assertDatabaseCount('payments', 2);
    }
}
