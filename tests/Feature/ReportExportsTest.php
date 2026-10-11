<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\FinancialReportService;
use App\Services\ReportExportService;
use App\Services\ReportPdfService;
use App\Services\ReportPeriod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportExportsTest extends TestCase
{
    use RefreshDatabase;

    public function test_csv_is_readable_numeric_unicode_safe_and_resists_spreadsheet_formulas(): void
    {
        $report = app(FinancialReportService::class)->report(ReportPeriod::resolve(['mode' => 'month', 'month' => '2026-10']));
        $report['summary']['operational_net_income'] = -1234.5;
        $report['packages'] = collect([
            (object) ['product_id' => 1, 'product_name_snapshot' => '  =HYPERLINK("https://example.invalid")', 'package_quantity' => 1, 'order_count' => 1, 'package_amount' => 0, 'extras_amount' => 0, 'total_amount' => 0],
            (object) ['product_id' => 2, 'product_name_snapshot' => 'Niño cake, chocolate "deluxe"', 'package_quantity' => 1, 'order_count' => 1, 'package_amount' => 1234.5, 'extras_amount' => 20, 'total_amount' => 1254.5],
        ]);
        $csv = app(ReportExportService::class)->csv($report);
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $file = fopen('php://temp', 'w+');
        fwrite($file, substr($csv, 3));
        rewind($file);
        $rows = [];
        while (($row = fgetcsv($file)) !== false) {
            $this->assertCount(7, $row);
            $rows[] = $row;
        }
        fclose($file);
        $this->assertContains(['Operational result', '-1234.50', '', '', '', '', ''], $rows);
        $this->assertContains(["'  =HYPERLINK(\"https://example.invalid\")", '1', '1', '0.00', '0.00', '0.00', ''], $rows);
        $this->assertContains(['Niño cake, chocolate "deluxe"', '1', '1', '1234.50', '20.00', '1254.50', ''], $rows);
        $this->assertStringNotContainsString('gross_collections', $csv);
        $this->assertContains(['Business timezone', 'Asia/Manila', '', '', '', '', ''], $rows);
    }

    public function test_owner_can_download_both_formats_for_the_same_period_and_pdf_is_real(): void
    {
        $owner = User::factory()->create(['role' => 'owner', 'is_active' => true]);
        $this->actingAs($owner);
        $query = ['mode' => 'custom', 'start_date' => '2026-10-02', 'end_date' => '2026-10-03'];
        $this->get(route('reports.index', $query))->assertOk()->assertSee('Export CSV')->assertSee('Export PDF')
            ->assertSee(route('reports.export.pdf', $query));
        $csv = $this->get(route('reports.export', $query))->assertOk()->assertDownload('aling-chona-report-2026-10-02-2026-10-03.csv');
        $csv->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('Oct 2, 2026 to Oct 3, 2026', $csv->streamedContent());
        $pdf = $this->get(route('reports.export.pdf', $query))->assertOk()->assertDownload('aling-chona-report-2026-10-02-2026-10-03.pdf');
        $pdf->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $pdf->getContent());
        $this->assertStringContainsString('%%EOF', $pdf->getContent());
        $this->assertStringContainsString('no-store', $pdf->headers->get('Cache-Control'));
    }

    public function test_exports_keep_owner_only_access_and_validate_dates(): void
    {
        foreach (['reports.export', 'reports.export.pdf'] as $route) {
            $this->get(route($route))->assertRedirect(route('login'));
            $this->actingAs(User::factory()->create(['role' => 'assistant', 'is_active' => true]))
                ->get(route($route))->assertForbidden();
            $this->actingAs(User::factory()->create(['role' => 'owner', 'is_active' => true]))
                ->getJson(route($route, ['mode' => 'custom', 'start_date' => '2026-10-03', 'end_date' => '2026-10-02']))
                ->assertUnprocessable()->assertJsonValidationErrors('end_date');
            auth()->forgetGuards();
        }
    }

    public function test_pdf_and_csv_share_all_summary_trends_and_breakdown_values(): void
    {
        $period = ReportPeriod::resolve(['mode' => 'month_range', 'start_month' => '2026-09', 'end_month' => '2026-11']);
        $report = app(FinancialReportService::class)->report($period);
        $exporter = app(ReportExportService::class);
        $sections = $exporter->sections($report);
        $html = view('admin.reports.pdf', $report + ['sections' => $sections])->render();
        $this->assertStringContainsString('Monthly financial trends', $html);
        $this->assertStringContainsString('No completed orders in this period.', $html);
        $this->assertStringContainsString('2026-09', $html);
        $this->assertStringContainsString('2026-11', $exporter->csv($report));
        $this->assertCount(3, $sections[4]['rows']);
        $this->assertStringStartsWith('%PDF-', app(ReportPdfService::class)->render($report, $sections));
    }
}
