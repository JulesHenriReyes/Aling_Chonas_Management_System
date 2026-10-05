<?php

namespace Tests;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Tests\Support\DisposableDatabase;

abstract class TestCase extends BaseTestCase
{
    protected function confirmPaymentFixture(\App\Models\Order $order): void
    {
        $fresh = $order->fresh();
        if (! $fresh->needsStaffReview() || $fresh->hasDownPayment()) {
            return;
        }
        $staff = \App\Models\User::where('is_active', true)->where('role', 'owner')->first()
            ?? \App\Models\User::where('is_active', true)->where('role', 'assistant')->first();
        if ($staff) {
            app(\App\Services\OrderReviewService::class)->confirm($fresh, $staff, true);
            $order->refresh();
        }
    }

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
