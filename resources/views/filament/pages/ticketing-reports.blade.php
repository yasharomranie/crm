<x-filament-panels::page>
    @php($r = $this->report())
    @php($n = fn ($value) => \Illuminate\Support\Number::toPersian($value))

    <div dir="rtl" class="space-y-6">
        <div class="flex flex-wrap gap-2">
            @foreach (['today' => 'امروز', '7' => '۷ روز اخیر', '30' => '۳۰ روز اخیر'] as $value => $label)
                <x-filament::button size="sm" :color="$this->period === (string) $value ? 'primary' : 'gray'" wire:click="$set('period', '{{ $value }}')">{{ $label }}</x-filament::button>
            @endforeach
        </div>

        {{-- Sales --}}
        <div class="grid grid-cols-2 gap-3 lg:grid-cols-5">
            <div class="rounded-lg border p-3"><div class="text-xs text-gray-500">سفارش‌ها</div><div class="text-lg font-semibold">{{ $n($r['sales']['orders']) }}</div></div>
            <div class="rounded-lg border p-3"><div class="text-xs text-gray-500">بلیت‌های فروخته‌شده</div><div class="text-lg font-semibold">{{ $n($r['sales']['tickets']) }}</div></div>
            <div class="rounded-lg border p-3"><div class="text-xs text-gray-500">فروش ناخالص</div><div class="text-lg font-semibold">{{ $this->toman($r['sales']['gross']) }}</div></div>
            <div class="rounded-lg border p-3"><div class="text-xs text-gray-500">تخفیف</div><div class="text-lg font-semibold">{{ $this->toman($r['sales']['discount']) }}</div></div>
            <div class="rounded-lg border p-3"><div class="text-xs text-gray-500">فروش خالص</div><div class="text-lg font-semibold">{{ $this->toman($r['sales']['net']) }}</div></div>
        </div>

        <div class="grid gap-4 lg:grid-cols-2">
            <div class="rounded-lg border p-3">
                <h3 class="mb-2 text-sm font-semibold">به تفکیک محصول</h3>
                @forelse ($r['sales']['by_product'] as $row)
                    <div class="flex justify-between gap-2 text-sm"><span>{{ $row['name'] }} ({{ $n($row['tickets']) }} بلیت)</span><span>{{ $this->toman($row['net']) }}</span></div>
                @empty <p class="text-sm text-gray-500">فروشی نبوده است.</p> @endforelse
            </div>
            <div class="rounded-lg border p-3">
                <h3 class="mb-2 text-sm font-semibold">به تفکیک صندوقدار</h3>
                @forelse ($r['sales']['by_cashier'] as $row)
                    <div class="flex justify-between gap-2 text-sm"><span>{{ $row['name'] }} ({{ $n($row['orders']) }} سفارش)</span><span>{{ $this->toman($row['net']) }}</span></div>
                @empty <p class="text-sm text-gray-500">فروشی نبوده است.</p> @endforelse
            </div>
            <div class="rounded-lg border p-3">
                <h3 class="mb-2 text-sm font-semibold">به تفکیک روش پرداخت</h3>
                @forelse ($r['sales']['by_method'] as $row)
                    <div class="flex justify-between gap-2 text-sm"><span>{{ $row['method'] }}</span><span>{{ $this->toman($row['amount']) }}</span></div>
                @empty <p class="text-sm text-gray-500">پرداختی ثبت نشده است.</p> @endforelse
            </div>
            <div class="rounded-lg border p-3">
                <h3 class="mb-2 text-sm font-semibold">به تفکیک منبع فروش</h3>
                @forelse ($r['sales']['by_source'] as $row)
                    <div class="flex justify-between gap-2 text-sm"><span>{{ $row['source'] }} ({{ $n($row['orders']) }})</span><span>{{ $this->toman($row['net']) }}</span></div>
                @empty <p class="text-sm text-gray-500">فروشی نبوده است.</p> @endforelse
            </div>
        </div>

        {{-- Discounts --}}
        <div class="rounded-lg border p-3">
            <h3 class="mb-2 text-sm font-semibold">کدهای تخفیف</h3>
            @forelse ($r['discounts'] as $row)
                <div class="flex justify-between gap-2 text-sm"><span>{{ $row['code'] }} ({{ $n($row['orders']) }} سفارش، فروش پس از تخفیف {{ $this->toman($row['net']) }})</span><span>تخفیف: {{ $this->toman($row['discount']) }}</span></div>
            @empty <p class="text-sm text-gray-500">کد تخفیفی استفاده نشده است.</p> @endforelse
        </div>

        {{-- Unused & complimentary --}}
        <div class="grid gap-4 lg:grid-cols-2">
            <div class="rounded-lg border p-3">
                <h3 class="mb-1 text-sm font-semibold">فروخته‌شده ولی استفاده‌نشده و منقضی</h3>
                <p class="mb-2 text-xs text-gray-500">{{ $n($r['unused']['tickets']) }} بلیت · ارزش {{ $this->toman($r['unused']['value']) }}</p>
                @foreach ($r['unused']['by_product'] as $row)
                    <div class="flex justify-between gap-2 text-sm"><span>{{ $row['name'] }} ({{ $n($row['tickets']) }})</span><span>{{ $this->toman($row['value']) }}</span></div>
                @endforeach
            </div>
            <div class="rounded-lg border p-3">
                <h3 class="mb-1 text-sm font-semibold">بلیت‌های رایگان / اهدایی</h3>
                <p class="mb-2 text-xs text-gray-500">{{ $n($r['complimentary']['tickets']) }} بلیت · ارزش اسمی {{ $this->toman($r['complimentary']['nominal_value']) }}</p>
                @foreach ($r['complimentary']['by_reason'] as $row)
                    <div class="flex justify-between gap-2 text-sm"><span>{{ $row['reason'] }}</span><span>{{ $n($row['tickets']) }} بلیت</span></div>
                @endforeach
                @if ($r['complimentary']['by_issuer'])
                    <p class="mt-2 text-xs font-semibold">صادرکننده‌ها</p>
                    @foreach ($r['complimentary']['by_issuer'] as $row)
                        <div class="flex justify-between gap-2 text-sm"><span>{{ $row['name'] }}</span><span>{{ $n($row['tickets']) }}</span></div>
                    @endforeach
                @endif
            </div>
        </div>

        {{-- Entry by hour --}}
        <div class="rounded-lg border p-3">
            <h3 class="mb-2 text-sm font-semibold">ورود به تفکیک ساعت</h3>
            @php($max = max(1, max($r['entry_by_hour'])))
            <div class="flex h-28 items-end gap-1" dir="ltr">
                @foreach ($r['entry_by_hour'] as $hour => $count)
                    <div class="flex flex-1 flex-col items-center justify-end" title="ساعت {{ $hour }}: {{ $count }}">
                        <div class="w-full rounded-t bg-primary-500" style="height: {{ $count === 0 ? 1 : max(4, (int) round($count / $max * 100)) }}%"></div>
                        <span class="text-[10px] text-gray-500">{{ $hour }}</span>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Reconciliation --}}
        <div class="rounded-lg border p-3 {{ ($r['reconciliation']['online_without_reference'] || $r['reconciliation']['stuck_topups'] || $r['reconciliation']['failing_webhooks']) ? 'border-warning-500' : '' }}">
            <h3 class="mb-2 text-sm font-semibold">موارد نیازمند بررسی (تطبیق پرداخت و اتصال‌ها)</h3>
            <div class="space-y-1 text-sm">
                <div>شارژ کیف پول گیرکرده (بیش از یک ساعت): <strong>{{ $n($r['reconciliation']['stuck_topups']) }}</strong></div>
                <div>شارژ کیف پول ناموفق در بازه: <strong>{{ $n($r['reconciliation']['failed_topups']) }}</strong></div>
                <div>وب‌هوک‌های فعال با خطای پیاپی: <strong>{{ $n($r['reconciliation']['failing_webhooks']) }}</strong></div>
                <div>پرداخت آنلاین بدون شماره‌ی مرجع درگاه: <strong>{{ $n(count($r['reconciliation']['online_without_reference'])) }}</strong></div>
                @foreach ($r['reconciliation']['online_without_reference'] as $row)
                    <div class="text-xs text-gray-500">· {{ $row['order_number'] }} - {{ $this->toman($row['amount']) }}</div>
                @endforeach
            </div>
        </div>
    </div>
</x-filament-panels::page>
