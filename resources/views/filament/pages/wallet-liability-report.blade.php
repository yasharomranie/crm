<x-filament-panels::page>
    @php($summary = $this->summary())

    <div dir="rtl" class="space-y-6">
        <p class="text-sm text-gray-500 dark:text-gray-400">
            موجودی کیف پول مشتریان یک بدهی شماست. «جمع گردش» مجموع واریز منهای برداشت‌های ثبت‌شده است و باید با موجودی برابر باشد؛
            اگر برابر نبود، موجودی خارج از مسیر عادی تغییر کرده و باید بررسی شود.
        </p>

        <div class="grid grid-cols-2 gap-3 lg:grid-cols-3">
            <div class="rounded-lg border p-3">
                <div class="text-xs text-gray-500 dark:text-gray-400">کل بدهی کیف پول</div>
                <div class="text-lg font-semibold">{{ $this->toman($summary['total_liability']) }}</div>
            </div>
            <div class="rounded-lg border p-3">
                <div class="text-xs text-gray-500 dark:text-gray-400">مشتریان دارای موجودی</div>
                <div class="text-lg font-semibold">{{ \Illuminate\Support\Number::toPersian($summary['guests_with_balance']) }}</div>
            </div>
            <div class="rounded-lg border p-3">
                <div class="text-xs text-gray-500 dark:text-gray-400">واریز در ۳۰ روز اخیر</div>
                <div class="text-lg font-semibold">{{ $this->toman($summary['credited_30d']) }}</div>
            </div>
            <div class="rounded-lg border p-3">
                <div class="text-xs text-gray-500 dark:text-gray-400">برداشت در ۳۰ روز اخیر</div>
                <div class="text-lg font-semibold">{{ $this->toman($summary['debited_30d']) }}</div>
            </div>
            <div class="rounded-lg border p-3">
                <div class="text-xs text-gray-500 dark:text-gray-400">در حال انقضا (۳۰ روز آینده)</div>
                <div class="text-lg font-semibold">{{ $this->toman($summary['expiring_30d']) }}</div>
            </div>
            <div class="rounded-lg border p-3 {{ $summary['mismatched'] > 0 ? 'border-danger-500' : '' }}">
                <div class="text-xs text-gray-500 dark:text-gray-400">مشتریان ناهماهنگ با گردش</div>
                <div class="text-lg font-semibold">{{ \Illuminate\Support\Number::toPersian($summary['mismatched']) }}</div>
            </div>
        </div>

        {{ $this->table }}
    </div>
</x-filament-panels::page>
