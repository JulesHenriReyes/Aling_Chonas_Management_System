<?php

require __DIR__.'/preview-bootstrap.php';
$fixture = json_decode(file_get_contents(__DIR__.'/evidence/preview-fixture.json'), true, 512, JSON_THROW_ON_ERROR);
previewApplication($fixture['database'], $fixture['storage']);
$snapshot = [];
foreach (['customers', 'orders', 'order_details', 'order_images', 'payments', 'payment_proofs', 'gcash_references', 'refunds',
    'supplies', 'inventory_operations', 'inventory_baselines', 'inventory_transactions', 'expenses', 'expense_audits'] as $table) {
    $rows = Illuminate\Support\Facades\DB::table($table)->orderBy('id')->get();
    $snapshot[$table] = ['count' => $rows->count(), 'sha256' => hash('sha256', $rows->toJson())];
}
$snapshot['supply-1'] = (array) Illuminate\Support\Facades\DB::table('supplies')->where('id', 1)->first(['current_quantity', 'stock_version']);
echo json_encode($snapshot, JSON_THROW_ON_ERROR);
