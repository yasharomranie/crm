<x-filament-panels::page>
    @php
        $calendar = $this->calendar();
        $number = fn ($value) => \Illuminate\Support\Number::toPersian((string) $value);
        $canUpdate = auth()->user()?->can('bookings.update');
        $canCreate = \App\Filament\Resources\Bookings\BookingResource::canCreate();
        $palette = [
            'pending' => ['#fde68a', '#78350f'],
            'confirmed' => ['#bfdbfe', '#1e3a8a'],
            'checked_in' => ['#bbf7d0', '#14532d'],
            'checked_out' => ['#e5e7eb', '#374151'],
        ];
    @endphp

    <div dir="rtl" class="space-y-4">
        <div class="flex flex-wrap items-center gap-3">
            <x-filament::button size="sm" color="gray" wire:click="shift(-1)">قبلی</x-filament::button>
            <x-filament::button size="sm" color="gray" wire:click="goToToday">امروز</x-filament::button>
            <x-filament::button size="sm" color="gray" wire:click="shift(1)">بعدی</x-filament::button>

            <label for="calendar-days" class="text-sm text-gray-600 dark:text-gray-300">بازه</label>
            <select id="calendar-days" wire:model.live="days" class="rounded-lg border-gray-300 text-sm dark:border-white/10 dark:bg-gray-900">
                <option value="7">۷ روز</option>
                <option value="14">۱۴ روز</option>
                <option value="30">۳۰ روز</option>
                <option value="60">۶۰ روز</option>
            </select>

            <label for="calendar-type" class="text-sm text-gray-600 dark:text-gray-300">نوع</label>
            <select id="calendar-type" wire:model.live="roomTypeId" class="rounded-lg border-gray-300 text-sm dark:border-white/10 dark:bg-gray-900">
                <option value="">همه</option>
                @foreach ($this->roomTypes() as $id => $name)
                    <option value="{{ $id }}">{{ $name }}</option>
                @endforeach
            </select>
        </div>

        <div class="flex flex-wrap gap-3 text-xs text-gray-600 dark:text-gray-300">
            @foreach (['pending' => 'در انتظار تأیید', 'confirmed' => 'قطعی', 'checked_in' => 'مقیم', 'checked_out' => 'خارج‌شده'] as $key => $label)
                <span class="inline-flex items-center gap-1"><span style="display:inline-block;width:.8rem;height:.8rem;border-radius:.2rem;background:{{ $palette[$key][0] }}"></span>{{ $label }}</span>
            @endforeach
            <span>ستون پررنگ‌تر = امروز · خانه‌ی خالی را بزنید تا رزرو تازه با همان اتاق و تاریخ شروع شود.</span>
        </div>

        <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-white/10">
            <table style="border-collapse: collapse; min-width: 100%; table-layout: fixed;" class="text-xs">
                <thead>
                    <tr>
                        <th style="width: 9rem; min-width: 9rem; position: sticky; right: 0; z-index: 2;" class="bg-gray-50 p-2 text-right dark:bg-gray-800">اتاق</th>
                        @foreach ($calendar['days'] as $day)
                            <th style="width: 2.6rem; min-width: 2.6rem; text-align: center;" class="p-1 {{ $day['is_today'] ? 'bg-primary-100 dark:bg-primary-900' : 'bg-gray-50 dark:bg-gray-800' }}">
                                <div>{{ $number($day['date']->toJalali('d')) }}</div>
                                <div class="font-normal text-gray-500">{{ $number($day['date']->toJalali('m')) }}</div>
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @forelse ($calendar['rows'] as $row)
                        <tr wire:key="room-{{ $row['room']->id }}" style="border-top: 1px solid rgba(128,128,128,.25);">
                            <th style="position: sticky; right: 0; z-index: 1;" class="bg-white p-2 text-right font-medium dark:bg-gray-900">
                                {{ $row['room']->name }}
                                <div class="font-normal text-gray-500">{{ $row['room']->roomType->name }}</div>
                            </th>
                            @foreach ($row['cells'] as $cell)
                                @if ($cell['type'] === 'free')
                                    <td style="height: 2.6rem; border-right: 1px solid rgba(128,128,128,.15);" class="{{ $cell['date']->isToday() ? 'bg-primary-50 dark:bg-primary-950' : '' }}">
                                        @if ($canCreate)
                                            <a href="{{ \App\Filament\Resources\Bookings\BookingResource::getUrl('create', ['room_id' => $row['room']->id, 'check_in_date' => $cell['date']->toDateString(), 'check_out_date' => $cell['date']->copy()->addDay()->toDateString()]) }}"
                                               style="display:block;height:100%;width:100%" title="رزرو تازه از {{ $cell['date']->toJalali('Y/m/d') }}" aria-label="رزرو تازه {{ $row['room']->name }} {{ $cell['date']->toJalali('Y/m/d') }}"></a>
                                        @endif
                                    </td>
                                @else
                                    @php
                                        $booking = $cell['booking'];
                                        [$background, $color] = $palette[$booking->status->value];
                                    @endphp
                                    <td colspan="{{ $cell['span'] }}" style="padding: 2px;">
                                        <{{ $canUpdate ? 'a' : 'div' }}
                                            @if ($canUpdate) href="{{ \App\Filament\Resources\Bookings\BookingResource::getUrl('edit', ['record' => $booking]) }}" @endif
                                            style="display:block;padding:.35rem .5rem;background:{{ $background }};color:{{ $color }};overflow:hidden;white-space:nowrap;text-overflow:ellipsis;border-radius:{{ $cell['ends_after'] ? '0' : '.4rem' }} {{ $cell['starts_before'] ? '0' : '.4rem' }} {{ $cell['starts_before'] ? '0' : '.4rem' }} {{ $cell['ends_after'] ? '0' : '.4rem' }}"
                                            title="{{ $booking->guest->name }} · {{ $booking->reference_code }} · {{ $booking->check_in_date->toJalali('Y/m/d') }} تا {{ $booking->check_out_date->toJalali('Y/m/d') }}">
                                            {{ $booking->guest->name }}
                                        </{{ $canUpdate ? 'a' : 'div' }}>
                                    </td>
                                @endif
                            @endforeach
                        </tr>
                    @empty
                        <tr><td colspan="{{ count($calendar['days']) + 1 }}" class="p-4 text-gray-500">اتاق فعالی برای نمایش نیست.</td></tr>
                    @endforelse
                </tbody>
                @if ($calendar['rooms'] > 0)
                    <tfoot>
                        <tr style="border-top: 2px solid rgba(128,128,128,.4);">
                            <th style="position: sticky; right: 0;" class="bg-gray-50 p-2 text-right dark:bg-gray-800">پر / کل</th>
                            @foreach ($calendar['days'] as $day)
                                <td style="text-align:center" class="p-1 {{ $day['occupied'] >= $calendar['rooms'] ? 'font-bold text-danger-600' : '' }}">{{ $number($day['occupied']) }}/{{ $number($calendar['rooms']) }}</td>
                            @endforeach
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </div>
</x-filament-panels::page>
