<?php

namespace App\Services;

class ReportExportService
{
    public const BUSINESS_NAME = 'Aling Chona Cakes & Cupcakes';

    public const DEFINITIONS = [
        'Completed sales use order completion dates and saved order prices, including paid extras.',
        'Verified collections use payment dates. Unverified and rejected receipts are excluded.',
        'Retained cancellation deposits use customer cancellation dates and are separate from completed sales.',
        'Valid expenses use expense dates and exclude voided entries.',
        'Operational result = completed sales + retained cancellation deposits - valid expenses. Collections are not added again.',
        'Operational result is not accounting profit. Cost of goods sold and other accounting costs are unavailable.',
        'Package order counts are per package and may overlap when one order contains several packages.',
    ];

    /** Both exports present the same reconciled report without recalculating money. */
    public function sections(array $report): array
    {
        $summary = $report['summary'];

        return [
            [
                'title' => 'Financial summary',
                'headers' => ['Metric', 'Amount (PHP)'],
                'formats' => ['text', 'money'],
                'rows' => [
                    ['Completed sales', $summary['sales']],
                    ['Verified collections', $summary['gross_collections']],
                    ['Retained cancellation deposits', $summary['cancellation_income']],
                    ['Valid expenses', $summary['expenses']],
                    ['Operational result', $summary['operational_net_income']],
                ],
            ],
            [
                'title' => 'Collections by payment method',
                'headers' => ['Payment method', 'Verified collections (PHP)'],
                'formats' => ['text', 'money'],
                'rows' => $report['methods']->map(fn ($method) => [$method['method'] === 'gcash' ? 'GCash' : ucfirst($method['method']), $method['gross']])->all(),
                'total' => ['Total verified collections', $summary['gross_collections']],
            ],
            [
                'title' => 'Expenses by category',
                'headers' => ['Category', 'Amount (PHP)'],
                'formats' => ['text', 'money'],
                'rows' => $report['expensesByCategory']->map(fn ($category) => [
                    $category->category === 'ingredients' ? 'Groceries' : ucfirst($category->category), $category->total_amount,
                ])->all(),
                'total' => ['Total valid expenses', $summary['expenses']],
            ],
            [
                'title' => 'Package performance',
                'headers' => ['Package', 'Packages sold', 'Orders', 'Package sales (PHP)', 'Paid extras (PHP)', 'Total sales (PHP)'],
                'formats' => ['text', 'integer', 'integer', 'money', 'money', 'money'],
                'rows' => $report['packages']->map(fn ($package) => [
                    $package->product_name_snapshot ?: 'Legacy package #'.$package->product_id,
                    $package->package_quantity, $package->order_count, $package->package_amount,
                    $package->extras_amount, $package->total_amount,
                ])->all(),
                'total' => ['Total', $report['packages']->sum('package_quantity'), $summary['completed_order_count'],
                    $report['packages']->sum('package_amount'), $report['packages']->sum('extras_amount'), $summary['sales']],
                'empty' => 'No completed orders in this period.',
            ],
            [
                'title' => $report['grain'].' financial trends',
                'headers' => ['Period', 'Completed sales (PHP)', 'Verified collections (PHP)', 'Retained deposits (PHP)', 'Valid expenses (PHP)', 'Operational result (PHP)', 'Completed orders'],
                'formats' => ['text', 'money', 'money', 'money', 'money', 'money', 'integer'],
                'rows' => array_map(fn ($row) => [
                    $row['period'], $row['sales'], $row['gross_collections'], $row['cancellation_income'],
                    $row['expenses'], $row['operational_net_income'], $row['completed_order_count'],
                ], $report['trends']),
                'total' => ['Total', $summary['sales'], $summary['gross_collections'], $summary['cancellation_income'],
                    $summary['expenses'], $summary['operational_net_income'], $summary['completed_order_count']],
            ],
        ];
    }

    public function filename(ReportPeriod $period, string $extension): string
    {
        return 'aling-chona-report-'.$period->start->format('Y-m-d').'-'.$period->end->format('Y-m-d').'.'.$extension;
    }

    public function csv(array $report): string
    {
        $file = fopen('php://temp', 'w+');
        // UTF-8 BOM keeps names and symbols readable when opened directly in Excel.
        fwrite($file, "\xEF\xBB\xBF");
        $write = function (array $cells) use ($file): void {
            fputcsv($file, array_pad($cells, 7, ''), ',', '"', '', "\r\n");
        };
        $period = $report['period'];
        $write([self::BUSINESS_NAME]);
        $write(['Financial report']);
        $write(['Report period', $period->start->format('M j, Y').' to '.$period->end->format('M j, Y')]);
        $write(['Business timezone', $period->timezone]);
        $write(['Currency', 'PHP']);
        $write(['Generated', $report['asOf']->format('Y-m-d H:i:s P')]);
        $write(['Completed orders', (string) $report['summary']['completed_order_count']]);
        foreach ($this->sections($report) as $section) {
            $write([]);
            $write([$section['title']]);
            $write($section['headers']);
            foreach ($section['rows'] as $row) {
                $write(array_map(fn ($value, $format) => $format === 'money'
                    ? number_format((float) $value, 2, '.', '')
                    : ($format === 'integer' ? (string) (int) $value : $this->safeText((string) $value)),
                    $row, $section['formats']));
            }
            if ($section['rows'] === []) {
                $write([$section['empty'] ?? 'No records in this period.']);
            }
            if (isset($section['total'])) {
                $write(array_map(fn ($value, $format) => $format === 'money'
                    ? number_format((float) $value, 2, '.', '')
                    : ($format === 'integer' ? (string) (int) $value : $this->safeText((string) $value)),
                    $section['total'], $section['formats']));
            }
        }
        $write([]);
        $write(['Report notes']);
        foreach (self::DEFINITIONS as $note) {
            $write([$note]);
        }
        rewind($file);
        $contents = stream_get_contents($file);
        fclose($file);

        return $contents;
    }

    private function safeText(string $value): string
    {
        return preg_match('/^[\x00-\x20]*[=+@-]/u', $value) ? "'".$value : $value;
    }
}
