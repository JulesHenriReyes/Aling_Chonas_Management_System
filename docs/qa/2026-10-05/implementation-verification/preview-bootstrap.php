<?php

function previewApplication(string $database, string $storage): Illuminate\Foundation\Application
{
    foreach (['APP_ENV' => 'testing', 'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => $database, 'DB_URL' => '',
        'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'file', 'APP_URL' => 'http://127.0.0.1:8124'] as $key => $value) {
        putenv($key.'='.$value);
        $_ENV[$key] = $_SERVER[$key] = $value;
    }
    require_once dirname(__DIR__, 4).'/vendor/autoload.php';
    Tests\Support\DisposableDatabase::storage();
    foreach (['framework/views', 'framework/sessions', 'framework/cache', 'logs', 'app/private', 'app/public', 'app/payment-receipts'] as $directory) {
        if (!is_dir($storage.'/'.$directory)) mkdir($storage.'/'.$directory, 0777, true);
    }
    $app = require dirname(__DIR__, 4).'/bootstrap/app.php';
    $app->addAbsoluteCachePathPrefix('C:');
    $app->useStoragePath($storage);
    $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    Tests\Support\DisposableDatabase::guard($app, $database);
    config(['session.driver' => 'file', 'session.cookie' => 'bakery_implementation_preview', 'app.url' => 'http://127.0.0.1:8124']);
    $app->instance('env', 'implementation-preview'); // Exercise actual browser CSRF/session handling after isolation is proven.

    return $app;
}
