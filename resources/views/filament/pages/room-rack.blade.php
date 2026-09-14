<x-filament-panels::page>
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
        @foreach ($this->getRooms() as $room)
            @php
                $colors = [
                    'available' => 'border-success-300 bg-success-50 dark:bg-success-950/40',
                    'occupied' => 'border-info-300 bg-info-50 dark:bg-info-950/40',
                    'needs_cleaning' => 'border-warning-300 bg-warning-50 dark:bg-warning-950/40',
                    'out_of_service' => 'border-danger-300 bg-danger-50 dark:bg-danger-950/40',
                ];
                $colorClass = $colors[$room->status->value] ?? '';
                $current = $room->currentBookingCache;
                $upcoming = $room->upcomingBookingCache;
            @endphp

            <div class="rounded-xl border p-4 {{ $colorClass }}">
                <div class="flex items-start justify-between gap-2">
                    <div>
                        <div class="fi-fo-field-label-content text-base font-semibold">{{ $room->name }}</div>
                        <div class="text-sm text-gray-500 dark:text-gray-400">{{ $room->roomType?->name }}</div>
                    </div>
                    <x-filament::badge :color="$room->status->getColor()">
                        {{ $room->status->getLabel() }}
                    </x-filament::badge>
                </div>

                <div class="mt-3 text-sm">
                    @if ($current)
                        <div class="text-gray-700 dark:text-gray-300">
                            مهمان مقیم: <span class="font-medium">{{ $current->guest->name }}</span>
                        </div>
                        <div class="text-gray-500 dark:text-gray-400">
                            تا {{ $current->check_out_date->toJalali('Y/m/d') }}
                        </div>
                    @elseif ($upcoming)
                        <div class="text-gray-700 dark:text-gray-300">
                            رزرو بعدی: <span class="font-medium">{{ $upcoming->guest->name }}</span>
                        </div>
                        <div class="text-gray-500 dark:text-gray-400">
                            از {{ $upcoming->check_in_date->toJalali('Y/m/d') }}
                        </div>
                    @else
                        <div class="text-gray-400 dark:text-gray-500">رزروی ثبت نشده</div>
                    @endif
                </div>
            </div>
        @endforeach
    </div>

    @if ($this->getRooms()->isEmpty())
        <div class="text-center text-gray-500 dark:text-gray-400 py-12">
            هنوز هیچ واحد فعالی ثبت نشده است.
        </div>
    @endif
</x-filament-panels::page>
