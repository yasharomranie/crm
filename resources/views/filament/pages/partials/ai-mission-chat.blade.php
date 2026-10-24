@php
    $thread = $this->getSelectedThread();
    $threads = $mission->threads()->limit(15)->get();
    $running = $thread?->status === \App\Enums\AiMissionThreadStatus::Running;
    $stuck = $running && $thread->updated_at->lt(now()->subMinutes(3));
    $hasAnswer = $thread?->messages->contains(fn ($message) => $message->kind === \App\Enums\AiMissionMessageKind::Final);
    $working = $thread ? app(\App\Domain\Ai\Missions\MissionChat::class)->workingMember($thread, $mission) : null;
    $specialistRoles = $mission->roles->filter(fn ($role) => $role->role === \App\Enums\AiMissionRoleType::Specialist && $role->profile);
    $stats = app(\App\Domain\Ai\Missions\MissionChatStats::class)->forMission($mission);
    $depthOptions = [
        'fast' => ['سریع', 'یک متخصص، بدون بحث؛ کمترین مصرف'],
        'normal' => ['عادی', 'یک دور بحث بین همکاران'],
        'deep' => ['عمیق', 'اگر منتقد ایراد بگیرد، دور دوم بحث'],
    ];
@endphp

<div class="mr-card mr-stack" style="gap: 0.8rem;" @if ($running) wire:poll.2s @endif>
    <div class="mr-row">
        <div>
            <h3 class="mr-h">💬 گفت‌وگو با تیم</h3>
            <p class="mr-sub">تیم همین است که چیده‌اید؛ درخواست یا سناریو را بنویسید تا اعضا با هم مشورت کنند و جواب را برایتان بفرستند. هر مرحله زنده اینجا دیده می‌شود.</p>
        </div>
        <button type="button" class="mr-btn" wire:click="newThread">+ گفت‌وگوی تازه</button>
    </div>

    @if ($mission->pins->isNotEmpty())
        <details class="mr-note" style="display: block;">
            <summary style="cursor: pointer; font-weight: 600;">📌 تصمیم‌های ذخیره‌شدهٔ تیم ({{ $fa($mission->pins->count()) }}) — در همهٔ گفت‌وگوها به تیم داده می‌شود</summary>
            <div class="mr-stack" style="gap: 0.4rem; margin-top: 0.5rem;">
                @foreach ($mission->pins as $pin)
                    <div class="mr-row" style="align-items: flex-start; flex-wrap: nowrap;" wire:key="pin-{{ $pin->id }}">
                        <span class="mr-sub" style="min-width: 0; overflow-wrap: anywhere; white-space: pre-wrap;">{{ \Illuminate\Support\Str::limit($pin->text, 220, '…') }}</span>
                        <button type="button" class="mr-mini" wire:click="removePin({{ $pin->id }})" aria-label="حذف تصمیم">✕</button>
                    </div>
                @endforeach
            </div>
        </details>
    @endif

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
                    $rateable = in_array($message->kind, [\App\Enums\AiMissionMessageKind::Answer, \App\Enums\AiMissionMessageKind::Final], true);
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
                            @if ($message->tokens > 0)
                                <span>{{ $fa(number_format($message->tokens)) }} توکن</span>
                            @endif
                        </div>
                        <div class="mr-bubble-body">{{ $message->body }}</div>
                        @if (! empty($message->meta['sources']))
                            <div class="mr-actions">
                                <span class="mr-sub">منابع:</span>
                                @foreach ($message->meta['sources'] as $sourceTitle)
                                    <span class="mr-pill" title="فایلی که از آن استفاده شد">📄 {{ \Illuminate\Support\Str::limit($sourceTitle, 40, '…') }}</span>
                                @endforeach
                            </div>
                        @endif
                        @if (($message->meta['actions'] ?? 0) > 0)
                            <span class="mr-pill warn">✅ {{ $fa($message->meta['actions']) }} اقدام برای تأیید شما ساخته شد (کارت «اقدام‌های پیشنهادی تیم»)</span>
                        @endif
                        @if ($rateable)
                            <div class="mr-actions">
                                <button type="button" class="mr-mini" style="{{ $message->rating === 1 ? 'border-color: var(--mr-good); background: var(--mr-good-soft);' : '' }}" wire:click="rateMessage({{ $message->id }}, 1)" aria-label="جواب خوب بود" aria-pressed="{{ $message->rating === 1 ? 'true' : 'false' }}">👍</button>
                                <button type="button" class="mr-mini" style="{{ $message->rating === -1 ? 'border-color: var(--mr-bad); background: var(--mr-bad-soft);' : '' }}" wire:click="rateMessage({{ $message->id }}, -1)" aria-label="جواب خوب نبود" aria-pressed="{{ $message->rating === -1 ? 'true' : 'false' }}">👎</button>
                                @if ($message->kind === \App\Enums\AiMissionMessageKind::Final)
                                    <button type="button" class="mr-mini" wire:click="pinMessage({{ $message->id }})">📌 ذخیره به‌عنوان تصمیم</button>
                                    <button type="button" class="mr-mini" x-data="{ done: false }" x-on:click="navigator.clipboard.writeText($el.dataset.text).then(() => { done = true; setTimeout(() => done = false, 1500) })" data-text="{{ $message->body }}"><span x-show="! done">📋 کپی</span><span x-show="done" x-cloak>کپی شد ✓</span></button>
                                    <button type="button" class="mr-mini" wire:click="downloadMessage({{ $message->id }})" wire:loading.attr="disabled" wire:target="downloadMessage({{ $message->id }})">⬇️ دانلود (Markdown)</button>
                                    <button type="button" class="mr-mini" wire:click="extractTasks({{ $message->id }})" wire:loading.attr="disabled" wire:target="extractTasks({{ $message->id }})">
                                        <span wire:loading.remove wire:target="extractTasks({{ $message->id }})">✅ تبدیل به لیست کارها</span>
                                        <span wire:loading wire:target="extractTasks({{ $message->id }})">در حال استخراج…</span>
                                    </button>
                                @endif
                            </div>
                        @endif
                    </div>
                </div>
            @endforeach

            @if ($running)
                <p class="mr-sub" style="display: flex; align-items: center; gap: 0.5rem;"><span class="mr-spinner" style="border-color: var(--mr-line); border-top-color: var(--mr-accent);"></span> {{ $working?->profile ? '«'.$working->profile->displayName().'» در حال نوشتن است…' : 'تیم در حال گفت‌وگوست…' }}</p>
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

        @if ($specialistRoles->isNotEmpty())
            <div class="mr-threads" aria-label="پرسیدن مستقیم از یک عضو">
                <span class="mr-sub">مستقیم از یک نفر بپرسید:</span>
                @foreach ($specialistRoles as $role)
                    <button type="button" class="mr-mini" wire:click="addMention({{ $role->ai_assistant_profile_id }})" wire:key="mention-{{ $role->id }}">{{ '@'.$role->profile->displayName() }}</button>
                @endforeach
            </div>
        @endif

        <div class="mr-threads" role="radiogroup" aria-label="عمق گفت‌وگو">
            @foreach ($depthOptions as $value => [$label, $hint])
                <label class="mr-mini" style="cursor: pointer; {{ $chatDepth === $value ? 'border-color: var(--mr-accent); background: var(--mr-accent-soft);' : '' }}" title="{{ $hint }}">
                    <input type="radio" id="chat-depth-{{ $value }}" name="chat-depth" value="{{ $value }}" wire:model.live="chatDepth" style="margin-inline-end: 0.3rem;" />
                    {{ $label }}
                </label>
            @endforeach
            <span class="mr-sub">{{ $depthOptions[$chatDepth][1] ?? '' }}</span>
        </div>

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

    @if ($stats->isNotEmpty())
        <details>
            <summary style="cursor: pointer; font-weight: 600; font-size: 0.85rem;">📊 سلامت تیم: هر عضو چقدر خرج کرده و چقدر مفید بوده</summary>
            <div style="overflow-x: auto; margin-top: 0.5rem;">
                <table style="width: 100%; border-collapse: collapse; font-size: 0.8rem; min-width: 28rem;">
                    <thead>
                        <tr style="text-align: start; color: var(--mr-dim);">
                            <th scope="col" style="padding: 0.3rem;">عضو</th>
                            <th scope="col" style="padding: 0.3rem;">پیام</th>
                            <th scope="col" style="padding: 0.3rem;">توکن</th>
                            <th scope="col" style="padding: 0.3rem;">میانگین هر پیام</th>
                            <th scope="col" style="padding: 0.3rem;">👍</th>
                            <th scope="col" style="padding: 0.3rem;">👎</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($stats as $row)
                            <tr style="border-top: 1px solid var(--mr-line);">
                                <td style="padding: 0.3rem;">{{ $row['name'] }} <span class="mr-sub">({{ $row['role'] }})</span></td>
                                <td style="padding: 0.3rem;">{{ $fa($row['messages']) }}</td>
                                <td style="padding: 0.3rem;">{{ $fa(number_format($row['tokens'])) }}</td>
                                <td style="padding: 0.3rem;">{{ $fa(number_format($row['average'])) }}</td>
                                <td style="padding: 0.3rem;">{{ $fa($row['up']) }}</td>
                                <td style="padding: 0.3rem;">{{ $fa($row['down']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </details>
    @endif
</div>
