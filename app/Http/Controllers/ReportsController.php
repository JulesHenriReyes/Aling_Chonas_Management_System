<?php

namespace App\Http\Controllers;

use App\Services\FinancialReportService;
use App\Services\ReportExportService;
use App\Services\ReportPdfService;
use App\Services\ReportPeriod;
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
        $exporter = app(ReportExportService::class);

        return response()->streamDownload(function () use ($report, $exporter) {
            echo $exporter->csv($report);
        }, $exporter->filename($period, 'csv'), [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function exportPdf(Request $request)
    {
        Gate::authorize('view-reports');
        $period = ReportPeriod::resolve($request->query());
        $report = $this->reports->report($period);
        $exporter = app(ReportExportService::class);
        $pdf = app(ReportPdfService::class)->render($report, $exporter->sections($report));

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$exporter->filename($period, 'pdf').'"',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
