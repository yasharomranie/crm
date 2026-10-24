@php
    $thread = $this->getSelectedThread();
    $threads = $mission->threads()->limit(15)->get();
    $running = $thread?->status === \App\Enums\AiMissionThreadStatus::Running;
    $stuck = $running && $thread->updated_at->lt(now()->subMinutes(3));
    $hasAnswer = $thread?->messages->contains(fn ($message) => $message->kind === \App\Enums\AiMissionMessageKind::Final);
@endphp

<div class="mr-card mr-stack" style="gap: 0.8rem;" @if ($running) wire:poll.2s @endif>
    <div class="mr-row">
        <div>
            <h3 class="mr-h">💬 گفت‌وگو با تیم</h3>
            <p class="mr-sub">تیم همین است که چیده‌اید؛ درخواست یا سناریو را بنویسید تا اعضا با هم مشورت کنند و جواب را برایتان بفرستند. هر مرحله زنده اینجا دیده می‌شود.</p>
        </div>
        <button type="button" class="mr-btn" wire:click="newThread">+ گفت‌وگوی تازه</button>
    </div>

    @if ($threads->isNotEmpty())
        <div class="mr-threads">
            @foreach ($threads as $item)
                <span style="display: inline-flex; align-items: center; gap: 0.2rem;" wire:key="thread-{{ $item->id }}">
                    <button type="button" class="mr-mini" style="{{ $thread?->id === $item->id ? 'border-color: var(--mr-accent); background: var(--mr-accent-soft);' : '' }}" wire:click="openThread({{ $item->id }})">{{ \Illuminate\Support\Str::limit($item->title, 28, '…') }}</button>
                    @if ($thread?->id === $item->id && ! $running)
                        <button type="button" class="mr-mini" wire:click="deleteThread({{ $item->id }})" wire:confirm="این گفت‌وگو پاک شود؟" aria-label="حذف گفت‌وگو">✕</button>
                    @endif
                </span>
            @endforeach
        </div>
    @endif

    @if ($thread)
        <div class="mr-chat">
            @foreach ($thread->messages as $message)
                @php
                    $isUser = $message->kind === \App\Enums\AiMissionMessageKind::User;
                    $bubbleClass = match ($message->kind) {
                        \App\Enums\AiMissionMessageKind::User => 'me',
                        \App\Enums\AiMissionMessageKind::Final => 'final',
                        \App\Enums\AiMissionMessageKind::Critique => 'critique',
                        default => '',
                    };
                @endphp
                <div class="mr-msg" wire:key="msg-{{ $message->id }}">
                    @if ($isUser || ! $message->profile)
                        <span class="mr-me-dot" aria-hidden="true">شما</span>
                    @else
                        <x-ai-assistant-avatar :shape="$message->profile->avatar_shape" :color="$message->profile->avatar_color" :size="36" :animated="false" />
                    @endif
                    <div class="mr-bubble {{ $bubbleClass }}">
                        <div class="mr-bubble-meta">
                            <strong style="color: var(--mr-text);">{{ $isUser ? 'شما' : $message->profile?->displayName() }}</strong>
                            @unless ($isUser)
                                <span class="mr-pill {{ $message->kind === \App\Enums\AiMissionMessageKind::Final ? 'good' : '' }}">{{ $message->kind === \App\Enums\AiMissionMessageKind::Plan ? $message->role?->getLabel() : (($message->meta['discussion'] ?? false) ? 'بعد از گفت‌وگو' : $message->kind->getLabel()) }}</span>
                            @endunless
                        </div>
                        <div class="mr-bubble-body">{{ $message->body }}</div>
                    </div>
                </div>
            @endforeach

            @if ($running)
                <p class="mr-sub" style="display: flex; align-items: center; gap: 0.5rem;"><span class="mr-spinner" style="border-color: var(--mr-line); border-top-color: var(--mr-accent);"></span> تیم در حال گفت‌وگوست…</p>
            @endif
        </div>

        @if ($thread->last_error && $thread->status === \App\Enums\AiMissionThreadStatus::Failed)
            <div class="mr-critique">{{ $thread->last_error }} — کارِ مرحله‌های تمام‌شده نگه داشته شده و با «ادامه» از همان‌جا می‌رود.</div>
        @endif

        <div class="mr-actions">
            @if ($thread->status === \App\Enums\AiMissionThreadStatus::Failed || $stuck)
                <button type="button" class="mr-btn mr-btn-primary" wire:click="resumeChat" wire:loading.attr="disabled" wire:target="resumeChat">ادامه از همان‌جا</button>
            @endif
            <span class="mr-sub">مصرف این گفت‌وگو: {{ $fa(number_format($thread->tokens_used)) }} توکن</span>
        </div>
    @else
        <p class="mr-sub">هنوز گفت‌وگویی ندارید. مثلاً بنویسید: «برای هفتهٔ آینده یک سناریوی پر کردن اتاق‌های خالی بنویسید».</p>
    @endif

    <div class="mr-stack" style="gap: 0.5rem;">
        <label class="mr-label">
            <span class="sr-only">پیام به تیم</span>
            <textarea id="chat-message" class="mr-input" rows="3" maxlength="{{ \App\Domain\Ai\Missions\MissionChat::MAX_MESSAGE_LENGTH }}" wire:model="chatMessage" placeholder="{{ $hasAnswer ? 'ادامهٔ گفت‌وگو؛ مثلاً: بخش دوم را کوتاه‌تر کن' : 'درخواست یا سناریو را بنویسید…' }}" @disabled($running && ! $stuck)></textarea>
        </label>
        <div class="mr-row">
            @if ($hasAnswer)
                <label class="mr-sub" style="display: inline-flex; align-items: center; gap: 0.4rem;">
                    <input type="checkbox" id="chat-ask-team" wire:model="askWholeTeam" />
                    دوباره از کل تیم بپرس (مصرف بیشتر)
                </label>
            @else
                <span class="mr-sub">برای کم‌بودن مصرف، فقط همکارانی که لازم‌اند صدا زده می‌شوند.</span>
            @endif
            <button type="button" class="mr-btn mr-btn-primary" wire:click="sendChat" wire:loading.attr="disabled" wire:target="sendChat" @disabled($running && ! $stuck)>
                <span wire:loading.remove wire:target="sendChat">ارسال به تیم</span>
                <span wire:loading wire:target="sendChat" style="display: inline-flex; align-items: center; gap: 0.5rem;"><span class="mr-spinner"></span> در حال ارسال…</span>
            </button>
        </div>
    </div>
</div>
