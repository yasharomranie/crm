<x-filament-panels::page>
    @php
        $labels = [
            'room_types' => 'نوع اتاق',
            'rooms' => 'اتاق',
            'guests' => 'مهمان',
            'bookings' => 'رزرو',
            'blocks' => 'واحد بسته‌شده',
            'companies' => 'شرکت',
            'deals' => 'معامله',
        ];
        $counts = $this->getCounts();
    @endphp

    <x-filament::section>
        <p style="line-height: 2; font-size: .9rem;">
            برای آزمایش صفحه‌ها روی سرور واقعی، با دکمهٔ «ساخت دادهٔ آزمایشی» چند رکورد نمونه با نشانهٔ «[تست]» ساخته می‌شود: یک رزرو مقیم، یک رزرو آینده، یک رزرو در انتظار، یک رزرو تمام‌شده، یک واحد «بسته تا اطلاع بعدی»، یک شرکت و دو معامله. هیچ رکورد موجودی تغییر نمی‌کند و «پاک کردن دادهٔ آزمایشی» فقط همین رکوردها را برمی‌دارد.
        </p>
    </x-filament::section>

    <x-filament::section heading="رکوردهای آزمایشی فعلی">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(8rem, 1fr)); gap: .75rem;">
            @foreach ($labels as $key => $label)
                <div style="text-align: center; padding: .75rem; border-radius: .75rem; background: rgb(128 128 128 / .08);">
                    <div style="font-size: 1.4rem; font-weight: 700;">{{ \Illuminate\Support\Number::toPersian($counts[$key] ?? 0) }}</div>
                    <div style="font-size: .8rem; opacity: .75;">{{ $label }}</div>
                </div>
            @endforeach
        </div>
    </x-filament::section>
</x-filament-panels::page>
