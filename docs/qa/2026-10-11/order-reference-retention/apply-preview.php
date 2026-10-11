<?php
require dirname(__DIR__, 2).'/2026-10-05/staff-confirmation-before-payment/preview-bootstrap.php';
$fixture = json_decode(file_get_contents(__DIR__.'/preview-fixture.json'), true, 512, JSON_THROW_ON_ERROR);
previewApplication($fixture['database'], $fixture['storage']);
$result = app(\App\Services\CompletedOrderRetentionService::class)->run(in_array('--apply', $argv, true));
file_put_contents(__DIR__.'/cleanup-result.json', json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
echo json_encode($result, JSON_THROW_ON_ERROR)."\n";
