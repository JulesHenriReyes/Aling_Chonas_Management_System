<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Tests\Support\DisposableDatabase;

function disposableMariaDbApplication(): Application
{
    putenv('APP_ENV=testing');
    $_ENV['APP_ENV'] = $_SERVER['APP_ENV'] = 'testing';
    require_once dirname(__DIR__, 2).'/vendor/autoload.php';
    $storage = DisposableDatabase::storage();
    $app = require dirname(__DIR__, 2).'/bootstrap/app.php';
    $app->addAbsoluteCachePathPrefix('C:');
    $app->useStoragePath($storage);
    $app->make(Kernel::class)->bootstrap();
    DisposableDatabase::guard($app);
    if ($app['db']->connection()->getDriverName() !== 'mysql') {
        throw new RuntimeException('This acceptance run requires the disposable MariaDB manifest.');
    }

    return $app;
}
