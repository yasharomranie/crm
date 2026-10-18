<x-filament-panels::page>
    @php($report = $this->report())
    @php($number = fn ($value) => \Illuminate\Support\Number::toPersian($value))

    <div dir="rtl" class="space-y-6">
        <div class="flex flex-wrap items-center gap-2">
            @foreach ([7 => '۷ روز', 30 => '۳۰ روز', 90 => '۹۰ روز'] as $days => $label)
                <x-filament::button size="sm" :color="$this->periodDays === $days ? 'primary' : 'gray'" wire:click="$set('periodDays', {{ $days }})">{{ $label }}</x-filament::button>
            @endforeach
        </div>

        <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
            <div class="rounded-lg border p-3"><div class="text-xs text-gray-500">برنامه‌های ساخته‌شده</div><div class="text-lg font-semibold">{{ $number($report['plans']) }}</div></div>
            <div class="rounded-lg border p-3"><div class="text-xs text-gray-500">میانگین طول سفر (روز)</div><div class="text-lg font-semibold">{{ $number($report['average_days']) }}</div></div>
            <div class="rounded-lg border p-3"><div class="text-xs text-gray-500">میانگین امتیاز مهمانان</div><div class="text-lg font-semibold">{{ $report['average_rating'] === null ? '—' : $number($report['average_rating']).' از ۵' }}</div><div class="text-xs text-gray-500">{{ $number($report['rated_plans']) }} امتیاز</div></div>
            <div class="rounded-lg border p-3"><div class="text-xs text-gray-500">تبدیل به رزرو اقامت</div><div class="text-lg font-semibold">{{ $report['booking_conversion_percent'] === null ? '—' : $number($report['booking_conversion_percent']).'٪' }}</div><div class="text-xs text-gray-500">{{ $number($report['plans_with_booking']) }} برنامه</div></div>
            <div class="rounded-lg border p-3"><div class="text-xs text-gray-500">ساخته‌شده بدون هوش مصنوعی (جایگزین)</div><div class="text-lg font-semibold">{{ $number($report['fallback_plans']) }}</div></div>
            <div class="rounded-lg border p-3"><div class="text-xs text-gray-500">میانگین زمان ساخت</div><div class="text-lg font-semibold">{{ $report['average_generation_ms'] === null ? '—' : $number(round($report['average_generation_ms'] / 1000, 1)).' ثانیه' }}</div></div>
            <div class="rounded-lg border p-3"><div class="text-xs text-gray-500">برنامه‌های به‌اشتراک‌گذاشته‌شده</div><div class="text-lg font-semibold">{{ $number($report['shared_plans']) }}</div></div>
        </div>

        <div class="grid gap-4 lg:grid-cols-3">
            <div class="rounded-lg border p-3">
                <h3 class="mb-2 text-sm font-semibold">پرتکرارترین جاذبه‌ها</h3>
                @forelse ($report['top_attractions'] as $row)
                    <div class="flex justify-between text-sm"><span>{{ $row['name'] }}</span><span>{{ $number($row['count']) }}</span></div>
                @empty
                    <p class="text-sm text-gray-500">داده‌ای نیست.</p>
                @endforelse
            </div>
            <div class="rounded-lg border p-3">
                <h3 class="mb-2 text-sm font-semibold">دسته‌های محبوب</h3>
                @forelse ($report['categories'] as $row)
                    <div class="flex justify-between text-sm"><span>{{ $row['category'] }}</span><span>{{ $number($row['count']) }}</span></div>
                @empty
                    <p class="text-sm text-gray-500">داده‌ای نیست.</p>
                @endforelse
            </div>
            <div class="rounded-lg border p-3">
                <h3 class="mb-2 text-sm font-semibold">جاذبه‌های فعالی که هیچ‌وقت انتخاب نشده‌اند</h3>
                @forelse ($report['never_scheduled'] as $name)
                    <div class="text-sm">{{ $name }}</div>
                @empty
                    <p class="text-sm text-gray-500">همه‌ی جاذبه‌ها دست‌کم یک‌بار انتخاب شده‌اند.</p>
                @endforelse
                @if ($report['never_scheduled'])
                    <p class="mt-2 text-xs text-gray-500">دلیل می‌تواند ساعت کاری/تعطیلی، نبود اطلاعات (هزینه، مناسب کودک...) یا اولویت کم باشد.</p>
                @endif
            </div>
        </div>
    </div>
</x-filament-panels::page>
