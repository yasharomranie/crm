<?php

namespace App\Filament\Pages;

use App\Domain\WebApp\CustomDomainVerifier;
use App\Filament\Pages\Concerns\HasAccessControlledPage;
use App\Models\WebAppDomainSetting;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * A singleton settings screen (backs the single WebAppDomainSetting row) -
 * lets an admin point a domain they own at the customer-facing وب‌اپ (see
 * CustomerAppController) so it opens at that domain's own clean root
 * instead of only under this app's /app path. See
 * App\Http\Middleware\ServeCustomerAppOnCustomDomain for where the
 * connected domain is actually served, and the "help" action below for
 * the full step-by-step this page can't do on its own (DNS + hosting
 * panel steps are always outside this app's control).
 */
class WebAppDomainSettings extends Page implements HasForms
{
    use HasAccessControlledPage;
    use InteractsWithForms;

    protected string $view = 'filament.pages.web-app-domain-settings';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedGlobeAlt;

    protected static string|\UnitEnum|null $navigationGroup = 'وب‌اپ مشتریان';

    protected static ?string $navigationLabel = 'دامنه اختصاصی';

    protected static ?string $title = 'دامنه اختصاصی وب‌اپ مشتریان';

    /**
     * @var array<string, mixed>
     */
    public array $data = [];

    public static function accessControlPermission(): string
    {
        return 'web-app-domain-settings.access';
    }

    public function mount(): void
    {
        $setting = WebAppDomainSetting::current();

        $this->form->fill([
            'is_enabled' => $setting->is_enabled,
            'domain' => $setting->domain,
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Toggle::make('is_enabled')
                    ->label('فعال‌سازی دامنه اختصاصی')
                    ->helperText('تا وقتی دامنه با دکمهٔ «بررسی اتصال DNS» تأیید نشده، حتی با روشن بودن این گزینه چیزی روی آن دامنه نشان داده نمی‌شود.'),
                TextInput::make('domain')
                    ->label('نام دامنه')
                    ->placeholder('app.example.com')
                    ->maxLength(255)
                    ->helperText('بدون //:https و بدون اسلش انتهایی - فقط خود نام دامنه، مثل app.example.com یا example.com'),
            ]);
    }

    public function save(): void
    {
        $data = $this->form->getState();
        $setting = WebAppDomainSetting::current();

        // Changing the domain invalidates any previous verification -
        // a stale dns_verified_at must never point at a domain the admin
        // just typed in and hasn't actually connected yet.
        if ($data['domain'] !== $setting->domain) {
            $data['dns_verified_at'] = null;
        }

        $setting->update($data);

        Notification::make()->title('تنظیمات ذخیره شد.')->success()->send();
    }

    public function currentSetting(): WebAppDomainSetting
    {
        return WebAppDomainSetting::current();
    }

    public function expectedCnameTarget(): string
    {
        return app(CustomDomainVerifier::class)->expectedCnameTarget();
    }

    public function expectedIp(): ?string
    {
        return app(CustomDomainVerifier::class)->expectedIp();
    }

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('viewApp')
                ->label('مشاهده اپلیکیشن مشتری')
                ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                ->color('gray')
                ->url(fn () => $this->liveAppUrl())
                ->openUrlInNewTab(),

            Action::make('verifyDns')
                ->label('بررسی اتصال DNS')
                ->icon(Heroicon::OutlinedGlobeAlt)
                ->color('primary')
                ->action(function (): void {
                    $setting = WebAppDomainSetting::current();

                    if (! $setting->domain) {
                        Notification::make()
                            ->title('ابتدا نام دامنه را وارد و ذخیره کن.')
                            ->danger()
                            ->send();

                        return;
                    }

                    $verified = app(CustomDomainVerifier::class)->verify($setting->domain);

                    $setting->update(['dns_verified_at' => $verified ? now() : null]);

                    Notification::make()
                        ->title($verified
                            ? 'دامنه شناسایی و متصل شد.'
                            : 'رکورد DNS هنوز شناسایی نشد. اگر همین الان اضافه‌اش کردی، ممکن است تا ۲۴-۴۸ ساعت طول بکشد؛ کمی بعد دوباره امتحان کن.')
                        ->{$verified ? 'success' : 'warning'}()
                        ->send();
                }),

            // Not shown in the header toolbar (hidden()) - triggered
            // instead by the large "؟" button in the page's own view via
            // wire:click="mountAction('help')", right next to the
            // settings it explains.
            Action::make('help')
                ->label('راهنمای کامل اتصال دامنه')
                ->hidden()
                ->modalHeading('راهنمای کامل اتصال دامنه اختصاصی')
                ->modalContent(fn () => view('filament.pages.web-app-domain-help', [
                    'cnameTarget' => $this->expectedCnameTarget(),
                    'ip' => $this->expectedIp(),
                ]))
                ->modalWidth('2xl')
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('بستن'),
        ];
    }

    private function liveAppUrl(): string
    {
        $setting = WebAppDomainSetting::current();

        return $setting->isActiveFor($setting->domain)
            ? 'https://'.$setting->domain.'/'
            : route('customer-app');
    }
}
