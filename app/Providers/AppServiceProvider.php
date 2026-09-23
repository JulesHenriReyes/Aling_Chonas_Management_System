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
        Gate::define('manage-users', fn (User $user) => $user->isOwner());

        // Every active business module is available to both approved staff roles.
        $staffCanOperate = fn (User $user) => in_array($user->role, ['owner', 'assistant'], true);
        Gate::define('manage-products', $staffCanOperate);
        Gate::define('view-reports', $staffCanOperate);
        Gate::define('manage-expenses', $staffCanOperate);
        Gate::define('manage-customers', $staffCanOperate);
        Gate::define('manage-orders', $staffCanOperate);
        Gate::define('cancel-orders', $staffCanOperate);
        Gate::define('record-payments', $staffCanOperate);
        Gate::define('manage-inventory', $staffCanOperate);

        RateLimiter::for('public-orders', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip());
        });
    }
}
