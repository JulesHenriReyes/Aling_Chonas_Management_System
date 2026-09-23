<?php

namespace App\Http\Controllers;

use App\Services\FinancialReportService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReportsController extends Controller
{
    public function __construct(
        protected FinancialReportService $financialReportService
    ) {}

    public function index(Request $request): View
    {
        $validated = $request->validate([
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
        ]);

        $startDate = $validated['start_date'] ?? now()->startOfMonth()->toDateString();
        $endDate = $validated['end_date'] ?? now()->toDateString();
        $summary = $this->financialReportService->getFinancialSummary($startDate, $endDate);
        $expensesByCategory = $this->financialReportService->getExpensesByCategory($startDate, $endDate);

        return view('admin.reports.index', compact('summary', 'expensesByCategory'));
    }
}
