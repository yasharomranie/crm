@php $landingLibrary = $this->getSources(); @endphp
<div class="mr-card mr-stack" style="gap: 0.6rem;" @if ($landingLibrary->contains(fn ($item) => $item->status === \App\Enums\AiMissionSourceStatus::Pending)) wire:poll.3s @endif>
    <div>
        <h2 class="mr-h">📚 کتابخانهٔ منابع</h2>
        <p class="mr-sub">فایل‌های استراتژی و گزارش‌ها اینجا نگه داشته می‌شوند و هر مأموریت هر کدام را که لازم دارد برمی‌دارد. فایل‌ها را از داخل خود مأموریت هم می‌توانید بگذارید.</p>
    </div>
    @forelse ($landingLibrary as $item)
        <div class="mr-item" wire:key="library-{{ $item->id }}">
            <div style="min-width: 0; flex: 1;">
                <div class="mr-row" style="justify-content: flex-start; gap: 0.5rem;">
                    <strong style="font-size: 0.88rem;">{{ $item->title }}</strong>
                    <span @class(['mr-pill', 'good' => $item->status === \App\Enums\AiMissionSourceStatus::Ready, 'bad' => $item->status === \App\Enums\AiMissionSourceStatus::Failed, 'accent' => $item->status === \App\Enums\AiMissionSourceStatus::Pending])>{{ $item->status->getLabel() }}</span>
                    <span class="mr-pill">{{ $item->kind->shortLabel() }}</span>
                </div>
                <span class="mr-sub">{{ strtoupper($item->extension) }} · {{ $fa(number_format($item->char_count)) }} نویسه · در {{ $fa($item->missions()->count()) }} مأموریت@if ($item->status === \App\Enums\AiMissionSourceStatus::Failed && filled($item->error)) · {{ $item->error }}@endif</span>
            </div>
            <div class="mr-row" style="gap: 0.4rem;">
                @if ($item->status === \App\Enums\AiMissionSourceStatus::Failed)
                    <button type="button" class="mr-mini" wire:click="retrySource({{ $item->id }})">تلاش دوباره</button>
                @endif
                <button type="button" class="mr-mini" wire:click="deleteSource({{ $item->id }})" wire:confirm="این فایل از کتابخانه حذف شود؟ از همهٔ مأموریت‌ها هم برداشته می‌شود." aria-label="حذف فایل">حذف</button>
            </div>
        </div>
    @empty
        <p class="mr-sub">هنوز فایلی نیست.</p>
    @endforelse
    @include('filament.pages.partials.ai-mission-source-upload')
</div>
