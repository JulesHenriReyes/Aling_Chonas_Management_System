<?php
require dirname(__DIR__, 4).'/tests/Support/mariadb-bootstrap.php';
$app = disposableMariaDbApplication();
[$script, $directory, $index, $storage] = $argv;
$app->useStoragePath($storage);
foreach (['public' => 'public', 'staff_references' => 'staff-order-references', 'design_references' => 'order-design-references'] as $disk => $folder) {
    config(['filesystems.disks.'.$disk.'.root' => $storage.'/app/'.$folder]);
}
Tests\Support\DisposableDatabase::guard($app);
touch($directory.'/ready-'.$index);
$deadline = microtime(true)+15;
while (! file_exists($directory.'/release')) {
    if (microtime(true)>$deadline) throw new RuntimeException('Worker barrier timed out.');
    usleep(10000);
}
$result = app(\App\Services\CompletedOrderRetentionService::class)->run(true);
file_put_contents($directory.'/result-'.$index.'.json', json_encode($result, JSON_THROW_ON_ERROR));
echo json_encode($result, JSON_THROW_ON_ERROR);
