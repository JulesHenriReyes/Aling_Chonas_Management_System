<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $staffCanOperate = fn (User $user) => $user->is_active && in_array($user->role, ['owner', 'assistant'], true);
        $ownerCanManage = fn (User $user) => $user->is_active && $user->isOwner();
        foreach (['view-records', 'update-order-status', 'manage-expenses', 'manage-inventory'] as $capability) {
            Gate::define($capability, $staffCanOperate);
        }
        foreach (['manage-users', 'manage-products', 'view-reports', 'manage-customers', 'manage-orders',
            'cancel-orders', 'record-payments', 'review-proofs', 'manage-refunds'] as $capability) {
            Gate::define($capability, $ownerCanManage);
        }

        RateLimiter::for('public-orders', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip());
        });
        RateLimiter::for('public-quotes', fn (Request $request) => Limit::perMinute(30)->by($request->ip()));
        RateLimiter::for('public-receipts', fn (Request $request) => [
            Limit::perMinute(5)->by('receipt-ip:'.$request->ip()),
            Limit::perHour(10)->by('receipt-order:'.hash('sha256', (string) $request->route('token'))),
        ]);
    }
}
