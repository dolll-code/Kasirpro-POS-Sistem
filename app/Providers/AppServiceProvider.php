<?php

namespace App\Providers;

use App\Models\User;
use App\Policies\UserPolicy;
use Illuminate\Support\Facades\Gate;
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
        Gate::policy(User::class, UserPolicy::class);

        // Generic permission gate: Gate::allows('permission', 'products.view')
        Gate::define('permission', function (User $user, string $permission) {
            return $user->hasPermission($permission);
        });

        // Specific ownership gate for sales transactions (Kasir cannot view other cashier's transaction without permission)
        Gate::define('view-sale', function (User $user, int $saleCashierId) {
            if ($user->hasPermission('sales.view_all')) {
                return true;
            }

            if ($user->hasPermission('sales.view_own') && $user->id === $saleCashierId) {
                return true;
            }

            return false;
        });
    }
}
