<?php

namespace Tests;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Tests\Support\DisposableDatabase;

abstract class TestCase extends BaseTestCase
{
    public function createApplication()
    {
        $storage = DisposableDatabase::storage();
        $app = require dirname(__DIR__).'/bootstrap/app.php';
        $this->traitsUsedByTest = array_flip(class_uses_recursive(static::class));
        $app->addAbsoluteCachePathPrefix('C:');
        $app->useStoragePath($storage);
        $app->make(Kernel::class)->bootstrap();
        DisposableDatabase::guard($app);

        return $app;
    }
}
