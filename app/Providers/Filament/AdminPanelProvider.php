<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Dashboard;
use App\Filament\Resources\AiProviders\AiProviderResource;
use App\Filament\Support\LocalViteFontProvider;
use Filament\Actions\Action;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\MenuItem;
use Filament\Navigation\NavigationGroup;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Icons\Heroicon;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\HtmlString;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->brandName(fn () => config('app.name'))
            // The mascot + FarsiWP wordmark, matching the public landing
            // page and the AI assistant widget - see brand-logo.blade.php.
            // brandLogoHeight is generous enough for the two-line text
            // block beside the mascot, not just a single logo image.
            ->brandLogo(fn () => view('filament.brand-logo'))
            ->brandLogoHeight('2.75rem')
            // Self-hosted, not Filament's default Bunny Fonts CDN - see
            // LocalViteFontProvider's own doc comment for why.
            ->font('Vazirmatn', provider: LocalViteFontProvider::class)
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->colors([
                'primary' => Color::Amber,
            ])
            ->userMenuItems([
                // Points at a Claude-published Artifact (a screenshot-illustrated
                // walkthrough of every module), not a page in this app - kept
                // up to date by hand alongside new features, so its URL isn't
                // stored anywhere more structured than this one line.
                MenuItem::make()
                    ->label('راهنمای سیستم')
                    ->icon(Heroicon::OutlinedBookOpen)
                    ->url('https://claude.ai/artifact/LaYcaduHmsj2uh3hjqqruN')
                    ->openUrlInNewTab(),
                MenuItem::make()
                    ->label('تنظیمات هوش مصنوعی')
                    ->icon(Heroicon::OutlinedCog6Tooth)
                    ->url(fn () => AiProviderResource::getUrl()),
                // A plain link (url '#') rather than a real Filament
                // action: this needs to run client-side JS only (request
                // Notification permission, subscribe via PushManager -
                // see pwa-push.blade.php), never a Livewire round trip.
                Action::make('togglePushNotifications')
                    ->label('فعال/غیرفعال‌سازی اعلان‌های فوری')
                    ->icon(Heroicon::OutlinedBell)
                    ->url('#')
                    ->extraAttributes(['onclick' => 'event.preventDefault(); window.farsiwpTogglePush?.();']),
            ])
            ->navigationGroups([
                // The AI assistant is the flagship module - all-of-system
                // reporting/insight - so it leads the sidebar regardless of
                // the order its resources/pages happen to be discovered in.
                NavigationGroup::make('دستیار هوشمند مدیریتی'),
                NavigationGroup::make('حقوقی و قرارداد'),
                NavigationGroup::make('زیرساخت سازمانی'),
                NavigationGroup::make('فرم‌ساز و گردش‌کار'),
                NavigationGroup::make('وظایف و پروژه‌ها'),
                NavigationGroup::make('اقامتی و هتلداری'),
                NavigationGroup::make('CRM و مشتریان'),
                NavigationGroup::make('مدیریت اسناد'),
                NavigationGroup::make('ارتباطات داخلی'),
                NavigationGroup::make('حضور و غیاب'),
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            // The dashboard's own widgets (stats overview + charts) live in
            // app/Filament/Widgets and are picked up by discovery below;
            // Filament's default AccountWidget/FilamentInfoWidget placeholder
            // boxes are deliberately not registered.
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ])
            // TableExports::pdf() (app/Filament/Support/TableExports.php) needs
            // to open the rendered report in a new tab *after* it finishes
            // building the file server-side - a plain link action can't do
            // that (its url() is fixed at render time), so it dispatches this
            // Livewire browser event instead and this one small listener
            // handles it panel-wide.
            //
            // This clicks a real <a target="_blank"> instead of calling
            // window.open() directly: by the time this listener runs, the
            // AJAX round trip for the action has already finished, so the
            // browser no longer sees it as "directly caused by the click"
            // and window.open() gets silently popup-blocked in most
            // browsers. A synthetic anchor click is exactly what Livewire's
            // own file-download feature does for the same reason (see
            // vendor/livewire/livewire/js/features/supportFileDownloads.js)
            // and isn't subject to that restriction.
            // Chart.js's own default numeric tick formatter special-cases
            // the value 0 and always returns the literal string '0',
            // skipping the Intl.NumberFormat(locale) path every other tick
            // goes through - so ->getOptions()'s 'locale' => 'fa' (see the
            // chart widgets under app/Filament/Widgets) correctly Persianizes
            // every tick except the zero baseline. There's no way to reach
            // this from PHP (ChartWidget::getOptions() is JSON-encoded, not
            // a place to hand Chart.js a JS callback), so this plugin -
            // registered through Filament's own documented
            // window.filamentChartJsGlobalPlugins hook, read once when its
            // chart bundle is lazy-loaded - patches just that one tick after
            // Chart.js (re)computes every scale's ticks on each update, but
            // still before the canvas is drawn. Must run before that lazy
            // import, which this satisfies simply by being a plain
            // synchronous script.
            ->renderHook(
                PanelsRenderHook::BODY_END,
                fn (): HtmlString => new HtmlString(<<<'HTML'
                    <script>
                        window.filamentChartJsGlobalPlugins = [
                            ...(window.filamentChartJsGlobalPlugins ?? []),
                            {
                                id: 'persianZeroTick',
                                afterUpdate(chart) {
                                    if (chart.options?.locale !== 'fa') {
                                        return;
                                    }

                                    for (const scale of Object.values(chart.scales ?? {})) {
                                        for (const tick of scale.ticks ?? []) {
                                            if (tick.label === '0') {
                                                tick.label = '۰';
                                            }
                                        }
                                    }
                                },
                            },
                        ];
                    </script>
                    HTML),
            )
            ->renderHook(
                PanelsRenderHook::BODY_END,
                fn (): HtmlString => new HtmlString(<<<'HTML'
                    <script>
                        document.addEventListener('livewire:init', () => {
                            Livewire.on('open-in-new-tab', ({ url }) => {
                                const link = document.createElement('a');
                                link.href = url;
                                link.target = '_blank';
                                link.rel = 'noopener';
                                link.style.display = 'none';
                                document.body.appendChild(link);
                                link.click();
                                document.body.removeChild(link);
                            });
                        });
                    </script>
                    HTML),
            )
            // The floating AI assistant mascot - see AiAssistantWidget for
            // why this has to be mounted panel-wide via a render hook
            // rather than as a normal page: it needs to follow the user
            // across every screen, not just its own route.
            ->renderHook(
                PanelsRenderHook::BODY_END,
                fn () => view('filament.render-hooks.ai-assistant-widget'),
            )
            // Makes the panel installable as a PWA (manifest + service
            // worker) - see resources/views/partials/pwa-head.blade.php and
            // public/sw.js for what that actually does. The same partials
            // are included on the public landing page (welcome.blade.php),
            // so either entry point can trigger the browser's install
            // prompt for the one shared app.
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn () => view('partials.pwa-head'),
            )
            ->renderHook(
                PanelsRenderHook::BODY_END,
                fn () => view('partials.pwa-register'),
            )
            ->renderHook(
                PanelsRenderHook::BODY_END,
                fn () => view('partials.pwa-install-prompt'),
            )
            // Mirrors the sidebar's own unread/alert counts onto the
            // installed app icon via the Badging API - admin panel only,
            // since it needs an authenticated user's counts (the public
            // landing page has none to show).
            ->renderHook(
                PanelsRenderHook::BODY_END,
                fn () => view('partials.pwa-badge'),
            )
            // Wires up the "فعال/غیرفعال‌سازی اعلان‌های فوری" user-menu
            // item above - see pwa-push.blade.php's own doc comment.
            ->renderHook(
                PanelsRenderHook::BODY_END,
                fn () => view('partials.pwa-push'),
            )
            // App-wide "you're offline" bottom bar + leave-page guard (every
            // panel page) - a lighter safety net than the Gate Scanner's own
            // dedicated offline mode, for the ordinary case of a brief
            // connectivity drop while filling out any other form. See the
            // partial's own doc comment.
            ->renderHook(
                PanelsRenderHook::BODY_END,
                fn () => view('partials.offline-guard'),
            )
            // Queues a Livewire action (any resource's "ثبت"/save, a table
            // action, anything) that fails purely because there's no
            // connection, and replays the exact same request the moment
            // it's back - covers Bookings, Deals, Form Submissions, Rooms
            // and every other Filament form with no per-resource code. See
            // the partial's own doc comment.
            ->renderHook(
                PanelsRenderHook::BODY_END,
                fn () => view('partials.offline-form-retry'),
            );
    }
}
