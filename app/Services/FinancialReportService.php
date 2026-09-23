<?php

namespace App\Services;

use App\Models\Expense;
use App\Models\Payment;
use App\Models\Supply;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class FinancialReportService
{
    /**
     * Sales: The total value of orders whose status is completed, based on completed_at.
     */
    public function getSales(string|Carbon $startDate, string|Carbon $endDate): float
    {
        $start = Carbon::parse($startDate)->startOfDay();
        $end = Carbon::parse($endDate)->endOfDay();

        $total = DB::table('order_details as od')
            ->join('orders as o', 'od.order_id', '=', 'o.id')
            ->where('o.status', 'completed')
            ->whereBetween('o.completed_at', [$start, $end])
            ->selectRaw('COALESCE(SUM(od.quantity * od.unit_price), 0) as total_sales')
            ->value('total_sales');

        return round((float) $total, 2);
    }

    /**
     * Payment Collections: The actual amounts received through the payments table, based on payment_date.
     */
    public function getPaymentCollections(string|Carbon $startDate, string|Carbon $endDate): float
    {
        $start = Carbon::parse($startDate)->startOfDay();
        $end = Carbon::parse($endDate)->endOfDay();

        $total = Payment::whereBetween('payment_date', [$start, $end])
            ->sum('amount');

        return round((float) $total, 2);
    }

    /**
     * Cancellation Income: Non-refundable down payments retained from cancelled orders, based on cancelled_at.
     */
    public function getCancellationIncome(string|Carbon $startDate, string|Carbon $endDate): float
    {
        $start = Carbon::parse($startDate)->startOfDay();
        $end = Carbon::parse($endDate)->endOfDay();

        $total = DB::table('payments as p')
            ->join('orders as o', 'p.order_id', '=', 'o.id')
            ->where('o.status', 'cancelled')
            ->where('p.payment_type', 'down_payment')
            ->whereBetween('o.cancelled_at', [$start, $end])
            ->selectRaw('COALESCE(SUM(p.amount), 0) as total_cancellation')
            ->value('total_cancellation');

        return round((float) $total, 2);
    }

    /**
     * Expenses: Recorded business expenses, based on expense_date.
     */
    public function getExpenses(string|Carbon $startDate, string|Carbon $endDate): float
    {
        $start = Carbon::parse($startDate)->toDateString();
        $end = Carbon::parse($endDate)->toDateString();

        $total = Expense::whereDate('expense_date', '>=', $start)
            ->whereDate('expense_date', '<=', $end)
            ->sum('amount');

        return round((float) $total, 2);
    }

    /**
     * Expenses breakdown by category.
     */
    public function getExpensesByCategory(string|Carbon $startDate, string|Carbon $endDate): Collection
    {
        $start = Carbon::parse($startDate)->toDateString();
        $end = Carbon::parse($endDate)->toDateString();

        return Expense::whereDate('expense_date', '>=', $start)
            ->whereDate('expense_date', '<=', $end)
            ->select('category', DB::raw('SUM(amount) as total_amount'))
            ->groupBy('category')
            ->get();
    }

    /**
     * Operational Net Income:
     * Formula: Net Income = Sales + Cancellation Income - Expenses
     *
     * Note: Operational reporting metric defined for this academic project.
     * Not intended to function as a full accounting system.
     */
    public function getFinancialSummary(string|Carbon $startDate, string|Carbon $endDate): array
    {
        $sales = $this->getSales($startDate, $endDate);
        $collections = $this->getPaymentCollections($startDate, $endDate);
        $cancellationIncome = $this->getCancellationIncome($startDate, $endDate);
        $expenses = $this->getExpenses($startDate, $endDate);

        $operationalNetIncome = round(($sales + $cancellationIncome) - $expenses, 2);

        return [
            'period_start' => Carbon::parse($startDate)->toDateString(),
            'period_end' => Carbon::parse($endDate)->toDateString(),
            'sales' => $sales,
            'payment_collections' => $collections,
            'cancellation_income' => $cancellationIncome,
            'expenses' => $expenses,
            'operational_net_income' => $operationalNetIncome,
        ];
    }

    /**
     * Supplies at or below reorder level.
     */
    public function getLowStockSupplies(): Collection
    {
        return Supply::active()
            ->lowStock()
            ->orderBy('current_quantity', 'asc')
            ->get();
    }
}
