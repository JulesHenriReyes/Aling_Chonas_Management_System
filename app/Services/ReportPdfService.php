<?php

namespace App\Services;

use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\File;

class ReportPdfService
{
    public function render(array $report, array $sections): string
    {
        $cache = storage_path('app/private/report-font-cache');
        File::ensureDirectoryExists($cache);
        $options = new Options;
        $options->setDefaultFont('DejaVu Sans');
        $options->setFontCache($cache);
        $options->setTempDir(sys_get_temp_dir());
        $options->setIsRemoteEnabled(false);
        $options->setIsPhpEnabled(false);
        $options->setIsJavascriptEnabled(false);

        $pdf = new Dompdf($options);
        $pdf->setPaper('A4', 'landscape');
        $pdf->loadHtml(view('admin.reports.pdf', $report + ['sections' => $sections])->render(), 'UTF-8');
        $pdf->render();
        $canvas = $pdf->getCanvas();
        $font = $pdf->getFontMetrics()->getFont('DejaVu Sans', 'normal');
        $canvas->page_text(48, 22, ReportExportService::BUSINESS_NAME, $font, 8, [0.38, 0.4, 0.43]);
        $canvas->page_text(480, 22, $report['period']->start->format('M j, Y').' to '.$report['period']->end->format('M j, Y').' | PHP', $font, 8, [0.38, 0.4, 0.43]);
        $canvas->page_text(48, $canvas->get_height() - 29, 'Aling Chona Cakes & Cupcakes | Financial report', $font, 8, [0.38, 0.4, 0.43]);
        $canvas->page_text($canvas->get_width() - 113, $canvas->get_height() - 29, 'Page {PAGE_NUM} of {PAGE_COUNT}', $font, 8, [0.38, 0.4, 0.43]);
        $canvas->add_info('Title', 'Aling Chona Financial Report '.$report['period']->start->toDateString().' to '.$report['period']->end->toDateString());
        $canvas->add_info('Author', ReportExportService::BUSINESS_NAME);

        return $pdf->output();
    }
}
