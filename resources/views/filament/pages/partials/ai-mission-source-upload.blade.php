<div class="mr-stack" style="gap: 0.5rem;">
    <label class="mr-sub" for="source-files">افزودن فایل (txt، md، csv، docx، pdf متنی، xlsx؛ تا ۲۰ مگابایت)</label>
    <div style="display: flex; gap: 0.5rem; flex-wrap: wrap; align-items: center;">
        <input id="source-files" type="file" multiple wire:model="sourceUploads" accept=".txt,.md,.csv,.docx,.pdf,.xlsx" class="mr-input" style="flex: 1; min-width: 14rem;" />
        <select id="source-kind" class="mr-input" style="width: auto;" wire:model="sourceKind" aria-label="نوع فایل‌های تازه">
            @foreach (\App\Enums\AiMissionSourceKind::cases() as $kind)
                <option value="{{ $kind->value }}">{{ $kind->getLabel() }}</option>
            @endforeach
        </select>
        <button type="button" class="mr-btn" wire:click="uploadSources" wire:loading.attr="disabled" wire:target="uploadSources,sourceUploads">بارگذاری</button>
    </div>
    @error('sourceUploads') <span class="mr-sub" style="color: var(--mr-bad);">{{ $message }}</span> @enderror
    @error('sourceUploads.*') <span class="mr-sub" style="color: var(--mr-bad);">{{ $message }}</span> @enderror
</div>
