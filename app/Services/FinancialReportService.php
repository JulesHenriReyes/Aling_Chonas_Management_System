<?php

namespace App\Services;

use App\Models\{Expense, Refund, Supply};
use Carbon\{Carbon, CarbonImmutable};
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class FinancialReportService
{
    public const LABELS = ['sales' => 'Completed sales', 'gross_collections' => 'Gross verified collections', 'refunds_completed' => 'Completed refunds', 'cancellation_income' => 'Retained cancellation deposits', 'expenses' => 'Valid expenses'];

    private function lines(ReportPeriod $period)
    {
        $extras = DB::table('order_add_ons')->select('order_detail_id')->selectRaw('SUM(quantity * unit_price) as extras_amount')->groupBy('order_detail_id');
        return $period->apply(DB::table('order_details as od')->join('orders as o', 'o.id', '=', 'od.order_id')
            ->leftJoinSub($extras, 'extras', 'extras.order_detail_id', '=', 'od.id')->where('o.status', 'completed'), 'o.completed_at');
    }

    /** The sole record-level source for headline totals, trends, breakdowns and drill-downs. */
    public function events(ReportPeriod $period, ?string $kind = null)
    {
        $sales = $this->lines($period)->selectRaw("'sales' as kind, o.id as record_id, o.id as order_id, o.order_number as label, o.completed_at as event_at, SUM(od.quantity * od.unit_price + COALESCE(extras.extras_amount,0)) as amount, NULL as method, NULL as category")
            ->groupBy('o.id', 'o.order_number', 'o.completed_at');
        // Only verified receipts create payments; pending/rejected payment_proofs never enter this ledger.
        $payments = $period->apply(DB::table('payments as p')->join('orders as o', 'o.id', '=', 'p.order_id'), 'p.payment_date')
            ->selectRaw("'gross_collections' as kind, p.id as record_id, o.id as order_id, o.order_number as label, p.payment_date as event_at, p.amount, p.payment_method as method, NULL as category");
        $refunds = $period->apply(DB::table('refunds as r')->join('orders as o', 'o.id', '=', 'r.order_id')->where('r.status', 'completed'), 'r.completed_at')
            ->selectRaw("'refunds_completed' as kind, r.id as record_id, o.id as order_id, o.order_number as label, r.completed_at as event_at, r.amount, r.method, NULL as category");
        $retained = $period->apply(DB::table('payments as p')->join('orders as o', 'o.id', '=', 'p.order_id')->where('o.status', 'cancelled')
            ->where(fn ($q) => $q->whereNull('o.cancellation_kind')->orWhere('o.cancellation_kind', 'customer'))->where('p.payment_type', 'down_payment'), 'o.cancelled_at')
            ->selectRaw("'cancellation_income' as kind, p.id as record_id, o.id as order_id, o.order_number as label, o.cancelled_at as event_at, p.amount, p.payment_method as method, NULL as category");
        $expenses = $period->apply(DB::table('expenses as e')->whereNull('e.deleted_at'), 'e.expense_date', true)
            ->selectRaw("'expenses' as kind, e.id as record_id, NULL as order_id, e.description as label, e.expense_date as event_at, e.amount, NULL as method, e.category");
        $queries = compact('sales', 'payments', 'refunds', 'retained', 'expenses');
        $mapping = ['sales' => 'sales', 'gross_collections' => 'payments', 'refunds_completed' => 'refunds', 'cancellation_income' => 'retained', 'expenses' => 'expenses'];
        if ($kind) return DB::query()->fromSub($queries[$mapping[$kind]], 'events');
        return DB::query()->fromSub($sales->unionAll($payments)->unionAll($refunds)->unionAll($retained)->unionAll($expenses), 'events');
    }

    public function report(ReportPeriod $period): array
    {
        $keys = array_keys(self::LABELS);
        $summary = array_fill_keys($keys, 0);
        $summary['completed_order_count'] = 0;
        $monthly = $period->start->diffInDays($period->end) > 62;
        $trends = [];
        for ($date = $monthly ? $period->start->startOfMonth() : $period->start; $date->lte($period->end); $date = $monthly ? $date->addMonth() : $date->addDay()) {
            $key = $date->format($monthly ? 'Y-m' : 'Y-m-d');
            $trends[$key] = ['period' => $key, 'completed_order_count' => 0] + array_fill_keys($keys, 0);
        }
        $categories = array_fill_keys(['ingredients', 'packaging', 'equipment', 'miscellaneous'], 0);
        $methods = ['cash' => ['gross' => 0, 'refunds' => 0], 'gcash' => ['gross' => 0, 'refunds' => 0]];
        // Stream records instead of loading all purchases or payments into the browser or PHP memory.
        foreach ($this->events($period)->orderBy('event_at')->cursor() as $event) {
            $amount = (int) round((float) $event->amount * 100);
            $day = $event->kind === 'expenses' ? CarbonImmutable::parse($event->event_at, $period->timezone)
                : CarbonImmutable::parse($event->event_at, config('app.timezone'))->setTimezone($period->timezone);
            $bucket = $day->format($monthly ? 'Y-m' : 'Y-m-d');
            $summary[$event->kind] += $amount; $trends[$bucket][$event->kind] += $amount;
            if ($event->kind === 'sales') { $summary['completed_order_count']++; $trends[$bucket]['completed_order_count']++; }
            if ($event->kind === 'expenses') $categories[$event->category] = ($categories[$event->category] ?? 0) + $amount;
            if (in_array($event->kind, ['gross_collections', 'refunds_completed'])) {
                $method = $event->method ?: 'unspecified';
                $methods[$method] ??= ['gross' => 0, 'refunds' => 0];
                $methods[$method][$event->kind === 'gross_collections' ? 'gross' : 'refunds'] += $amount;
            }
        }
        $convert = function (array $row) use ($keys): array {
            foreach ($keys as $key) $row[$key] = round($row[$key] / 100, 2);
            $row['payment_collections'] = round($row['gross_collections'] - $row['refunds_completed'], 2);
            $row['operational_net_income'] = round($row['sales'] + $row['cancellation_income'] - $row['expenses'], 2);
            return $row;
        };
        $summary = $convert($summary) + ['period_start' => $period->start->toDateString(), 'period_end' => $period->end->toDateString(), 'refunds_pending' => (float) Refund::where('status', 'pending')->sum('amount')];
        return ['period' => $period, 'summary' => $summary, 'trends' => array_map($convert, array_values($trends)), 'grain' => $monthly ? 'Monthly' : 'Daily',
            'expensesByCategory' => collect($categories)->map(fn ($cents, $category) => (object) ['category' => $category, 'total_amount' => $cents / 100])->values(),
            'methods' => collect($methods)->map(fn ($values, $method) => ['method' => $method, 'gross' => $values['gross'] / 100, 'refunds' => $values['refunds'] / 100, 'net' => ($values['gross'] - $values['refunds']) / 100])->values(),
            'packages' => $this->packagePerformance($period), 'asOf' => CarbonImmutable::now($period->timezone),
            'lowStockCount' => Supply::active()->lowStock()->count()];
    }

    public function packagePerformance(ReportPeriod $period): Collection
    {
        return $this->lines($period)->select('od.product_id', 'od.product_name_snapshot')
            ->selectRaw('SUM(od.quantity) as package_quantity, COUNT(DISTINCT o.id) as order_count, SUM(od.quantity * od.unit_price) as package_amount, SUM(COALESCE(extras.extras_amount,0)) as extras_amount, SUM(od.quantity * od.unit_price + COALESCE(extras.extras_amount,0)) as total_amount')
            ->groupBy('od.product_id', 'od.product_name_snapshot')->orderByDesc('total_amount')->get();
    }

    private function total(ReportPeriod $period, string $kind): float { return round((float) $this->events($period, $kind)->sum('amount'), 2); }
    public function getSales(string|Carbon $startDate, string|Carbon $endDate): float { return $this->total(ReportPeriod::dates($startDate, $endDate), 'sales'); }
    public function getPaymentCollections(string|Carbon $startDate, string|Carbon $endDate): float
    {
        $period = ReportPeriod::dates($startDate, $endDate);
        return round($this->total($period, 'gross_collections') - $this->total($period, 'refunds_completed'), 2);
    }
    public function getCancellationIncome(string|Carbon $startDate, string|Carbon $endDate): float { return $this->total(ReportPeriod::dates($startDate, $endDate), 'cancellation_income'); }
    public function getExpenses(string|Carbon $startDate, string|Carbon $endDate): float { return $this->total(ReportPeriod::dates($startDate, $endDate), 'expenses'); }
    public function getExpensesByCategory(string|Carbon $startDate, string|Carbon $endDate): Collection
    {
        return $this->events(ReportPeriod::dates($startDate, $endDate), 'expenses')->select('category')->selectRaw('SUM(amount) as total_amount')->groupBy('category')->get();
    }
    public function getFinancialSummary(string|Carbon $startDate, string|Carbon $endDate): array { return $this->report(ReportPeriod::dates($startDate, $endDate))['summary']; }
    public function getLowStockSupplies(): Collection { return Supply::active()->lowStock()->orderBy('current_quantity')->get(); }
}
