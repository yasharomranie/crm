<?php

namespace App\Providers;

use App\Models\User;
use App\Policies\DynamicPermissionPolicy;
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
        $this->registerAccessControl();
    }

    /**
     * Wire the dynamic, config-driven access-control system
     * (config/access-control.php): a super-admin bypass, plus one generic
     * DynamicPermissionPolicy instance per resource-backed module.
     */
    protected function registerAccessControl(): void
    {
        Gate::before(function (User $user, string $ability) {
            if ($user->hasRole('admin')) {
                return true;
            }

            // Backward-compatible default: a user with no role assigned yet
            // keeps unrestricted access, exactly as before this system
            // existed. Assigning any role switches them to that role's own
            // permissions only.
            if ($user->roles()->doesntExist()) {
                return true;
            }

            return null;
        });

        foreach (config('access-control.resources', []) as $module => $definition) {
            $bindingKey = "policy.access-control.{$module}";

            $this->app->bind($bindingKey, fn () => new DynamicPermissionPolicy($module));

            Gate::policy($definition['model'], $bindingKey);
        }
    }
}
