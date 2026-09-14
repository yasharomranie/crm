<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Dashboard;
use App\Filament\Resources\AiProviders\AiProviderResource;
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
            ->font('Vazirmatn')
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->colors([
                'primary' => Color::Amber,
            ])
            ->userMenuItems([
                MenuItem::make()
                    ->label('تنظیمات هوش مصنوعی')
                    ->icon(Heroicon::OutlinedCog6Tooth)
                    ->url(fn () => AiProviderResource::getUrl()),
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
            ->renderHook(
                PanelsRenderHook::BODY_END,
                fn (): HtmlString => new HtmlString(<<<'HTML'
                    <script>
                        document.addEventListener('livewire:init', () => {
                            Livewire.on('open-in-new-tab', ({ url }) => window.open(url, '_blank'));
                        });
                    </script>
                    HTML),
            );
    }
}
