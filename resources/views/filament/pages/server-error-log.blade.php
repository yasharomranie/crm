<x-filament-panels::page>
    @php
        $entries = $this->getEntries();
        $file = $this->logFileName();
    @endphp

    <div style="display: flex; flex-wrap: wrap; gap: .5rem; align-items: center;">
        <x-filament::button size="sm" wire:click="$refresh" icon="heroicon-m-arrow-path">تازه‌سازی</x-filament::button>
        <x-filament::button size="sm" color="{{ $this->errorsOnly ? 'primary' : 'gray' }}" wire:click="showAll(false)">فقط خطاها</x-filament::button>
        <x-filament::button size="sm" color="{{ $this->errorsOnly ? 'gray' : 'primary' }}" wire:click="showAll(true)">همهٔ پیام‌ها</x-filament::button>
        @if ($file)
            <span style="font-size: .8rem; opacity: .7; direction: ltr;">{{ $file }}</span>
        @endif
    </div>

    <p style="font-size: .85rem; opacity: .8; line-height: 2;">
        ۲۰ پیام آخر فایل گزارش برنامه (تازه‌ترین بالا). کلیدها و رمزها پنهان می‌شوند، اما این صفحه فقط برای مدیر سیستم است؛ متن خطا را برای کسی جز پشتیبان فنی نفرستید.
    </p>

    @forelse ($entries as $entry)
        <x-filament::section :compact="true">
            <details>
                <summary style="cursor: pointer; line-height: 1.9;">
                    <x-filament::badge :color="in_array($entry['level'], ['ERROR', 'CRITICAL', 'ALERT', 'EMERGENCY'], true) ? 'danger' : 'gray'">{{ $entry['level'] }}</x-filament::badge>
                    <span style="direction: ltr; unicode-bidi: embed; font-size: .8rem; opacity: .7;">{{ $entry['time'] }}</span>
                    <div style="direction: ltr; text-align: left; font-size: .85rem; word-break: break-word;">{{ $entry['headline'] }}</div>
                </summary>
                <pre style="direction: ltr; text-align: left; white-space: pre-wrap; word-break: break-word; font-size: .75rem; line-height: 1.6; margin-top: .75rem; max-height: 28rem; overflow: auto;">{{ $entry['details'] }}</pre>
            </details>
        </x-filament::section>
    @empty
        <x-filament::section>
            {{ $file ? 'پیام خطایی در فایل گزارش نیست.' : 'فایل گزارشی پیدا نشد (پوشهٔ storage/logs خالی است).' }}
        </x-filament::section>
    @endforelse
</x-filament-panels::page>
