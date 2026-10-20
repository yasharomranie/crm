<x-filament-panels::page>
    {{-- Shown only when this page loaded from the service worker's cached
        snapshot (public/sw.js's OFFLINE_CACHEABLE_PATHS) rather than a
        live request - the room statuses/bookings below are then whatever
        they were on the last successful visit, not necessarily current. --}}
    <div id="room-rack-stale-banner" hidden class="mb-4 rounded-lg border border-warning-300 bg-warning-50 px-4 py-2 text-sm text-warning-800 dark:border-warning-800 dark:bg-warning-950/40 dark:text-warning-200">
        اتصال اینترنت برقرار نیست - این نسخه از رک اتاق ذخیره‌شده و ممکنه به‌روز نباشه. آخرین بروزرسانی: <span data-last-synced>نامشخص</span>.
    </div>

    <script>
        (function () {
            var STORAGE_KEY = 'room-rack-last-synced-at';

            if (navigator.onLine) {
                try {
                    localStorage.setItem(STORAGE_KEY, new Date().toISOString());
                } catch (e) {}

                return;
            }

            var lastSynced = null;
            try {
                lastSynced = localStorage.getItem(STORAGE_KEY);
            } catch (e) {}

            var banner = document.getElementById('room-rack-stale-banner');
            if (!banner) {
                return;
            }

            if (lastSynced) {
                banner.querySelector('[data-last-synced]').textContent = new Date(lastSynced).toLocaleString('fa-IR');
            }

            banner.hidden = false;
        })();
    </script>

    @php
        $number = fn ($value) => \Illuminate\Support\Number::toPersian($value);
        $counts = $this->getCounts();
        $groups = $this->getGroups();
        $stripes = [
            'available' => 'border-s-success-500',
            'occupied' => 'border-s-info-500',
            'needs_cleaning' => 'border-s-warning-500',
            'out_of_service' => 'border-s-danger-500',
        ];
        $bars = [
            'available' => 'bg-success-500',
            'occupied' => 'bg-info-500',
            'needs_cleaning' => 'bg-warning-500',
            'out_of_service' => 'bg-danger-500',
        ];
        $chips = [
            ['key' => 'all', 'label' => 'همه'],
            ['key' => 'available', 'label' => 'خالی و آماده'],
            ['key' => 'occupied', 'label' => 'مقیم'],
            ['key' => 'needs_cleaning', 'label' => 'نیازمند نظافت'],
            ['key' => 'out_of_service', 'label' => 'خارج از سرویس'],
            ['key' => 'arriving', 'label' => 'ورود امروز'],
            ['key' => 'departing', 'label' => 'خروج امروز'],
        ];
        $canUpdate = auth()->user()?->can('update', new \App\Models\Room) ?? false;
    @endphp

    <div wire:poll.30s class="space-y-5">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="relative w-full sm:w-72">
                <x-filament::icon icon="heroicon-m-magnifying-glass" class="pointer-events-none absolute start-3 top-1/2 h-5 w-5 -translate-y-1/2 text-gray-400" />
                <input
                    type="search"
                    wire:model.live.debounce.300ms="search"
                    placeholder="جست‌وجوی واحد یا مهمان"
                    aria-label="جست‌وجوی واحد یا مهمان"
                    class="block w-full rounded-lg border-0 bg-white py-2 ps-10 pe-3 text-sm text-gray-950 shadow-sm ring-1 ring-gray-950/10 placeholder:text-gray-400 focus:ring-2 focus:ring-primary-600 dark:bg-white/5 dark:text-white dark:ring-white/20"
                >
            </div>

            <div class="flex items-center gap-2 text-xs text-gray-500 dark:text-gray-400" role="status">
                <span class="relative flex h-2 w-2">
                    <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-success-500 opacity-60 motion-reduce:animate-none"></span>
                    <span class="relative inline-flex h-2 w-2 rounded-full bg-success-500"></span>
                </span>
                زنده · هر ۳۰ ثانیه تازه می‌شود · آخرین به‌روزرسانی {{ $number(now()->toJalali()->format('H:i')) }}
            </div>
        </div>

        <div class="flex flex-wrap gap-2" role="group" aria-label="فیلتر وضعیت واحدها">
            @foreach ($chips as $chip)
                @php
                    $active = $this->filter === $chip['key'];
                @endphp
                <button
                    type="button"
                    wire:click="setFilter('{{ $chip['key'] }}')"
                    aria-pressed="{{ $active ? 'true' : 'false' }}"
                    wire:key="chip-{{ $chip['key'] }}"
                    @class([
                        'inline-flex min-h-9 cursor-pointer items-center gap-2 rounded-full px-3.5 py-1.5 text-sm font-medium transition-colors duration-150 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600',
                        'bg-primary-600 text-white shadow-sm' => $active,
                        'bg-white text-gray-700 ring-1 ring-gray-950/10 hover:bg-gray-50 dark:bg-white/5 dark:text-gray-200 dark:ring-white/15 dark:hover:bg-white/10' => ! $active,
                    ])
                >
                    {{ $chip['label'] }}
                    <span @class([
                        'rounded-full px-2 text-xs tabular-nums',
                        'bg-white/20' => $active,
                        'bg-gray-100 text-gray-600 dark:bg-white/10 dark:text-gray-300' => ! $active,
                    ])>{{ $number($counts[$chip['key']] ?? 0) }}</span>
                </button>
            @endforeach
        </div>

        @forelse ($groups as $typeName => $tiles)
            <section class="space-y-3" wire:key="group-{{ $typeName }}">
                <h3 class="flex items-center gap-2 text-sm font-semibold text-gray-700 dark:text-gray-200">
                    {{ $typeName }}
                    <span class="rounded-full bg-gray-100 px-2 text-xs font-normal tabular-nums text-gray-600 dark:bg-white/10 dark:text-gray-300">{{ $number($tiles->count()) }}</span>
                </h3>

                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-4">
                    @foreach ($tiles as $tile)
                        @php
                            $room = $tile['room'];
                            $status = $tile['status'];
                            $stay = $tile['stay'];
                            $next = $tile['next'];
                            $block = $tile['block'];
                        @endphp

                        <article
                            wire:key="room-{{ $room->id }}"
                            class="flex flex-col gap-3 rounded-xl border-s-4 bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 {{ $stripes[$status->value] ?? '' }}"
                        >
                            <header class="flex items-start justify-between gap-2">
                                <div>
                                    <div class="text-base font-semibold text-gray-950 dark:text-white">{{ $room->name }}</div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400">{{ $room->roomType?->name }}</div>
                                </div>
                                <x-filament::badge :color="$status->getColor()">
                                    {{ $status->getLabel() }}
                                </x-filament::badge>
                            </header>

                            <div class="min-h-14 space-y-1.5 text-sm">
                                @if ($stay)
                                    <div class="flex items-center gap-1.5 font-medium text-gray-950 dark:text-white">
                                        <x-filament::icon icon="heroicon-m-user" class="h-4 w-4 shrink-0 text-gray-400" />
                                        {{ $stay->guest->name }}
                                    </div>
                                    <div class="flex items-center justify-between text-xs text-gray-500 dark:text-gray-400">
                                        <span>شب {{ $number($tile['night_index']) }} از {{ $number($tile['nights']) }}</span>
                                        <span>خروج {{ $stay->check_out_date->toJalali('m/d') }}</span>
                                    </div>
                                    <div class="h-1.5 overflow-hidden rounded-full bg-gray-100 dark:bg-white/10" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $tile['progress'] }}" aria-label="پیشرفت اقامت">
                                        <div class="h-full rounded-full {{ $bars[$status->value] ?? 'bg-info-500' }}" style="width: {{ $tile['progress'] }}%"></div>
                                    </div>
                                @elseif ($block)
                                    <div class="flex items-start gap-1.5 text-danger-700 dark:text-danger-400">
                                        <x-filament::icon icon="heroicon-m-wrench-screwdriver" class="mt-0.5 h-4 w-4 shrink-0" />
                                        <span>{{ $block->reason }}</span>
                                    </div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400">تا {{ $block->ends_on->toJalali('Y/m/d') }}</div>
                                @elseif ($next)
                                    <div class="flex items-center gap-1.5 font-medium text-gray-950 dark:text-white">
                                        <x-filament::icon icon="heroicon-m-calendar-days" class="h-4 w-4 shrink-0 text-gray-400" />
                                        {{ $next->guest->name }}
                                    </div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400">رزرو بعدی از {{ $next->check_in_date->toJalali('Y/m/d') }}</div>
                                @else
                                    <div class="text-gray-400 dark:text-gray-500">رزروی ثبت نشده</div>
                                @endif
                            </div>

                            @if ($tile['arrives_today'] || $tile['departs_today'] || $tile['overdue'] || $tile['balance'] > 0 || $tile['alert'] || $tile['blocked_guest'])
                                <footer class="flex flex-wrap gap-1.5">
                                    @if ($tile['overdue'])
                                        <x-filament::badge color="danger" icon="heroicon-m-clock">از ساعت خروج گذشته</x-filament::badge>
                                    @elseif ($tile['departs_today'])
                                        <x-filament::badge color="warning" icon="heroicon-m-arrow-up-tray">خروج امروز</x-filament::badge>
                                    @endif
                                    @if ($tile['arrives_today'])
                                        <x-filament::badge color="info" icon="heroicon-m-arrow-down-tray">ورود امروز</x-filament::badge>
                                    @endif
                                    @if ($tile['balance'] > 0)
                                        <x-filament::badge color="warning" icon="heroicon-m-banknotes">مانده {{ $number(number_format($tile['balance'])) }}</x-filament::badge>
                                    @endif
                                    @if ($tile['blocked_guest'])
                                        <x-filament::badge color="danger" icon="heroicon-m-no-symbol">مهمان مسدود</x-filament::badge>
                                    @endif
                                    @if ($tile['alert'])
                                        <x-filament::badge color="gray" icon="heroicon-m-exclamation-triangle" :title="$tile['alert']">{{ \Illuminate\Support\Str::limit($tile['alert'], 28) }}</x-filament::badge>
                                    @endif
                                </footer>
                            @endif

                            @if ($canUpdate && $room->status === \App\Enums\RoomStatus::NeedsCleaning)
                                <x-filament::button size="sm" color="success" icon="heroicon-m-sparkles" wire:click="markReady({{ $room->id }})" wire:loading.attr="disabled" class="w-full">
                                    نظافت انجام شد
                                </x-filament::button>
                            @endif
                        </article>
                    @endforeach
                </div>
            </section>
        @empty
            <div class="rounded-xl bg-white py-12 text-center text-sm text-gray-500 ring-1 ring-gray-950/5 dark:bg-gray-900 dark:text-gray-400 dark:ring-white/10">
                @if ($counts['all'] === 0)
                    هنوز هیچ واحد فعالی ثبت نشده است.
                @else
                    واحدی با این فیلتر پیدا نشد.
                @endif
            </div>
        @endforelse
    </div>
</x-filament-panels::page>
