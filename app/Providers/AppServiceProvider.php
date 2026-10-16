<?php

namespace App\Providers;

use App\Http\Middleware\EnsureIdempotentLivewireUpdate;
use App\Livewire\AiAssistantWidget;
use App\Models\Booking;
use App\Models\CustomerAppNavItem;
use App\Models\Deal;
use App\Models\FormSubmission;
use App\Models\GuestTicketReply;
use App\Models\User;
use App\Observers\BookingObserver;
use App\Observers\DealObserver;
use App\Observers\FormSubmissionObserver;
use App\Policies\DynamicPermissionPolicy;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Number;
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
        $this->registerPersianNumberMacro();
        $this->registerRateLimiters();
        $this->registerAuthRedirects();
        $this->registerCustomerAppNavComposer();

        // Filament's own number formatting (TextColumn::numeric(), the
        // table pagination/selection UI, etc.) already resolves to Persian
        // digits because it passes app()->getLocale() into Number::format()
        // explicitly - but any *bare* Number::format()/Number::currency()
        // call elsewhere in the app (with no locale argument) falls back to
        // Number's own static default, which is 'en' unless told otherwise.
        // This keeps that default in sync with the app's actual locale.
        Number::useLocale(config('app.locale'));

        // Explicit registration (rather than relying on Livewire's
        // app/Livewire auto-discovery) so the tag name is guaranteed
        // stable regardless of Livewire version - the panel-wide render
        // hook in AdminPanelProvider mounts it by this exact tag.
        Livewire::component('ai-assistant-widget', AiAssistantWidget::class);

        FormSubmission::observe(FormSubmissionObserver::class);
        Booking::observe(BookingObserver::class);
        Deal::observe(DealObserver::class);

        $this->attachIdempotencyMiddlewareToLivewireUpdateRoute();
    }

    /**
     * See EnsureIdempotentLivewireUpdate's own doc comment for why this
     * exists. Deferred to app()->booted() rather than run here directly,
     * so it doesn't matter whether Livewire's own service provider has
     * registered its update route yet at this point in the boot cycle -
     * by the time every provider has booted, it always has.
     */
    protected function attachIdempotencyMiddlewareToLivewireUpdateRoute(): void
    {
        $this->app->booted(function () {
            foreach (Route::getRoutes() as $route) {
                if (! str($route->getName())->endsWith('livewire.update')) {
                    continue;
                }

                if (! in_array(EnsureIdempotentLivewireUpdate::class, $route->middleware(), true)) {
                    $route->middleware(EnsureIdempotentLivewireUpdate::class);
                }
            }
        });
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
     * The Persian-digit counterpart of the macro above, for every number
     * that reaches the screen *without* going through Number::format() -
     * a raw number_format() call, a string-interpolated count/percentage in
     * a stat widget or an AI assistant alert, a chart's pre-formatted axis
     * label. Number::toPersian(1234) / Number::toPersian("۱۴ روز") both just
     * swap Latin digit characters for Persian ones wherever they appear, so
     * it's safe to wrap either a bare number or an already-composed string.
     */
    protected function registerPersianNumberMacro(): void
    {
        Number::macro('toPersian', fn (string|int|float $value): string => CalendarUtils::convertNumbers((string) $value));
    }

    /**
     * Keyed by mobile number (not just IP) so one phone can't be SMS-bombed
     * from many IPs, and one IP can't cycle through many phone numbers -
     * both limits apply together on every OTP request (see
     * App\Http\Controllers\CustomerApp\AuthController::requestOtp()).
     */
    protected function registerRateLimiters(): void
    {
        RateLimiter::for('customer-otp', function ($request) {
            return [
                Limit::perMinute(3)->by('otp-mobile:'.$request->input('mobile')),
                Limit::perMinute(10)->by('otp-ip:'.$request->ip()),
            ];
        });

        // Public lead forms have no login to gate them, so one IP is capped
        // per minute and per hour to keep a script from flooding the
        // pipeline with junk deals.
        RateLimiter::for('lead-capture', function ($request) {
            return [
                Limit::perMinute(5)->by('lead-ip-minute:'.$request->ip()),
                Limit::perHour(30)->by('lead-ip-hour:'.$request->ip()),
            ];
        });
    }

    /**
     * An unauthenticated hit on an 'auth:customer' route (see
     * routes/web.php's وب‌اپ مشتریان group) must land on the customer-app's
     * own login screen, not the staff one - Laravel's own default redirect
     * target is a route named 'login', which this app never defines
     * (Filament's own is 'filament.admin.auth.login'), so every other
     * 'auth'-gated area intentionally still 500s on a guest hit instead of
     * redirecting (see Seo\GoogleOAuthControllerTest's own note on this) -
     * preserved here by falling through to that same route('login') call
     * for every path outside /app.
     */
    protected function registerAuthRedirects(): void
    {
        Authenticate::redirectUsing(function (Request $request) {
            return $request->is('app/*')
                ? route('customer-app.auth.login')
                : route('login');
        });
    }

    /**
     * Shares the وب‌اپ مشتریان's bottom nav bar items - and its raised,
     * round auth button (see partials.customer-app-bottom-nav) - with
     * every page that renders that partial, instead of each controller
     * re-resolving them: keeps the per-controller code free of a concern
     * (what the global nav looks like right now) that belongs to the nav
     * itself. The auth button's own destination is fixed here, never
     * admin-editable, same reasoning as CustomerAppNavItem::forDisplay():
     * signed out it goes to login, signed in it goes to "پروفایل کاربری".
     */
    protected function registerCustomerAppNavComposer(): void
    {
        View::composer([
            'customer-app',
            'customer-app-bookings',
            'customer-app-wallet',
            'customer-app-tickets',
            'customer-app-support',
            'customer-app-support-show',
            'customer-app-profile',
            'customer-app-trip-planner',
            'customer-app-trip-plan-show',
        ], function ($view) {
            $guest = Auth::guard('customer')->user();
            $currentRouteName = Route::currentRouteName();

            $view->with('navItems', CustomerAppNavItem::forDisplay($guest, $currentRouteName));

            $view->with('authNavItem', [
                'url' => $guest ? route('customer-app.profile') : route('customer-app.auth.login'),
                'label' => $guest ? 'پروفایل' : 'ورود',
                'initial' => $guest ? mb_substr($guest->name, 0, 1) : null,
                'active' => $currentRouteName === 'customer-app.profile',
            ]);
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

        // A reply is never a standalone module in its own right (see
        // GuestTickets\RelationManagers\RepliesRelationManager) - it shares
        // the parent ticket's own "guest-tickets" permission rather than
        // needing a brand new permission a role would have to be granted
        // separately just to reply to tickets it can already manage.
        Gate::policy(GuestTicketReply::class, 'policy.access-control.guest-tickets');
    }
}
