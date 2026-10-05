<?php

require __DIR__.'/preview-bootstrap.php';
$fixture = json_decode(file_get_contents(__DIR__.'/evidence/preview-fixture.json'), true, 512, JSON_THROW_ON_ERROR);
previewApplication($fixture['database'], $fixture['storage']);
$owner = App\Models\User::where('email', 'owner@implementation.test')->sole();
$product = App\Models\Product::findOrFail($fixture['product']);
$orders = app(App\Services\OrderService::class);
Carbon\Carbon::setTestNow('2026-12-31 15:59:00');
$order = $orders->createInternalOrder(['customer_id' => $fixture['customer'], 'pickup_date' => '2027-01-10', 'pickup_time' => '15:00',
    'items' => [['product_id' => $product->id, 'package_option_id' => $product->options()->first()->id, 'quantity' => 1]]], $owner);
$orders->recordDownPayment($order, 1000, 'cash', null, $owner);
$refunds = app(App\Services\RefundService::class);
$refund = $refunds->markBakeryFailure($order, 'Synthetic future-period refund-only example', $owner, true);
Carbon\Carbon::setTestNow('2026-12-31 16:01:00'); // Jan 1 in Asia/Manila.
$refunds->complete($refund, ['method' => 'cash', 'reference_number' => 'FUTURE-FIXTURE-ONLY', 'transfer_confirmed' => 1], $owner);
Carbon\Carbon::setTestNow();
$report = app(App\Services\FinancialReportService::class)->report(App\Services\ReportPeriod::resolve(['mode' => 'month', 'month' => '2027-01']));
if ($report['summary']['gross_collections'] !== 0.0 || $report['summary']['refunds_completed'] !== 1000.0 || $report['summary']['payment_collections'] !== -1000.0) throw new RuntimeException('Refund-only fixture does not reconcile.');
file_put_contents(__DIR__.'/evidence/refund-only-preview.json', json_encode(['environment' => 'Synthetic isolated SQLite future-period fixture', 'period' => '2027-01', 'summary' => $report['summary']], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
echo "PASS: refund-only fixture: gross 0; completed refund 1000; net collections -1000.\n";
