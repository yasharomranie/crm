<x-filament-panels::page>
    @php($setting = $this->currentSetting())

    <div class="flex items-start justify-between gap-4 rounded-lg border border-gray-200 p-4 dark:border-white/10" dir="rtl">
        <div class="flex items-center gap-3">
            <x-filament::badge :color="$setting->is_enabled ? 'success' : 'gray'">
                {{ $setting->is_enabled ? 'فعال' : 'غیرفعال' }}
            </x-filament::badge>

            <x-filament::badge :color="$setting->dns_verified_at ? 'success' : 'warning'">
                {{ $setting->dns_verified_at ? 'DNS متصل' : 'DNS در انتظار تأیید' }}
            </x-filament::badge>

            @if ($setting->domain)
                <span class="text-sm text-gray-500 dark:text-gray-400" dir="ltr">{{ $setting->domain }}</span>
            @endif
        </div>

        {{-- The big, hard-to-miss "?" the user asked for - opens the full
             three-layer explanation (app / DNS / hosting-panel) as a modal,
             right next to the settings it explains. --}}
        <button
            type="button"
            wire:click="mountAction('help')"
            title="راهنمای کامل اتصال دامنه"
            class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full border-2 border-primary-500 text-primary-600 transition hover:bg-primary-50 dark:text-primary-400 dark:hover:bg-primary-500/10"
        >
            <span class="text-2xl font-bold leading-none">؟</span>
        </button>
    </div>

    <form wire:submit="save" class="mt-6 space-y-6">
        {{ $this->form }}

        <x-filament::button type="submit">
            ذخیره تنظیمات
        </x-filament::button>
    </form>
</x-filament-panels::page>
