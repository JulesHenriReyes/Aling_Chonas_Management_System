<?php
require dirname(__DIR__, 2).'/2026-10-05/staff-confirmation-before-payment/preview-bootstrap.php';

use App\Models\Order;
use App\Services\{DesignChangeService, DesignChangeStore};
use Illuminate\Support\Str;

$fixture = json_decode(file_get_contents(__DIR__.'/preview-fixture.json'), true, 512, JSON_THROW_ON_ERROR);
$app = previewApplication($fixture['database'], $fixture['storage']);
$store = new class extends DesignChangeStore {
    public function root(): string { return storage_path('app/design-change-benchmark'); }
};
$changes = new DesignChangeService($store);
$order = Order::findOrFail($fixture['orders']['pending']['id']);
$snapshot = $changes->snapshot($order);
$orders = [];
for ($i = 0; $i < 10; $i++) {
    $item = clone $order;
    $item->receipt_key = 'benchmark-'.$i;
    $orders[] = $item;
}
$measure = function () use ($orders, $store, $changes) {
    $samples = [];
    for ($run = 0; $run < 101; $run++) {
        $start = hrtime(true);
        foreach ($orders as $order) {
            $store->read($order, true); // Cold per-request application cache, warm OS filesystem cache.
            $changes->summary($order);
        }
        $samples[] = (hrtime(true) - $start) / 1e6;
    }
    sort($samples);
    return ['median_ms' => round($samples[50], 3), 'p95_ms' => round($samples[95], 3)];
};
$results = ['empty_history_ten_orders' => $measure()];
foreach ([10, 100] as $count) {
    foreach ($orders as $item) {
        $state = $store->read($item, true);
        $state['requests'] = [];
        for ($i = 0; $i < $count; $i++) {
            $id = (string) Str::uuid();
            $state['requests'][$id] = ['id' => $id, 'status' => 'approved', 'submitted_at' => now()->toIso8601String(),
                'before' => $snapshot, 'proposed' => $snapshot, 'base_fingerprint' => $changes->fingerprint($snapshot),
                'reviewer_id' => 1, 'reviewer_name' => 'Benchmark reviewer', 'decided_at' => now()->toIso8601String(), 'reason' => null];
        }
        $store->locked($item, fn () => $store->write($item, $state));
    }
    $results[$count.'_past_requests_per_order'] = $measure();
    $results[$count.'_past_requests_per_order']['bytes_per_file'] = filesize($store->statePath($orders[0]));
}
$results['scope'] = 'Local metadata read + summary for 10 displayed orders; excludes PHP bootstrap, database queries, network, HTML and photo downloads. Synthetic history is kept in isolated benchmark storage.';
file_put_contents(__DIR__.'/performance-results.json', json_encode($results, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
echo json_encode($results, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR).PHP_EOL;
