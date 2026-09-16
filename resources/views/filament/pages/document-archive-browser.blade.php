<x-filament-panels::page>
    <div
        x-data
        x-init="
            window.farsiwpArchiveDrop = (event, targetFolderId) => {
                const raw = event?.dataTransfer?.getData('text/plain');
                if (! raw) { return; }
                const data = JSON.parse(raw);
                if (data.type === 'folder') {
                    $wire.moveFolder(data.id, targetFolderId);
                } else {
                    $wire.moveDocument(data.id, targetFolderId);
                }
            }
        "
    >
        {{-- Breadcrumb: each crumb is also a drop target, so dragging a
            card onto an ancestor is a quick way to move it several levels
            up at once instead of one "بازگشت" tile at a time. --}}
        <nav class="mb-4 flex flex-wrap items-center gap-1 text-sm">
            <button
                type="button"
                wire:click="openFolder(null)"
                x-on:dragover.prevent
                x-on:drop.prevent="window.farsiwpArchiveDrop($event, null)"
                @class([
                    'flex items-center gap-1 rounded-md px-2 py-1 hover:bg-gray-100 dark:hover:bg-white/5',
                    'font-semibold text-gray-950 dark:text-white' => $this->folderId === null,
                    'text-gray-500 dark:text-gray-400' => $this->folderId !== null,
                ])
            >
                <x-heroicon-o-archive-box class="h-4 w-4" />
                بایگانی
            </button>

            @foreach ($this->breadcrumbs() as $crumb)
                <span class="text-gray-400 dark:text-gray-600">/</span>
                <button
                    type="button"
                    wire:click="openFolder({{ $crumb->id }})"
                    x-on:dragover.prevent
                    x-on:drop.prevent="window.farsiwpArchiveDrop($event, {{ $crumb->id }})"
                    @class([
                        'rounded-md px-2 py-1 hover:bg-gray-100 dark:hover:bg-white/5',
                        'font-semibold text-gray-950 dark:text-white' => $this->folderId === $crumb->id,
                        'text-gray-500 dark:text-gray-400' => $this->folderId !== $crumb->id,
                    ])
                >
                    {{ $crumb->name }}
                </button>
            @endforeach
        </nav>

        <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5">
            @if ($currentFolder = $this->currentFolder())
                <button
                    type="button"
                    wire:click="openFolder({{ $currentFolder->parent_id ?? 'null' }})"
                    x-data="{ over: false }"
                    x-on:dragover.prevent="over = true"
                    x-on:dragleave="over = false"
                    x-on:drop.prevent="over = false; window.farsiwpArchiveDrop($event, {{ $currentFolder->parent_id ?? 'null' }})"
                    :class="over && 'ring-2 ring-primary-500'"
                    class="flex flex-col items-center justify-center gap-2 rounded-xl border border-dashed border-gray-300 p-4 text-gray-500 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-400 dark:hover:bg-white/5"
                >
                    <x-heroicon-o-arrow-uturn-up class="h-8 w-8" />
                    <span class="text-sm">بازگشت</span>
                </button>
            @endif

            @foreach ($this->subfolders() as $folder)
                <div
                    wire:key="archive-folder-{{ $folder->id }}"
                    x-data="{ over: false }"
                    draggable="true"
                    x-on:dragstart="$event.dataTransfer.setData('text/plain', JSON.stringify({ type: 'folder', id: {{ $folder->id }} }))"
                    x-on:dragover.prevent="over = true"
                    x-on:dragleave="over = false"
                    x-on:drop.prevent="over = false; window.farsiwpArchiveDrop($event, {{ $folder->id }})"
                    wire:click="openFolder({{ $folder->id }})"
                    :class="over && 'ring-2 ring-primary-500'"
                    class="flex cursor-pointer flex-col items-center gap-2 rounded-xl border border-gray-200 p-4 text-center hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-white/5"
                >
                    <x-heroicon-o-folder class="h-10 w-10 text-warning-500" />
                    <span class="line-clamp-2 text-sm font-medium text-gray-950 dark:text-white">{{ $folder->name }}</span>
                    <span class="text-xs text-gray-500 dark:text-gray-400">
                        {{ \Illuminate\Support\Number::toPersian($folder->children_count + $folder->documents_count) }} مورد
                    </span>
                </div>
            @endforeach

            @foreach ($this->documents() as $document)
                <div
                    wire:key="archive-document-{{ $document->id }}"
                    draggable="true"
                    x-on:dragstart="$event.dataTransfer.setData('text/plain', JSON.stringify({ type: 'document', id: {{ $document->id }} }))"
                    class="flex flex-col items-center gap-2 rounded-xl border border-gray-200 p-4 text-center hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-white/5"
                >
                    <a href="{{ route('documents.download', $document) }}" target="_blank" class="flex flex-col items-center gap-2">
                        <x-heroicon-o-document-text class="h-10 w-10 text-gray-400 dark:text-gray-500" />
                        <span class="line-clamp-2 text-sm font-medium text-gray-950 dark:text-white">{{ $document->title }}</span>
                    </a>
                    <span class="text-xs text-gray-500 dark:text-gray-400">
                        {{ $document->size_kb ? \Illuminate\Support\Number::toPersian(number_format($document->size_kb)).' کیلوبایت' : '—' }}
                    </span>
                </div>
            @endforeach
        </div>

        @if ($this->subfolders()->isEmpty() && $this->documents()->isEmpty())
            <div class="py-12 text-center text-gray-500 dark:text-gray-400">
                این پوشه خالی است.
            </div>
        @endif
    </div>
</x-filament-panels::page>
