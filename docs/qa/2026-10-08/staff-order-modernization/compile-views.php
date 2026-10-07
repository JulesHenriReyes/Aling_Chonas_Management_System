<?php
putenv('APP_ENV=testing'); $_ENV['APP_ENV'] = $_SERVER['APP_ENV'] = 'testing';
require dirname(__DIR__, 4).'/vendor/autoload.php';
$storage = Tests\Support\DisposableDatabase::storage();
$app = require dirname(__DIR__, 4).'/bootstrap/app.php';
$app->addAbsoluteCachePathPrefix('C:'); $app->useStoragePath($storage);
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
Tests\Support\DisposableDatabase::guard($app);
$result = Illuminate\Support\Facades\Artisan::call('view:cache');
echo Illuminate\Support\Facades\Artisan::output();
file_put_contents(__DIR__.'/view-compilation.json', json_encode(['exit_code' => $result, 'storage' => $storage, 'temporary_database' => true, 'compiled_views' => count(glob($storage.'/framework/views/*.php'))], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
exit($result);
