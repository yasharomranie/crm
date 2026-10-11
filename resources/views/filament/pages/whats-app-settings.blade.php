<x-filament-panels::page>
    <div class="mb-6 rounded-xl border border-gray-200 bg-gray-50 p-4 text-sm dark:border-white/10 dark:bg-white/5">
        <p class="font-medium text-gray-700 dark:text-gray-300">آدرس وب‌هوک (این آدرس را در پنل توسعه‌دهندگان متا وارد کنید)</p>
        <p class="mt-1 select-all break-all font-mono text-xs text-gray-500 dark:text-gray-400">{{ $this->webhookUrl() }}</p>
    </div>

    <form wire:submit="save" class="space-y-6">
        {{ $this->form }}

        <x-filament::button type="submit">
            ذخیره تنظیمات
        </x-filament::button>
    </form>
</x-filament-panels::page>
