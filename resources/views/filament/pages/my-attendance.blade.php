<x-filament-panels::page>
    @php
        $today = $this->getToday();
        $summary = $this->getMonthlySummary();
        $history = $this->getHistory();

        $toHoursLabel = function (int $minutes): string {
            $hours = intdiv($minutes, 60);
            $mins = $minutes % 60;

            return \Illuminate\Support\Number::toPersian($hours > 0 ? "{$hours} ساعت و {$mins} دقیقه" : "{$mins} دقیقه");
        };
    @endphp

    <div class="space-y-6" x-data="attendanceCard()">
        {{-- Today's status + check-in/out action --}}
        <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-white/10 dark:bg-gray-900">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <p class="text-sm text-gray-500 dark:text-gray-400">امروز</p>
                    <p class="text-lg font-semibold text-gray-950 dark:text-white">{{ \Illuminate\Support\Number::toPersian(now()->translatedFormat('l، j F Y')) }}</p>
                    @if ($today?->check_in_at)
                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">
                            ورود: <span class="font-medium">{{ \Illuminate\Support\Number::toPersian($today->check_in_at->format('H:i')) }}</span>
                            @if ($today->check_out_at)
                                · خروج: <span class="font-medium">{{ \Illuminate\Support\Number::toPersian($today->check_out_at->format('H:i')) }}</span>
                            @endif
                        </p>
                    @else
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">هنوز ورود خود را ثبت نکرده‌اید.</p>
                    @endif

                    @if ($this->shiftHasGeofence())
                        <p class="mt-2 flex items-center gap-1 text-xs text-warning-600 dark:text-warning-400">
                            <x-heroicon-o-map-pin class="h-4 w-4" />
                            این شیفت نیازمند تأیید موقعیت مکانی است.
                        </p>
                    @endif

                    <p x-show="errorMessage" x-cloak x-text="errorMessage" class="mt-2 text-xs text-danger-600 dark:text-danger-400"></p>
                </div>

                <div class="flex items-center gap-3">
                    @if ($today?->status)
                        <span @class([
                            'inline-flex items-center gap-1 rounded-full px-3 py-1 text-sm font-medium',
                            'bg-success-100 text-success-700 dark:bg-success-500/10 dark:text-success-400' => $today->status === \App\Enums\AttendanceStatus::OnTime,
                            'bg-warning-100 text-warning-700 dark:bg-warning-500/10 dark:text-warning-400' => $today->status === \App\Enums\AttendanceStatus::Late,
                            'bg-info-100 text-info-700 dark:bg-info-500/10 dark:text-info-400' => $today->status === \App\Enums\AttendanceStatus::Incomplete,
                            'bg-danger-100 text-danger-700 dark:bg-danger-500/10 dark:text-danger-400' => $today->status === \App\Enums\AttendanceStatus::Absent,
                        ])>
                            {{ $today->status->getLabel() }}
                        </span>
                    @endif

                    @if (! $today?->check_in_at)
                        <button
                            type="button"
                            x-on:click="submit('checkIn')"
                            x-bind:disabled="isLocating"
                            class="flex items-center gap-2 rounded-lg bg-success-600 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-success-500 disabled:cursor-not-allowed disabled:opacity-60"
                        >
                            <x-heroicon-o-finger-print class="h-5 w-5" x-show="! isLocating" />
                            <svg x-show="isLocating" x-cloak class="h-5 w-5 animate-spin" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path></svg>
                            <span x-text="isLocating ? 'در حال دریافت موقعیت مکانی...' : 'ثبت ورود'"></span>
                        </button>
                    @elseif (! $today->check_out_at)
                        <button
                            type="button"
                            x-on:click="submit('checkOut')"
                            x-bind:disabled="isLocating"
                            class="flex items-center gap-2 rounded-lg bg-danger-600 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-danger-500 disabled:cursor-not-allowed disabled:opacity-60"
                        >
                            <x-heroicon-o-finger-print class="h-5 w-5" x-show="! isLocating" />
                            <svg x-show="isLocating" x-cloak class="h-5 w-5 animate-spin" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path></svg>
                            <span x-text="isLocating ? 'در حال دریافت موقعیت مکانی...' : 'ثبت خروج'"></span>
                        </button>
                    @else
                        <span class="flex items-center gap-2 rounded-lg bg-gray-100 px-5 py-2.5 text-sm font-medium text-gray-500 dark:bg-white/5 dark:text-gray-400">
                            <x-heroicon-o-check-circle class="h-5 w-5" />
                            امروز کامل ثبت شد
                        </span>
                    @endif
                </div>
            </div>
        </div>

        {{-- Monthly stat cards --}}
        <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
            <div class="rounded-xl border border-gray-200 bg-white p-4 text-center shadow-sm dark:border-white/10 dark:bg-gray-900">
                <p class="text-2xl font-bold text-gray-950 dark:text-white">{{ $toHoursLabel($summary['worked_minutes']) }}</p>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">کارکرد این ماه</p>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white p-4 text-center shadow-sm dark:border-white/10 dark:bg-gray-900">
                <p class="text-2xl font-bold text-warning-600 dark:text-warning-400">{{ \Illuminate\Support\Number::toPersian($summary['late_days']) }}</p>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">روز با تأخیر</p>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white p-4 text-center shadow-sm dark:border-white/10 dark:bg-gray-900">
                <p class="text-2xl font-bold text-danger-600 dark:text-danger-400">{{ \Illuminate\Support\Number::toPersian($summary['absent_days']) }}</p>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">روز غیبت</p>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white p-4 text-center shadow-sm dark:border-white/10 dark:bg-gray-900">
                <p class="text-2xl font-bold text-gray-950 dark:text-white">{{ \Illuminate\Support\Number::toPersian($summary['late_minutes']) }}</p>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">مجموع دقایق تأخیر</p>
            </div>
        </div>

        {{-- History table --}}
        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-gray-900">
            <div class="border-b border-gray-200 px-4 py-3 dark:border-white/10">
                <h3 class="text-sm font-semibold text-gray-950 dark:text-white">سوابق این ماه</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-xs text-gray-500 dark:bg-white/5 dark:text-gray-400">
                        <tr>
                            <th class="px-4 py-2 text-start">تاریخ</th>
                            <th class="px-4 py-2 text-start">ورود</th>
                            <th class="px-4 py-2 text-start">خروج</th>
                            <th class="px-4 py-2 text-start">وضعیت</th>
                            <th class="px-4 py-2 text-start">کارکرد</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                        @forelse ($history as $record)
                            <tr>
                                <td class="px-4 py-2.5 text-gray-700 dark:text-gray-300">{{ $record->date->toJalali('Y/m/d') }}</td>
                                <td class="px-4 py-2.5 text-gray-700 dark:text-gray-300">{{ $record->check_in_at ? \Illuminate\Support\Number::toPersian($record->check_in_at->format('H:i')) : '—' }}</td>
                                <td class="px-4 py-2.5 text-gray-700 dark:text-gray-300">{{ $record->check_out_at ? \Illuminate\Support\Number::toPersian($record->check_out_at->format('H:i')) : '—' }}</td>
                                <td class="px-4 py-2.5">
                                    <span @class([
                                        'rounded-full px-2 py-0.5 text-xs font-medium',
                                        'bg-success-100 text-success-700 dark:bg-success-500/10 dark:text-success-400' => $record->status === \App\Enums\AttendanceStatus::OnTime,
                                        'bg-warning-100 text-warning-700 dark:bg-warning-500/10 dark:text-warning-400' => $record->status === \App\Enums\AttendanceStatus::Late,
                                        'bg-info-100 text-info-700 dark:bg-info-500/10 dark:text-info-400' => $record->status === \App\Enums\AttendanceStatus::Incomplete,
                                        'bg-danger-100 text-danger-700 dark:bg-danger-500/10 dark:text-danger-400' => $record->status === \App\Enums\AttendanceStatus::Absent,
                                    ])>
                                        {{ $record->status->getLabel() }}
                                    </span>
                                </td>
                                <td class="px-4 py-2.5 text-gray-700 dark:text-gray-300">{{ $toHoursLabel($record->worked_minutes) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-8 text-center text-gray-400">
                                    هنوز رکوردی برای این ماه ثبت نشده است.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    @script
    <script>
        Alpine.data('attendanceCard', () => ({
            isLocating: false,
            errorMessage: null,

            /**
             * Reads the browser's Geolocation API (the only location signal
             * a web page can access - there's no way to reach GPS/Wi-Fi
             * hardware more directly than this) and a best-effort device
             * fingerprint, then calls the matching Livewire action. If
             * location is denied/unavailable, we still submit with null
             * coordinates - the server decides whether that's fatal (only
             * shifts with a configured geofence require it).
             */
            submit(action) {
                this.errorMessage = null;
                this.isLocating = true;

                const finish = (latitude, longitude) => {
                    this.fingerprint().then((fingerprint) => {
                        this.isLocating = false;
                        this.$wire[action](latitude, longitude, fingerprint);
                    });
                };

                if (! navigator.geolocation) {
                    finish(null, null);

                    return;
                }

                navigator.geolocation.getCurrentPosition(
                    (position) => finish(position.coords.latitude, position.coords.longitude),
                    () => {
                        this.errorMessage = 'دسترسی به موقعیت مکانی رد شد یا در دسترس نیست؛ اگر شیفت شما نیازمند آن باشد، ثبت رد خواهد شد.';
                        finish(null, null);
                    },
                    { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 },
                );
            },

            async fingerprint() {
                const raw = [
                    navigator.userAgent,
                    navigator.language,
                    screen.width + 'x' + screen.height,
                    Intl.DateTimeFormat().resolvedOptions().timeZone,
                ].join('|');

                if (window.crypto?.subtle) {
                    const digest = await crypto.subtle.digest('SHA-256', new TextEncoder().encode(raw));

                    return Array.from(new Uint8Array(digest))
                        .map((byte) => byte.toString(16).padStart(2, '0'))
                        .join('')
                        .slice(0, 32);
                }

                // Fallback for non-secure contexts where SubtleCrypto is
                // unavailable: a simple, non-cryptographic string hash is
                // still good enough to notice "this looks like a different
                // device than usual".
                let hash = 0;
                for (let i = 0; i < raw.length; i++) {
                    hash = (hash << 5) - hash + raw.charCodeAt(i);
                    hash |= 0;
                }

                return String(hash);
            },
        }));
    </script>
    @endscript
</x-filament-panels::page>
