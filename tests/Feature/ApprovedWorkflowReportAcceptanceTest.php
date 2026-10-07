<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Product;
use App\Models\User;
use App\Services\ExpenseService;
use App\Services\FinancialReportService;
use App\Services\OrderService;
use App\Services\ReportPeriod;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ApprovedWorkflowReportAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_ordinary_workflows_reconcile_independent_centavo_oracle_across_all_outputs_and_event_periods(): void
    {
        config(['bakery.business_timezone' => 'Asia/Manila', 'bakery.pickup_timezone' => 'Asia/Manila', 'app.timezone' => 'UTC']);
        $owner = User::factory()->create(['role' => 'owner', 'is_active' => true]);
        $assistant = User::factory()->create(['role' => 'assistant', 'is_active' => true]);
        $buyer = Customer::create(['first_name' => 'Oracle', 'last_name' => 'Buyer', 'phone_number' => '0322345678']);
        $product = Product::create(['product_name' => 'Oracle cake', 'price' => 2000, 'is_active' => true]);
        $option = $product->options()->create(['layers' => 1, 'price' => 2000, 'included_contents' => 'Cake', 'is_active' => true]);
        $orders = app(OrderService::class);
        $this->actingAs($owner);
        $this->travelTo(Carbon::parse('2026-09-30 15:59:00', 'UTC')); // Sep 30, 23:59 in the bakery.
        $fixture = [];
        foreach (['pickup', 'customer-cancel'] as $key) {
            $fixture[$key] = $orders->createInternalOrder(['customer_id' => $buyer->id, 'pickup_date' => '2026-12-01',
                'pickup_time' => '15:00', 'expected_total' => 2000,
                'items' => [['product_id' => $product->id, 'package_option_id' => $option->id, 'quantity' => 1]]], $owner);
            $this->confirmPaymentFixture($fixture[$key]);
            $orders->recordDownPayment($fixture[$key], 1000, 'cash', null, $owner);
        }
        $this->travelTo(Carbon::parse('2026-09-30 16:01:00', 'UTC')); // Oct 1, 00:01 in the bakery.
        $orders->updateStatus($fixture['pickup'], 'preparing', $assistant);
        $orders->updateStatus($fixture['pickup'], 'ready_for_pickup', $assistant);
        $orders->completePickup($fixture['pickup'], 'cash', null, $owner, true);
        // Historical paid cancellation fixture; new paid cancellations are blocked.
        $fixture['customer-cancel']->forceFill(['status'=>'cancelled','cancellation_kind'=>'customer','cancelled_at'=>now()])->save();
        app(ExpenseService::class)->create(['submission_key' => (string) Str::uuid(), 'description' => 'Oracle flour',
            'category' => 'ingredients', 'amount' => 125.55, 'expense_date' => '2026-10-01'], $assistant);

        // Explicit expected cents derived from the transactions above, never from report queries.
        $this->assertPeriod('2026-08', [0, 0, 0, 0, 0, 0]);
        $this->assertPeriod('2026-09', [0, 200000, 200000, 0, 0, 0]);
        $this->assertPeriod('2026-10', [200000, 100000, 100000, 100000, 12555, 287445]);
        $this->travelTo(Carbon::parse('2026-10-31 16:01:00', 'UTC'));
        $this->assertPeriod('2026-11', [0, 0, 0, 0, 0, 0]);
        $this->assertPeriod('2026-10', [200000, 100000, 100000, 100000, 12555, 287445]);
        $this->travelBack();
    }

    private function assertPeriod(string $month, array $expectedCents): void
    {
        $period = ReportPeriod::resolve(['mode' => 'month', 'month' => $month]);
        $report = app(FinancialReportService::class)->report($period);
        $csv = $this->get(route('reports.export', $period->query()))->assertOk()->streamedContent();
        $rows = array_map('str_getcsv', explode("\n", trim($csv)));
        $header = array_shift($rows);
        $rows = collect($rows)->map(fn ($row) => array_combine($header, $row));
        $csvSummary = $rows->firstWhere('section', 'summary');
        $metrics = ['sales', 'gross_collections', 'payment_collections', 'cancellation_income', 'expenses', 'operational_net_income'];
        foreach ($metrics as $index => $metric) {
            $expected = $expectedCents[$index];
            $this->assertSame($expected, (int) round($report['summary'][$metric] * 100), "$month summary $metric");
            $this->assertSame($expected, (int) round(array_sum(array_column($report['trends'], $metric)) * 100), "$month trend $metric");
            if (isset(FinancialReportService::LABELS[$metric])) {
                $records = $this->get(route('reports.records', $period->query() + ['metric' => $metric]))->assertOk();
                $this->assertSame($expected, (int) round($records->viewData('total') * 100), "$month drilldown total $metric");
                $this->assertSame($expected, (int) round($records->viewData('records')->sum('amount') * 100), "$month record rows $metric");
            }
            $this->assertSame($expected, (int) round((float) $csvSummary[$metric] * 100), "$month CSV summary $metric");
            $this->assertSame($expected, (int) round($rows->where('section', 'trend')->sum($metric) * 100), "$month CSV trend $metric");
        }
        $this->assertSame((int) round($report['summary']['payment_collections'] * 100), (int) round($report['methods']->sum('net') * 100));
    }
}
