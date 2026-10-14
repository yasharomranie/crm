<x-filament-panels::page>
    <div dir="rtl" class="space-y-6" @if ($this->isDeployPending()) wire:poll.5s @endif>
        <div class="rounded-lg border border-gray-200 p-4 dark:border-white/10">
            <p class="text-sm text-gray-600 dark:text-gray-300">
                با زدن این دکمه، آخرین نسخه‌ی کد از گیت‌هاب روی سرور گرفته می‌شود، مایگریشن‌های جدید اجرا می‌شوند
                و کش‌های برنامه بازسازی می‌گردند. اجرای واقعی توسط یک Cron Job روی سرور، حداکثر تا یک دقیقه بعد
                از ثبت درخواست، انجام می‌شود.
            </p>

            <div class="mt-4">
                @if ($this->isDeployPending())
                    <x-filament::badge color="warning" icon="heroicon-m-clock">
                        درخواست دیپلوی ثبت شده — در انتظار اجرا توسط Cron Job...
                    </x-filament::badge>
                @else
                    <x-filament::button wire:click="requestDeploy" wire:loading.attr="disabled" icon="heroicon-m-rocket-launch">
                        اجرای دیپلوی
                    </x-filament::button>
                @endif
            </div>
        </div>

        <div class="rounded-lg border border-gray-200 p-4 dark:border-white/10">
            <p class="mb-2 text-xs font-medium text-gray-500 dark:text-gray-400">
                آخرین خروجی دیپلوی
                @if ($this->lastDeployAt())
                    <span class="font-normal text-gray-400">— {{ $this->lastDeployAt() }}</span>
                @endif
            </p>
            @if ($this->lastDeployLog())
                <pre class="max-h-96 overflow-x-auto overflow-y-auto rounded bg-gray-950 p-3 text-xs text-gray-200" dir="ltr">{{ $this->lastDeployLog() }}</pre>
            @else
                <p class="text-sm text-gray-400">هنوز هیچ دیپلویی از این صفحه ثبت نشده است.</p>
            @endif
        </div>
    </div>
</x-filament-panels::page>
