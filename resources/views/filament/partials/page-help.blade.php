@php
    $help = \App\Filament\Support\PageHelp::forCurrentPage();
@endphp

@if ($help)
    <div
        x-data="{
            open: false,
            storageKey: 'page-help-open:{{ $help['key'] }}',
            init() {
                try { this.open = localStorage.getItem(this.storageKey) === '1'; } catch (e) {}
            },
            toggle() {
                this.open = ! this.open;
                try { localStorage.setItem(this.storageKey, this.open ? '1' : '0'); } catch (e) {}
            },
        }"
        class="mb-4 rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10"
        data-page-help="{{ $help['key'] }}"
    >
        <button
            type="button"
            x-on:click="toggle()"
            x-bind:aria-expanded="open.toString()"
            class="flex w-full cursor-pointer items-center justify-between gap-3 rounded-xl px-4 py-3 text-start text-sm font-medium text-gray-700 transition-colors duration-150 hover:bg-gray-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600 dark:text-gray-200 dark:hover:bg-white/5"
        >
            <span class="flex items-center gap-2">
                <x-filament::icon icon="heroicon-m-light-bulb" class="h-5 w-5 text-primary-600 dark:text-primary-400" />
                راهنمای ساده‌ی این صفحه
            </span>
            <x-filament::icon icon="heroicon-m-chevron-down" class="h-4 w-4 text-gray-400 transition-transform duration-150 motion-reduce:transition-none" x-bind:class="open ? 'rotate-180' : ''" />
        </button>

        <div x-show="open" x-cloak class="space-y-3 border-t border-gray-100 px-4 py-3 dark:border-white/10">
            <p class="text-sm font-semibold text-gray-950 dark:text-white">{{ $help['title'] }}</p>
            <ol class="list-decimal space-y-1.5 ps-5 text-sm text-gray-600 dark:text-gray-300">
                @foreach ($help['steps'] as $step)
                    <li>{{ $step }}</li>
                @endforeach
            </ol>
            <a href="{{ url('/system-guide#'.$help['guide']) }}" target="_blank" rel="noopener" class="inline-flex items-center gap-1 text-sm font-medium text-primary-600 hover:underline dark:text-primary-400">
                راهنمای کامل این بخش
                <x-filament::icon icon="heroicon-m-arrow-top-right-on-square" class="h-4 w-4" />
            </a>
        </div>
    </div>
@endif
