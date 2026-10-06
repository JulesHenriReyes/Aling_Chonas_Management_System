<?php

function documentQaApplication(array $fixture): Illuminate\Foundation\Application
{
    foreach (['APP_ENV' => 'testing', 'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => $fixture['database'],
        'DB_URL' => '', 'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'file', 'APP_URL' => $fixture['url']] as $key => $value) {
        putenv($key.'='.$value);
        $_ENV[$key] = $_SERVER[$key] = $value;
    }
    require_once dirname(__DIR__, 4).'/vendor/autoload.php';
    Tests\Support\DisposableDatabase::storage();
    foreach (['framework/views', 'framework/sessions', 'framework/cache', 'logs', 'app/private', 'app/public', 'app/payment-receipts'] as $directory) {
        if (! is_dir($fixture['storage'].'/'.$directory)) mkdir($fixture['storage'].'/'.$directory, 0777, true);
    }
    $app = require dirname(__DIR__, 4).'/bootstrap/app.php';
    $app->addAbsoluteCachePathPrefix('C:');
    $app->useStoragePath($fixture['storage']);
    $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    Tests\Support\DisposableDatabase::guard($app, $fixture['database']);
    config(['session.driver' => 'file', 'session.cookie' => 'bakery_document_qa_preview', 'app.url' => $fixture['url']]);
    $app->instance('env', 'document-qa-preview');

    return $app;
}
