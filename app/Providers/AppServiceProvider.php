<?php

namespace App\Providers;

use App\Livewire\AiAssistantWidget;
use App\Models\User;
use App\Policies\DynamicPermissionPolicy;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use Morilog\Jalali\CalendarUtils;
use Morilog\Jalali\Jalalian;

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
        // Some hosts still run a MySQL/MariaDB combination where a utf8mb4
        // varchar(255) index (255 * 4 bytes) exceeds the key-length limit -
        // shortening the default to 191 chars keeps every unique/primary
        // key comfortably under that limit regardless of engine or version.
        Schema::defaultStringLength(191);

        $this->registerAccessControl();
        $this->registerJalaliDateMacro();

        // Explicit registration (rather than relying on Livewire's
        // app/Livewire auto-discovery) so the tag name is guaranteed
        // stable regardless of Livewire version - the panel-wide render
        // hook in AdminPanelProvider mounts it by this exact tag.
        Livewire::component('ai-assistant-widget', AiAssistantWidget::class);
    }

    /**
     * The whole application shows dates in the Jalali (Shamsi) calendar -
     * Filament tables/pickers get this via mokhosh/filament-jalali, but
     * plain Blade views (print/export documents, chat replies, etc.) just
     * format a Carbon instance directly. This macro is the one place that
     * conversion lives, so every view can write `$date?->toJalali()`
     * instead of repeating the Jalalian::fromCarbon(...) dance.
     */
    protected function registerJalaliDateMacro(): void
    {
        Carbon::macro('toJalali', function (string $format = 'Y/m/d H:i'): string {
            /** @var Carbon $this */
            return CalendarUtils::convertNumbers(Jalalian::fromCarbon($this)->format($format));
        });
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
