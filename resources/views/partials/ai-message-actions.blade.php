{{-- Time, copy, Excel (when the answer has tables) and thumbs up/down under a
     message. Shared by the full page and the floating widget. --}}
<div class="flex items-center gap-1 px-1" @if (! $isUser) x-data="{ copied: false }" @endif>
    <span class="text-[11px] text-gray-400">{{ $message->created_at->format('H:i') }}</span>
    @if (! $isUser)
        <button
            type="button"
            x-on:click="copied = true; navigator.clipboard.writeText(@js($message->content ?? '')); setTimeout(() => copied = false, 1500)"
            title="کپی پاسخ"
            aria-label="کپی پاسخ"
            class="rounded p-0.5 text-gray-300 hover:text-gray-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500 dark:text-gray-600"
        >
            <x-heroicon-o-check class="h-3.5 w-3.5 text-success-600 dark:text-success-400" x-show="copied" x-cloak />
            <x-heroicon-o-clipboard class="h-3.5 w-3.5" x-show="! copied" />
        </button>
        @if (\App\Domain\Ai\MarkdownTables::hasTable($message->content))
            <button
                type="button"
                wire:click="downloadMessageTables({{ $message->id }})"
                title="دانلود جدول‌ها به Excel"
                aria-label="دانلود جدول‌ها به Excel"
                class="rounded p-0.5 text-gray-300 hover:text-gray-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500 dark:text-gray-600"
            >
                <x-heroicon-o-arrow-down-tray class="h-3.5 w-3.5" />
            </button>
        @endif
        <button type="button" wire:click="setMessageFeedback({{ $message->id }}, 'up')" title="پاسخ خوبی بود" aria-label="پاسخ خوبی بود" aria-pressed="{{ $message->feedback === 'up' ? 'true' : 'false' }}" class="rounded p-0.5 focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500 {{ $message->feedback === 'up' ? 'text-success-600 dark:text-success-400' : 'text-gray-300 hover:text-gray-500 dark:text-gray-600' }}">
            <x-heroicon-o-hand-thumb-up class="h-3.5 w-3.5" />
        </button>
        <button type="button" wire:click="setMessageFeedback({{ $message->id }}, 'down')" title="پاسخ ضعیفی بود" aria-label="پاسخ ضعیفی بود" aria-pressed="{{ $message->feedback === 'down' ? 'true' : 'false' }}" class="rounded p-0.5 focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500 {{ $message->feedback === 'down' ? 'text-danger-600 dark:text-danger-400' : 'text-gray-300 hover:text-gray-500 dark:text-gray-600' }}">
            <x-heroicon-o-hand-thumb-down class="h-3.5 w-3.5" />
        </button>
    @endif
</div>
