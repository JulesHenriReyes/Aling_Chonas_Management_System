<?php

namespace App\Http\Controllers;

use App\Services\{FinancialReportService, ReportPeriod};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ReportsController extends Controller
{
    public function __construct(private FinancialReportService $reports) {}
    public function index(Request $request)
    {
        Gate::authorize('view-reports');
        $period = ReportPeriod::resolve($request->query());
        return view('admin.reports.index', $this->reports->report($period));
    }
    public function records(Request $request)
    {
        Gate::authorize('view-reports');
        $data = $request->validate(['metric' => ['required', Rule::in(array_keys(FinancialReportService::LABELS))]]);
        $period = ReportPeriod::resolve($request->query());
        $query = $this->reports->events($period, $data['metric']);
        $total = (float) (clone $query)->sum('amount');
        $records = $query->orderByDesc('event_at')->orderByDesc('record_id')->paginate(25)->withQueryString();
        return view('admin.reports.records', ['period' => $period, 'records' => $records, 'total' => $total, 'metric' => $data['metric'], 'label' => FinancialReportService::LABELS[$data['metric']]]);
    }
    public function export(Request $request)
    {
        Gate::authorize('view-reports');
        $period = ReportPeriod::resolve($request->query());
        $report = $this->reports->report($period);
        return response()->streamDownload(function () use ($period, $report) {
            $file = fopen('php://output', 'w');
            $columns = ['section', 'period', 'label', 'sales', 'gross_collections', 'payment_collections', 'cancellation_income', 'expenses', 'operational_net_income', 'completed_order_count', 'package_quantity', 'package_amount', 'extras_amount', 'amount'];
            fputcsv($file, $columns, ',', '"', '');
            $row = function (array $values) use ($file, $columns) {
                $cells = array_map(function ($key) use ($values) {
                    $value = $values[$key] ?? '';
                    return is_string($value) && preg_match('/^[=+@\-\t\r]/', $value) ? "'".$value : $value;
                }, $columns);
                fputcsv($file, $cells, ',', '"', '');
            };
            $row(['section' => 'period', 'period' => $period->start->toDateString().' through '.$period->end->toDateString(), 'label' => $period->timezone.'; amounts in PHP']);
            $row(['section' => 'summary'] + $report['summary']);
            foreach ($report['trends'] as $trend) $row(['section' => 'trend'] + $trend);
            foreach ($report['expensesByCategory'] as $category) $row(['section' => 'expense_category', 'label' => $category->category, 'amount' => (float) $category->total_amount]);
            foreach ($report['methods'] as $method) $row(['section' => 'payment_method', 'label' => $method['method'], 'gross_collections' => $method['gross'], 'payment_collections' => $method['net']]);
            foreach ($report['packages'] as $package) $row(['section' => 'package', 'label' => $package->product_name_snapshot ?: 'Legacy package #'.$package->product_id, 'amount' => (float) $package->total_amount, 'package_quantity' => (int) $package->package_quantity, 'completed_order_count' => (int) $package->order_count, 'package_amount' => (float) $package->package_amount, 'extras_amount' => (float) $package->extras_amount]);
            $row(['section' => 'definition', 'label' => 'Operational result = completed sales + retained cancellation deposits - valid expenses. Not accounting profit; COGS and other accounting costs are unavailable.']);
            fclose($file);
        }, 'bakery-report-'.$period->start->format('Y-m-d').'-'.$period->end->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
