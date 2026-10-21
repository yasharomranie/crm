<x-filament-panels::page>
    @php
        $missions = $this->getMissions();
        $mission = $this->getSelectedMission();
        $profiles = $this->getProfiles();
        $catalog = app(\App\Domain\Ai\CrmToolCatalog::class);
        $profileById = $profiles->keyBy('id');
        $usedIds = collect([$coordinatorId, $criticId])->merge(collect($specialists)->pluck('profile_id'))->filter()->all();
        $toolLabels = fn ($profile) => collect($profile->allowed_tools ?? [])->map(fn ($tool) => $catalog->label($tool))->filter()->values();
        $fa = fn ($number) => \Illuminate\Support\Number::toPersian($number);
    @endphp

    <style>
        .mr-root { --mr-card: #ffffff; --mr-soft: #f4f6fb; --mr-line: #e3e8f2; --mr-text: #0f1b2d; --mr-dim: #62708a; --mr-accent: #2563eb; --mr-accent-soft: #e8efff; --mr-good: #15803d; --mr-good-soft: #e6f6ec; --mr-warn: #b45309; --mr-warn-soft: #fff4de; --mr-bad: #b91c1c; --mr-bad-soft: #fdeaea; color: var(--mr-text); }
        .dark .mr-root { --mr-card: #111a2b; --mr-soft: #18233a; --mr-line: #263450; --mr-text: #e8edf7; --mr-dim: #93a1bd; --mr-accent: #6aa0ff; --mr-accent-soft: #1b2c52; --mr-good: #4ade80; --mr-good-soft: #12301f; --mr-warn: #fbbf24; --mr-warn-soft: #382a0d; --mr-bad: #f87171; --mr-bad-soft: #3a1717; }
        .mr-root * { box-sizing: border-box; }
        .mr-layout { display: grid; gap: 1.25rem; grid-template-columns: minmax(0, 1fr); }
        @media (min-width: 1024px) { .mr-layout { grid-template-columns: 19rem minmax(0, 1fr); align-items: start; } }
        .mr-card { background: var(--mr-card); border: 1px solid var(--mr-line); border-radius: 1rem; padding: 1.25rem; }
        .mr-stack { display: grid; gap: 1rem; }
        .mr-stack > * { min-width: 0; }
        .mr-h { font-size: 1rem; font-weight: 700; margin: 0; }
        .mr-sub { font-size: 0.8rem; color: var(--mr-dim); line-height: 1.8; margin: 0; }
        .mr-btn { display: inline-flex; align-items: center; justify-content: center; gap: 0.5rem; min-height: 2.5rem; padding: 0 1.1rem; border-radius: 0.75rem; border: 1px solid var(--mr-line); background: var(--mr-card); color: var(--mr-text); font-size: 0.85rem; font-weight: 600; cursor: pointer; text-decoration: none; }
        .mr-btn:hover { background: var(--mr-soft); }
        .mr-btn:focus-visible, .mr-input:focus-visible, .mr-tile:focus-visible, .mr-slot:focus-visible, .mr-mission:focus-visible { outline: 2px solid var(--mr-accent); outline-offset: 2px; }
        .mr-btn-primary { background: var(--mr-accent); border-color: var(--mr-accent); color: #fff; min-height: 2.9rem; padding: 0 1.6rem; font-size: 0.95rem; }
        .mr-btn-primary:hover { background: var(--mr-accent); filter: brightness(1.08); }
        .mr-btn[disabled] { opacity: 0.5; cursor: not-allowed; }
        .mr-btn-quiet { border-color: transparent; background: transparent; color: var(--mr-dim); }
        .mr-input { width: 100%; border: 1px solid var(--mr-line); background: var(--mr-card); color: var(--mr-text); border-radius: 0.75rem; padding: 0.65rem 0.85rem; font-size: 0.9rem; font-family: inherit; }
        textarea.mr-input { resize: vertical; line-height: 1.8; }
        .mr-label { display: grid; gap: 0.35rem; font-size: 0.82rem; font-weight: 600; }
        .mr-err { color: var(--mr-bad); font-size: 0.75rem; font-weight: 500; }
        .mr-pill { display: inline-flex; align-items: center; gap: 0.3rem; padding: 0.15rem 0.6rem; border-radius: 9999px; font-size: 0.7rem; font-weight: 600; background: var(--mr-soft); color: var(--mr-dim); white-space: nowrap; }
        .mr-pill.good { background: var(--mr-good-soft); color: var(--mr-good); }
        .mr-pill.warn { background: var(--mr-warn-soft); color: var(--mr-warn); }
        .mr-pill.bad { background: var(--mr-bad-soft); color: var(--mr-bad); }
        .mr-pill.accent { background: var(--mr-accent-soft); color: var(--mr-accent); }
        .mr-avatars { display: flex; }
        .mr-avatars > * + * { margin-inline-start: -0.5rem; }
        .mr-avatars > * { border-radius: 9999px; background: var(--mr-card); }
        .mr-mission { display: grid; gap: 0.6rem; width: 100%; text-align: start; padding: 0.9rem 1rem; border: 1px solid var(--mr-line); border-radius: 0.9rem; background: var(--mr-card); color: inherit; cursor: pointer; }
        .mr-mission:hover { border-color: var(--mr-accent); }
        .mr-mission.on { border-color: var(--mr-accent); background: var(--mr-accent-soft); }
        .mr-row { display: flex; align-items: center; justify-content: space-between; gap: 0.75rem; flex-wrap: wrap; }
        .mr-step { display: grid; grid-template-columns: 2rem minmax(0, 1fr); gap: 0.85rem; }
        .mr-num { width: 2rem; height: 2rem; border-radius: 9999px; background: var(--mr-accent-soft); color: var(--mr-accent); font-weight: 800; display: grid; place-items: center; font-size: 0.85rem; }
        .mr-role { border: 1px solid var(--mr-line); border-radius: 0.9rem; padding: 0.9rem; background: var(--mr-soft); display: grid; gap: 0.7rem; }
        .mr-slot { display: flex; align-items: center; gap: 0.8rem; width: 100%; min-width: 0; overflow-wrap: anywhere; padding: 0.7rem 0.9rem; border-radius: 0.8rem; border: 2px dashed var(--mr-line); background: var(--mr-card); color: var(--mr-dim); font-size: 0.85rem; cursor: pointer; text-align: start; }
        .mr-slot.filled { border-style: solid; border-color: var(--mr-line); color: var(--mr-text); }
        .mr-slot:hover { border-color: var(--mr-accent); }
        .mr-overlay { position: fixed; inset: 0; z-index: 60; background: rgb(8 14 26 / 0.6); display: grid; place-items: center; padding: 1rem; }
        .mr-modal { width: min(46rem, 100%); max-height: 86vh; max-height: 86dvh; overflow-x: hidden; overflow-y: auto; overflow-wrap: anywhere; background: var(--mr-card); border: 1px solid var(--mr-line); border-radius: 1.1rem; padding: 1.25rem; }
        .mr-grid { display: grid; gap: 0.7rem; grid-template-columns: repeat(auto-fill, minmax(min(13rem, 100%), 1fr)); }
        .mr-tile { min-width: 0; overflow-wrap: anywhere; display: grid; justify-items: center; gap: 0.5rem; text-align: center; padding: 1rem 0.8rem; border-radius: 0.9rem; border: 1px solid var(--mr-line); background: var(--mr-soft); color: inherit; cursor: pointer; }
        .mr-tile:hover:not([disabled]) { border-color: var(--mr-accent); background: var(--mr-accent-soft); }
        .mr-tile[disabled] { opacity: 0.45; cursor: not-allowed; }
        .mr-tile .mr-pill, .mr-actions .mr-pill, .mr-slot .mr-pill { white-space: normal; max-width: 100%; text-align: center; overflow-wrap: anywhere; }
        .mr-chips { display: flex; flex-wrap: wrap; gap: 0.3rem; justify-content: center; }
        .mr-team { display: grid; gap: 0.75rem; grid-template-columns: minmax(0, 1fr); align-items: stretch; }
        @media (min-width: 900px) { .mr-team.has-critic { grid-template-columns: minmax(0, 1.4fr) auto minmax(0, 1fr) auto minmax(0, 1fr); } .mr-team.no-critic { grid-template-columns: minmax(0, 1.4fr) auto minmax(0, 1fr); } }
        .mr-arrow { display: none; align-self: center; color: var(--mr-dim); font-size: 1.4rem; }
        @media (min-width: 900px) { .mr-arrow { display: block; } }
        .mr-member { display: flex; align-items: center; gap: 0.6rem; padding: 0.5rem 0.7rem; border-radius: 0.8rem; background: var(--mr-soft); }
        .mr-decision { display: grid; grid-template-columns: 2rem minmax(0, 1fr); gap: 0.8rem; padding: 0.9rem 1rem; border-radius: 0.9rem; background: var(--mr-card); border: 1px solid var(--mr-line); }
        .mr-finding { border: 1px solid var(--mr-line); border-radius: 0.85rem; padding: 0.8rem 0.9rem; background: var(--mr-card); display: grid; gap: 0.5rem; font-size: 0.85rem; line-height: 1.9; }
        .mr-templates { display: grid; gap: 0.7rem; grid-template-columns: repeat(auto-fit, minmax(14rem, 1fr)); }
        .mr-template { display: grid; gap: 0.35rem; text-align: start; padding: 0.9rem 1rem; border-radius: 0.9rem; border: 1px solid var(--mr-line); background: var(--mr-soft); color: inherit; cursor: pointer; }
        .mr-template:hover { border-color: var(--mr-accent); }
        .mr-template.on { border-color: var(--mr-accent); background: var(--mr-accent-soft); }
        .mr-template:focus-visible { outline: 2px solid var(--mr-accent); outline-offset: 2px; }
        .mr-tiles { display: grid; gap: 0.7rem; grid-template-columns: repeat(auto-fit, minmax(9.5rem, 1fr)); }
        .mr-tile-stat { padding: 0.9rem 1rem; border-radius: 0.9rem; background: var(--mr-soft); border: 1px solid var(--mr-line); display: grid; gap: 0.15rem; }
        .mr-tile-stat strong { font-size: 1.6rem; line-height: 1.2; font-variant-numeric: tabular-nums; }
        .mr-tile-stat.warn { background: var(--mr-warn-soft); border-color: var(--mr-warn); }
        .mr-tile-stat.bad { background: var(--mr-bad-soft); border-color: var(--mr-bad); }
        .mr-item { display: flex; gap: 0.75rem; align-items: center; justify-content: space-between; flex-wrap: wrap; padding: 0.7rem 0.9rem; border-radius: 0.8rem; background: var(--mr-soft); }
        .mr-finding.rejected { opacity: 0.55; }
        .mr-finding.rejected .mr-claim { text-decoration: line-through; }
        .mr-finding.locked { border-color: var(--mr-good); }
        .mr-actions { display: flex; flex-wrap: wrap; gap: 0.4rem; align-items: center; }
        .mr-mini { display: inline-flex; align-items: center; gap: 0.25rem; min-height: 1.8rem; padding: 0 0.7rem; border-radius: 0.6rem; border: 1px solid var(--mr-line); background: var(--mr-card); color: var(--mr-text); font-size: 0.75rem; font-weight: 600; cursor: pointer; }
        .mr-mini:hover { background: var(--mr-soft); }
        .mr-mini:focus-visible { outline: 2px solid var(--mr-accent); outline-offset: 2px; }
        .mr-note { display: flex; gap: 0.6rem; align-items: flex-start; justify-content: space-between; padding: 0.6rem 0.8rem; border-radius: 0.7rem; background: var(--mr-soft); font-size: 0.82rem; line-height: 1.8; }
        .mr-critique { background: var(--mr-bad-soft); color: var(--mr-bad); border-radius: 0.6rem; padding: 0.45rem 0.7rem; font-size: 0.78rem; line-height: 1.8; }
        .mr-columns { display: grid; gap: 1rem; grid-template-columns: repeat(auto-fit, minmax(17rem, 1fr)); align-items: start; }
        .mr-empty { text-align: center; padding: 2rem 1rem; display: grid; gap: 1rem; justify-items: center; }
        .mr-howto { display: grid; gap: 0.6rem; text-align: start; max-width: 28rem; }
        .mr-spinner { width: 1rem; height: 1rem; border-radius: 9999px; border: 2px solid rgb(255 255 255 / 0.4); border-top-color: #fff; animation: mr-spin 0.8s linear infinite; }
        @media (max-width: 639px) {
            .mr-card { padding: 1rem; }
            .mr-step { grid-template-columns: minmax(0, 1fr); gap: 0.6rem; }
            .mr-role { padding: 0.75rem; }
            .mr-overlay { align-items: end; padding: 0; }
            .mr-modal { width: 100%; max-height: 90vh; max-height: 90dvh; border-radius: 1.1rem 1.1rem 0 0; padding: 1rem; }
            .mr-templates { grid-template-columns: minmax(0, 1fr); }
        }
        @keyframes mr-spin { to { transform: rotate(360deg); } }
        @media (prefers-reduced-motion: reduce) { .mr-spinner { animation: none; } }
    </style>

    <div class="mr-root" x-data x-on:keydown.escape.window="$wire.closePicker()">
        <div class="mr-layout">
            {{-- ===== missions ===== --}}
            <aside class="mr-stack">
                <button type="button" class="mr-btn mr-btn-primary" wire:click="startCreate" style="width: 100%;">＋ مأموریت جدید</button>

                @forelse ($missions as $item)
                    <button type="button" wire:click="openMission({{ $item->id }})" wire:key="mission-{{ $item->id }}" @class(['mr-mission', 'on' => $mission?->id === $item->id])>
                        <span style="font-weight: 700; font-size: 0.9rem;">{{ $item->title }}</span>
                        <span class="mr-row">
                            <span class="mr-avatars">
                                @foreach ($item->roles->take(4) as $role)
                                    @if ($role->profile)
                                        <x-ai-assistant-avatar :shape="$role->profile->avatar_shape" :color="$role->profile->avatar_color" :size="30" :animated="false" />
                                    @endif
                                @endforeach
                            </span>
                            <span @class(['mr-pill', 'good' => $item->status === \App\Enums\AiMissionStatus::Done, 'warn' => $item->status === \App\Enums\AiMissionStatus::WaitingApproval, 'bad' => $item->status === \App\Enums\AiMissionStatus::Failed, 'accent' => $item->status === \App\Enums\AiMissionStatus::Running])>{{ $item->status->getLabel() }}</span>
                        </span>
                        <span class="mr-sub">دور {{ $fa($item->rounds_run) }} از {{ $fa($item->max_rounds) }}</span>
                    </button>
                @empty
                    <div class="mr-card mr-sub">هنوز مأموریتی ندارید. یک هدف به چند دستیار بسپارید تا با هم روی آن کار کنند و برایتان گزارش بنویسند.</div>
                @endforelse
            </aside>

            {{-- ===== main ===== --}}
            <main class="mr-stack" style="min-width: 0;">
                @if ($showCreate)
                    <div class="mr-card mr-stack">
                        <div>
                            <h2 class="mr-h" style="font-size: 1.15rem;">مأموریت جدید</h2>
                            <p class="mr-sub">از یک نمونهٔ آماده شروع کنید یا خودتان از صفر بنویسید: بگویید چه می‌خواهید، تیم را انتخاب کنید و شروع کنید.</p>
                        </div>

                        <div class="mr-stack" style="gap: 0.6rem;">
                            <strong style="font-size: 0.85rem;">✨ نمونه‌های آماده</strong>
                            <div class="mr-templates">
                                @foreach (app(\App\Domain\Ai\Missions\MissionTemplates::class)->all() as $key => $template)
                                    <button type="button" wire:key="template-{{ $key }}" wire:click="applyTemplate('{{ $key }}')" @class(['mr-template', 'on' => $templateKey === $key])>
                                        <span style="font-size: 1.4rem;">{{ $template['icon'] }}</span>
                                        <strong style="font-size: 0.88rem;">{{ $template['title'] }}</strong>
                                        <span class="mr-sub">{{ $template['about'] }}</span>
                                        <span class="mr-pill accent" style="justify-self: start;">پیشنهاد تکرار: {{ $template['schedule']['label'] }}</span>
                                    </button>
                                @endforeach
                            </div>
                            @if ($templateKey)
                                <label style="display: flex; gap: 0.5rem; align-items: center; font-size: 0.82rem;">
                                    <input id="apply-template-schedule" type="checkbox" wire:model="applyTemplateSchedule" />
                                    مأموریت با تکرار پیشنهادیِ این نمونه ({{ app(\App\Domain\Ai\Missions\MissionTemplates::class)->find($templateKey)['schedule']['label'] }}) ساخته شود
                                </label>
                            @endif
                        </div>

                        {{-- step 1 --}}
                        <section class="mr-step">
                            <span class="mr-num">۱</span>
                            <div class="mr-stack">
                                <h3 class="mr-h">چه می‌خواهید؟</h3>
                                <label class="mr-label">عنوان کوتاه
                                    <input id="mission-title" type="text" class="mr-input" wire:model="missionTitle" placeholder="مثلاً: وضعیت رزرو هفتهٔ آینده" />
                                    @error('missionTitle') <span class="mr-err">{{ $message }}</span> @enderror
                                </label>
                                <label class="mr-label">هدف (نتیجه‌ای که می‌خواهید)
                                    <textarea id="mission-goal" rows="3" class="mr-input" wire:model="missionGoal" placeholder="مثلاً: بگو کدام روزها اتاق خالی کم است و کدام مهمان نیاز به پیگیری دارد."></textarea>
                                    @error('missionGoal') <span class="mr-err">{{ $message }}</span> @enderror
                                </label>
                            </div>
                        </section>

                        {{-- step 2 --}}
                        <section class="mr-step">
                            <span class="mr-num">۲</span>
                            <div class="mr-stack">
                                <div>
                                    <h3 class="mr-h">تیم را بچینید</h3>
                                    <p class="mr-sub">روی هر جایگاه بزنید و دستیار را انتخاب کنید. متخصص‌ها یافته می‌آورند، منتقد ایراد می‌گیرد و هماهنگ‌کننده نتیجه را جمع می‌کند.</p>
                                </div>

                                {{-- specialists --}}
                                <div class="mr-role">
                                    <div class="mr-row">
                                        <div><strong style="font-size: 0.9rem;">🔎 متخصص‌ها</strong> <span class="mr-sub">کسانی که داده را بررسی می‌کنند (یک یا بیشتر)</span></div>
                                        @if (count($specialists) < 4)
                                            <button type="button" class="mr-btn" wire:click="addSpecialist">＋ متخصص دیگر</button>
                                        @endif
                                    </div>
                                    @foreach ($specialists as $index => $row)
                                        @php $chosen = $profileById[$row['profile_id']] ?? null; @endphp
                                        <div wire:key="specialist-{{ $index }}" class="mr-stack" style="gap: 0.5rem;">
                                            <div style="display: flex; gap: 0.5rem; align-items: stretch; min-width: 0;">
                                                <button type="button" wire:click="openPicker('specialist:{{ $index }}')" @class(['mr-slot', 'filled' => $chosen])>
                                                    @if ($chosen)
                                                        <x-ai-assistant-avatar :shape="$chosen->avatar_shape" :color="$chosen->avatar_color" :size="44" :animated="false" />
                                                        <span style="min-width: 0;"><strong style="display: block;">{{ $chosen->displayName() }}</strong><span class="mr-sub">برای تغییر بزنید</span></span>
                                                    @else
                                                        <span style="font-size: 1.4rem;">＋</span> دستیار را انتخاب کنید
                                                    @endif
                                                </button>
                                                @if (count($specialists) > 1)
                                                    <button type="button" class="mr-btn mr-btn-quiet" style="flex: none;" wire:click="removeSpecialist({{ $index }})" aria-label="حذف این متخصص">حذف</button>
                                                @endif
                                            </div>
                                            @php $needs = $row['needs'] ?? []; $missing = $chosen ? array_values(array_diff($needs, $chosen->allowed_tools ?? [])) : []; @endphp
                                            @if ($needs !== [])
                                                <div class="mr-actions">
                                                    <span class="mr-sub">ابزار لازم برای این جایگاه:</span>
                                                    @foreach ($needs as $need)
                                                        <span @class(['mr-pill', 'accent' => ! in_array($need, $missing, true), 'bad' => in_array($need, $missing, true)])>{{ $catalog->label($need) }}</span>
                                                    @endforeach
                                                </div>
                                            @endif
                                            @if ($chosen && $needs !== [] && $missing !== [])
                                                <span class="mr-pill warn" style="justify-self: start; white-space: normal;">⚠ این دستیار همهٔ ابزارهای لازم را ندارد؛ در «مرکز دستیاران» آن‌ها را برایش فعال کنید، وگرنه یافته‌هایش «بدون منبع» می‌شود</span>
                                            @elseif ($chosen && $toolLabels($chosen)->isEmpty())
                                                <span class="mr-pill warn" style="justify-self: start;">⚠ این دستیار ابزاری ندارد؛ یافته‌هایش «بدون منبع» می‌شود</span>
                                            @endif
                                            <input id="specialist-directive-{{ $index }}" type="text" class="mr-input" wire:model="specialists.{{ $index }}.directive" placeholder="چه چیزی را بررسی کند؟ (اختیاری)" />
                                        </div>
                                    @endforeach
                                </div>

                                {{-- critic --}}
                                <div class="mr-role">
                                    <div class="mr-row">
                                        <div><strong style="font-size: 0.9rem;">🧐 منتقد</strong> <span class="mr-sub">ادعاهای ضعیف یا بی‌منبع را به چالش می‌کشد (پیشنهاد می‌شود)</span></div>
                                        <button type="button" class="mr-btn" wire:click="toggleCritic" aria-pressed="{{ $withCritic ? 'true' : 'false' }}">{{ $withCritic ? 'حذف منتقد' : '＋ افزودن منتقد' }}</button>
                                    </div>
                                    @if ($withCritic)
                                        @php $chosen = $profileById[$criticId] ?? null; @endphp
                                        <button type="button" wire:click="openPicker('critic')" @class(['mr-slot', 'filled' => $chosen])>
                                            @if ($chosen)
                                                <x-ai-assistant-avatar :shape="$chosen->avatar_shape" :color="$chosen->avatar_color" :size="44" :animated="false" />
                                                <span style="min-width: 0;"><strong style="display: block;">{{ $chosen->displayName() }}</strong><span class="mr-sub">برای تغییر بزنید</span></span>
                                            @else
                                                <span style="font-size: 1.4rem;">＋</span> دستیار را انتخاب کنید
                                            @endif
                                        </button>
                                        <input id="critic-directive" type="text" class="mr-input" wire:model="criticDirective" placeholder="به چه چیزی حساس باشد؟ (اختیاری)" />
                                    @endif
                                </div>

                                {{-- coordinator --}}
                                <div class="mr-role">
                                    <div><strong style="font-size: 0.9rem;">🧭 هماهنگ‌کننده</strong> <span class="mr-sub">یافته‌ها را جمع می‌کند و پیشنهاد تصمیم می‌نویسد</span></div>
                                    @php $chosen = $profileById[$coordinatorId] ?? null; @endphp
                                    <button type="button" wire:click="openPicker('coordinator')" @class(['mr-slot', 'filled' => $chosen])>
                                        @if ($chosen)
                                            <x-ai-assistant-avatar :shape="$chosen->avatar_shape" :color="$chosen->avatar_color" :size="44" :animated="false" />
                                            <span style="min-width: 0;"><strong style="display: block;">{{ $chosen->displayName() }}</strong><span class="mr-sub">برای تغییر بزنید</span></span>
                                        @else
                                            <span style="font-size: 1.4rem;">＋</span> دستیار را انتخاب کنید
                                        @endif
                                    </button>
                                    <input id="coordinator-directive" type="text" class="mr-input" wire:model="coordinatorDirective" placeholder="چه نوع جمع‌بندی بنویسد؟ (اختیاری)" />
                                </div>
                            </div>
                        </section>

                        {{-- step 3: sources --}}
                        <section class="mr-step">
                            <span class="mr-num">۳</span>
                            <div class="mr-stack">
                                <div>
                                    <h3 class="mr-h">فایل‌های استراتژی و گزارش این مأموریت (اختیاری)</h3>
                                    <p class="mr-sub">اگر استراتژی یا گزارش‌هایتان فایل دارد، همین‌جا بگذارید تا کتابخانهٔ همین مأموریت شود. بعد از ساخت مأموریت از آن‌ها یک «خلاصهٔ استراتژی» می‌سازید و تأیید می‌کنید. متن فایل‌ها برای پردازش به سرویس هوش مصنوعی فرستاده می‌شود.</p>
                                </div>
                                @php $pendingSources = $this->getPendingSources(); @endphp
                                @foreach ($pendingSources as $item)
                                    <div class="mr-item" wire:key="pending-source-{{ $item->id }}" @if ($item->status === \App\Enums\AiMissionSourceStatus::Pending) wire:poll.3s @endif>
                                        <div style="min-width: 0; flex: 1;">
                                            <strong style="font-size: 0.88rem;">{{ $item->title }}</strong>
                                            <span class="mr-sub" style="display: block;">{{ strtoupper($item->extension) }} · {{ $item->kind->shortLabel() }} · {{ $item->status->getLabel() }}</span>
                                        </div>
                                        <button type="button" class="mr-mini" wire:click="removePendingSource({{ $item->id }})" aria-label="حذف فایل">حذف</button>
                                    </div>
                                @endforeach
                                @include('filament.pages.partials.ai-mission-source-upload')
                            </div>
                        </section>

                        {{-- step 4 --}}
                        <section class="mr-step">
                            <span class="mr-num">۴</span>
                            <div class="mr-stack">
                                <h3 class="mr-h">تنظیمات (می‌توانید دست نزنید)</h3>
                                <div style="display: grid; gap: 0.75rem; grid-template-columns: repeat(auto-fit, minmax(14rem, 1fr));">
                                    <label class="mr-label">شرط پایان
                                        <input id="mission-end" type="text" class="mr-input" wire:model="missionEnd" placeholder="مثلاً: وقتی برای هر بخش یافتهٔ منبع‌دار داریم" />
                                    </label>
                                    <label class="mr-label">حداکثر چند دور
                                        <input id="mission-rounds" type="number" min="1" max="5" class="mr-input" wire:model="maxRounds" />
                                    </label>
                                    <label class="mr-label">سقف مصرف (توکن)
                                        <input id="mission-budget" type="number" min="1000" step="1000" class="mr-input" wire:model="tokenBudget" />
                                        <span class="mr-sub">اگر مصرف به این عدد برسد، تیم خودش می‌ایستد.</span>
                                        @error('tokenBudget') <span class="mr-err">{{ $message }}</span> @enderror
                                    </label>
                                </div>
                                <p class="mr-sub">تیم فقط می‌خواند و گزارش می‌دهد؛ هیچ داده‌ای را تغییر نمی‌دهد و هر عضو فقط ابزارهای خواندنیِ همان دستیار را دارد، به اندازهٔ مجوزهای خود شما.</p>
                            </div>
                        </section>

                        <div class="mr-row" style="border-top: 1px solid var(--mr-line); padding-top: 1rem;">
                            <button type="button" class="mr-btn mr-btn-quiet" wire:click="cancelCreate">انصراف</button>
                            <button type="button" class="mr-btn mr-btn-primary" wire:click="createMission">ساخت مأموریت</button>
                        </div>
                    </div>
                @elseif ($mission)
                    @php
                        $specialistRoles = $mission->roles->where('role', \App\Enums\AiMissionRoleType::Specialist);
                        $criticRole = $mission->roles->firstWhere('role', \App\Enums\AiMissionRoleType::Critic);
                        $coordinatorRole = $mission->roles->firstWhere('role', \App\Enums\AiMissionRoleType::Coordinator);
                    @endphp

                    {{-- hero --}}
                    <div class="mr-card mr-stack" @if ($mission->status === \App\Enums\AiMissionStatus::Running) wire:poll.3s @endif>
                        <div class="mr-row" style="align-items: flex-start;">
                            <div style="min-width: 0;">
                                <h2 class="mr-h" style="font-size: 1.2rem;">{{ $mission->title }}</h2>
                                <p class="mr-sub" style="margin-top: 0.3rem;">{{ $mission->goal }}</p>
                                @if ($mission->end_condition)
                                    <p class="mr-sub">شرط پایان: {{ $mission->end_condition }}</p>
                                @endif
                            </div>
                            <span @class(['mr-pill', 'good' => $mission->status === \App\Enums\AiMissionStatus::Done, 'warn' => $mission->status === \App\Enums\AiMissionStatus::WaitingApproval, 'bad' => $mission->status === \App\Enums\AiMissionStatus::Failed, 'accent' => $mission->status === \App\Enums\AiMissionStatus::Running]) style="font-size: 0.8rem; padding: 0.3rem 0.85rem;">{{ $mission->status->getLabel() }}</span>
                        </div>

                        {{-- team flow --}}
                        <div @class(['mr-team', 'has-critic' => $criticRole, 'no-critic' => ! $criticRole])>
                            <div class="mr-role">
                                <span class="mr-sub" style="font-weight: 700;">🔎 متخصص‌ها بررسی می‌کنند</span>
                                @foreach ($specialistRoles as $role)
                                    <div class="mr-member" wire:key="role-{{ $role->id }}">
                                        @if ($role->profile)
                                            <x-ai-assistant-avatar :shape="$role->profile->avatar_shape" :color="$role->profile->avatar_color" :size="38" :animated="false" />
                                        @endif
                                        <span style="min-width: 0; font-size: 0.85rem; font-weight: 600;">{{ $role->profile?->displayName() ?? 'دستیار حذف‌شده' }}</span>
                                    </div>
                                @endforeach
                            </div>
                            @if ($criticRole)
                                <span class="mr-arrow">←</span>
                                <div class="mr-role">
                                    <span class="mr-sub" style="font-weight: 700;">🧐 منتقد نقد می‌کند</span>
                                    <div class="mr-member">
                                        @if ($criticRole->profile)
                                            <x-ai-assistant-avatar :shape="$criticRole->profile->avatar_shape" :color="$criticRole->profile->avatar_color" :size="38" :animated="false" />
                                        @endif
                                        <span style="font-size: 0.85rem; font-weight: 600;">{{ $criticRole->profile?->displayName() ?? 'دستیار حذف‌شده' }}</span>
                                    </div>
                                </div>
                            @endif
                            <span class="mr-arrow">←</span>
                            <div class="mr-role">
                                <span class="mr-sub" style="font-weight: 700;">🧭 هماهنگ‌کننده جمع می‌بندد</span>
                                <div class="mr-member">
                                    @if ($coordinatorRole?->profile)
                                        <x-ai-assistant-avatar :shape="$coordinatorRole->profile->avatar_shape" :color="$coordinatorRole->profile->avatar_color" :size="38" :animated="false" />
                                    @endif
                                    <span style="font-size: 0.85rem; font-weight: 600;">{{ $coordinatorRole?->profile?->displayName() ?? 'دستیار حذف‌شده' }}</span>
                                </div>
                            </div>
                        </div>

                        @if ($mission->last_error && in_array($mission->status, [\App\Enums\AiMissionStatus::Failed, \App\Enums\AiMissionStatus::Stopped], true))
                            <div class="mr-critique">{{ $mission->status === \App\Enums\AiMissionStatus::Failed ? 'اجرا ناموفق بود: ' : '' }}{{ $mission->last_error }}{{ $mission->status === \App\Enums\AiMissionStatus::Failed ? ' — می‌توانید همین دور را دوباره اجرا کنید.' : '' }}</div>
                        @endif

                        @if ($mission->status === \App\Enums\AiMissionStatus::Running)
                            <div class="mr-role" style="border-color: var(--mr-accent);">
                                <div class="mr-row">
                                    <span style="display: inline-flex; align-items: center; gap: 0.6rem; font-size: 0.9rem; font-weight: 600;">
                                        <span class="mr-spinner" style="border-color: var(--mr-accent-soft); border-top-color: var(--mr-accent);"></span>
                                        {{ $mission->current_step ?? 'در حال کار…' }}
                                    </span>
                                    <button type="button" class="mr-btn" wire:click="stopMission" wire:confirm="تیم متوقف شود؟ دورِ نیمه‌کاره کنار گذاشته می‌شود.">⏹ توقف</button>
                                </div>
                                <div style="height: 0.4rem; border-radius: 9999px; background: var(--mr-line); overflow: hidden;">
                                    <div style="height: 100%; background: var(--mr-accent); width: {{ $mission->step_total > 0 ? min(100, (int) round((($mission->step_index) / $mission->step_total) * 100)) : 0 }}%;"></div>
                                </div>
                                <p class="mr-sub">مرحلهٔ {{ $fa($mission->step_index + 1) }} از {{ $fa($mission->step_total) }} · لازم نیست صفحه باز بماند؛ تیم در پس‌زمینه کار می‌کند و این صفحه هر چند ثانیه تازه می‌شود.</p>
                            </div>
                        @endif

                        <div class="mr-row">
                            <div class="mr-row" style="gap: 0.5rem;">
                                @if ($mission->status !== \App\Enums\AiMissionStatus::Running)
                                    <button type="button" class="mr-btn mr-btn-primary" wire:click="runRound" wire:loading.attr="disabled" wire:target="runRound" @disabled(! $mission->canRunAnotherRound())>
                                        <span wire:loading.remove wire:target="runRound">▶ {{ $mission->rounds_run === 0 ? 'شروع کار تیم' : 'دور بعدی' }}</span>
                                        <span wire:loading wire:target="runRound" style="display: inline-flex; align-items: center; gap: 0.5rem;"><span class="mr-spinner"></span> در حال شروع…</span>
                                    </button>
                                @endif
                                <span class="mr-sub">دور {{ $fa($mission->rounds_run) }} از {{ $fa($mission->max_rounds) }}</span>
                            </div>
                            <div class="mr-row" style="gap: 0.4rem;">
                                <a class="mr-btn" href="{{ route('filament.admin.pages.ai-team-map', ['mission' => $mission->id]) }}">🌐 نمایش روی نقشه</a>
                                <button type="button" class="mr-btn mr-btn-quiet" wire:click="deleteMission({{ $mission->id }})" wire:confirm="این مأموریت و تختهٔ آن حذف شود؟" @disabled($mission->status === \App\Enums\AiMissionStatus::Running)>حذف</button>
                            </div>
                        </div>

                        <div class="mr-row" style="border-top: 1px solid var(--mr-line); padding-top: 0.75rem;">
                            <span class="mr-sub">
                                مصرف تا الان: {{ $fa(number_format($mission->tokens_used)) }}
                                @if ($mission->token_budget) از {{ $fa(number_format($mission->token_budget)) }} @endif
                                توکن
                            </span>
                            <span style="display: inline-flex; align-items: center; gap: 0.4rem;">
                                <input type="number" id="budget-input" x-ref="budget" min="1000" step="1000" class="mr-input" style="width: 8rem; padding: 0.35rem 0.6rem;" value="{{ $mission->token_budget ?? 60000 }}" aria-label="سقف توکن" />
                                <button type="button" class="mr-btn" wire:click="saveBudget($refs.budget.value)">ذخیرهٔ سقف</button>
                            </span>
                        </div>
                    </div>

                    {{-- sources and strategy brief --}}
                    @php
                        $attachedIds = $mission->sources->pluck('id')->all();
                        $isRunning = $mission->status === \App\Enums\AiMissionStatus::Running;
                        $briefStatus = $mission->brief_status;
                        $briefStale = $mission->sources->isNotEmpty() && $briefStatus === \App\Enums\AiMissionBriefStatus::Approved && $mission->brief_sources_hash !== \App\Domain\Ai\Missions\MissionBrief::sourcesHash($mission);
                        $sourcesBusy = $briefStatus === \App\Enums\AiMissionBriefStatus::Building || $mission->sources->contains(fn ($item) => $item->status === \App\Enums\AiMissionSourceStatus::Pending);
                        $hasBrief = ! empty($mission->brief);
                    @endphp
                    <div class="mr-card mr-stack" style="gap: 0.8rem;" @if ($sourcesBusy) wire:poll.3s="refreshBrief" @endif>
                        <div class="mr-row">
                            <div>
                                <h2 class="mr-h">📚 کتابخانهٔ این مأموریت</h2>
                                <p class="mr-sub">فایل‌های استراتژی، سیاست‌ها و گزارش‌های همین مأموریت را بگذارید (برای هر مأموریت کتابخانهٔ جدا). فایل‌ها به‌محض «آماده» شدن قابل استفاده‌اند: اعضای تیم خودشان در آن‌ها جست‌وجو می‌کنند و برای هر یافته می‌نویسند از کدام فایل و کدام بخش آمده. متن فایل‌ها برای پردازش به سرویس هوش مصنوعی فرستاده می‌شود؛ فایل بسیار محرمانه نگذارید.</p>
                            </div>
                            @if ($mission->sources->isNotEmpty() && $hasBrief)
                                <span @class(['mr-pill', 'good' => $briefStatus === \App\Enums\AiMissionBriefStatus::Approved && ! $briefStale, 'warn' => $briefStale || $briefStatus === \App\Enums\AiMissionBriefStatus::Review || $briefStatus === \App\Enums\AiMissionBriefStatus::None, 'bad' => $briefStatus === \App\Enums\AiMissionBriefStatus::Failed, 'accent' => $briefStatus === \App\Enums\AiMissionBriefStatus::Building])>خلاصه: {{ $briefStale ? 'منابع تغییر کرده' : $briefStatus->getLabel() }}</span>
                            @endif
                        </div>

                        @forelse ($mission->sources as $item)
                            <div class="mr-item" wire:key="source-{{ $item->id }}">
                                <div style="min-width: 0; flex: 1;">
                                    <div class="mr-row" style="justify-content: flex-start; gap: 0.5rem;">
                                        <strong style="font-size: 0.88rem;">{{ $item->title }}</strong>
                                        <span @class(['mr-pill', 'good' => $item->status === \App\Enums\AiMissionSourceStatus::Ready, 'bad' => $item->status === \App\Enums\AiMissionSourceStatus::Failed, 'accent' => $item->status === \App\Enums\AiMissionSourceStatus::Pending])>{{ $item->status->getLabel() }}</span>
                                    </div>
                                    <span class="mr-sub">{{ strtoupper($item->extension) }} · {{ $fa(number_format($item->char_count)) }} نویسه@if ($item->tokens_used) · {{ $fa(number_format($item->tokens_used)) }} توکن برای خواندن@endif</span>
                                    @if ($item->status === \App\Enums\AiMissionSourceStatus::Failed && filled($item->error))
                                        <span class="mr-sub" style="color: var(--mr-bad); display: block;">{{ $item->error }}</span>
                                    @endif
                                </div>
                                <div class="mr-row" style="gap: 0.4rem; flex-wrap: wrap;">
                                    <select class="mr-input" style="width: auto; padding: 0.35rem 0.6rem; font-size: 0.78rem;" aria-label="نوع فایل {{ $item->title }}" wire:change="setSourceKind({{ $item->id }}, $event.target.value)" @disabled($isRunning)>
                                        @foreach (\App\Enums\AiMissionSourceKind::cases() as $kind)
                                            <option value="{{ $kind->value }}" @selected($item->kind === $kind)>{{ $kind->getLabel() }}</option>
                                        @endforeach
                                    </select>
                                    @if ($item->status === \App\Enums\AiMissionSourceStatus::Failed)
                                        <button type="button" class="mr-mini" wire:click="retrySource({{ $item->id }})">تلاش دوباره</button>
                                    @endif
                                    <button type="button" class="mr-mini" wire:click="deleteSource({{ $item->id }})" wire:confirm="این فایل حذف شود؟" aria-label="حذف فایل" @disabled($isRunning)>حذف</button>
                                </div>
                            </div>
                        @empty
                            <p class="mr-sub">هنوز فایلی برای این مأموریت نگذاشته‌اید.</p>
                        @endforelse

                        @if ($mission->sources->isNotEmpty())
                            @if ($mission->sources->contains(fn ($item) => $item->status === \App\Enums\AiMissionSourceStatus::Pending))
                                <p class="mr-sub" style="color: var(--mr-accent);">در حال خواندن فایل‌ها… وقتی همه «آماده» شدند می‌توانید مأموریت را اجرا کنید.</p>
                            @elseif ($mission->sources->contains(fn ($item) => $item->status === \App\Enums\AiMissionSourceStatus::Ready))
                                <p class="mr-sub" style="color: var(--mr-good);">✓ فایل‌ها آماده‌اند؛ تیم هنگام اجرا در آن‌ها جست‌وجو می‌کند.</p>
                            @endif
                        @endif

                        @include('filament.pages.partials.ai-mission-source-upload')

                        @if ($mission->sources->count() > 1 && $mission->roles->isNotEmpty())
                            <div class="mr-stack" style="gap: 0.5rem; border-top: 1px solid var(--mr-line); padding-top: 0.8rem;">
                                <div>
                                    <strong style="font-size: 0.9rem;">هر عضو کدام فایل‌ها را ببیند؟</strong>
                                    <p class="mr-sub">پیش‌فرض همه (✓). با زدن روی یک فایل برای یک عضو آن را کنار بگذارید؛ مثلاً فایل مالی فقط پیش متخصص مالی بماند. خلاصهٔ استراتژی همیشه به همه می‌رسد.</p>
                                </div>
                                @foreach ($mission->roles as $memberRole)
                                    @php $allowed = $memberRole->sources->pluck('id')->all() ?: $attachedIds; @endphp
                                    <div class="mr-item" wire:key="role-sources-{{ $memberRole->id }}">
                                        <div class="mr-member" style="background: transparent; padding: 0;">
                                            @if ($memberRole->profile)
                                                <x-ai-assistant-avatar :shape="$memberRole->profile->avatar_shape" :color="$memberRole->profile->avatar_color" :size="32" :animated="false" />
                                            @endif
                                            <span style="min-width: 0; font-size: 0.82rem; font-weight: 700;">{{ $memberRole->profile?->displayName() ?? 'دستیار' }} <span class="mr-sub">({{ $memberRole->role->getLabel() }})</span></span>
                                        </div>
                                        <div class="mr-actions">
                                            @foreach ($mission->sources as $attachedSource)
                                                @php $sees = in_array($attachedSource->id, $allowed, true); @endphp
                                                <button type="button" @class(['mr-mini']) style="{{ $sees ? '' : 'opacity: 0.55; text-decoration: line-through;' }}" wire:click="toggleRoleSource({{ $memberRole->id }}, {{ $attachedSource->id }})" aria-pressed="{{ $sees ? 'true' : 'false' }}" @disabled($isRunning)>{{ $sees ? '✓' : '✕' }} {{ \Illuminate\Support\Str::limit($attachedSource->title, 28) }}</button>
                                            @endforeach
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif

                        @if ($mission->sources->isNotEmpty())
                            <div class="mr-row" style="gap: 0.5rem; justify-content: flex-start; flex-wrap: wrap;">
                                <button type="button" class="mr-btn mr-btn-primary" style="min-height: 2.5rem; font-size: 0.85rem;" wire:click="buildBrief" wire:loading.attr="disabled" wire:target="buildBrief" @disabled($isRunning || $briefStatus === \App\Enums\AiMissionBriefStatus::Building)>{{ $hasBrief ? 'ساخت دوبارهٔ خلاصه' : 'ساخت خلاصهٔ استراتژی (اختیاری)' }}</button>
                                @if ($briefStatus === \App\Enums\AiMissionBriefStatus::Building)
                                    <span class="mr-sub">در حال ساخت خلاصه… (چند لحظه)</span>
                                @elseif ($briefStatus === \App\Enums\AiMissionBriefStatus::Failed)
                                    <span class="mr-sub" style="color: var(--mr-bad);">{{ $mission->brief_error }}</span>
                                @elseif (! $hasBrief)
                                    <span class="mr-sub">اختیاری: اگر می‌خواهید تیم همیشه چند هدف و «خط قرمز» ثابت را به یاد داشته باشد، خلاصه بسازید و تأیید کنید.</span>
                                @endif
                            </div>
                        @endif

                        @if ($mission->sources->isNotEmpty() && $hasBrief && $briefStatus !== \App\Enums\AiMissionBriefStatus::Building)
                            <div class="mr-stack" style="gap: 0.6rem; border-top: 1px solid var(--mr-line); padding-top: 0.8rem;">
                                <strong style="font-size: 0.9rem;">خلاصهٔ استراتژی (اختیاری؛ فقط بعد از تأیید شما به تیم داده می‌شود؛ هر مورد یک خط)</strong>
                                @if ($briefStale)
                                    <p class="mr-sub" style="color: var(--mr-warn);">فایل‌های مأموریت بعد از تأیید عوض شده‌اند؛ تا خلاصه را دوباره نسازید یا همین را دوباره تأیید نکنید، به تیم داده نمی‌شود.</p>
                                @endif
                                <div>
                                    <label class="mr-sub" for="brief-summary">چکیده</label>
                                    <textarea id="brief-summary" class="mr-input" rows="3" wire:model="briefSummary" @disabled($isRunning)></textarea>
                                </div>
                                @foreach (\App\Domain\Ai\Missions\MissionBrief::LISTS as $listKey => $listLabel)
                                    <div wire:key="brief-list-{{ $listKey }}">
                                        <label class="mr-sub" for="brief-{{ $listKey }}">{{ $listLabel }}</label>
                                        <textarea id="brief-{{ $listKey }}" class="mr-input" rows="{{ $listKey === 'conflicts' ? 2 : 3 }}" wire:model="briefLists.{{ $listKey }}" @disabled($isRunning)></textarea>
                                    </div>
                                @endforeach
                                <div class="mr-row" style="gap: 0.5rem; justify-content: flex-start;">
                                    <button type="button" class="mr-btn mr-btn-primary" wire:click="approveBrief" @disabled($isRunning)>✓ تأیید خلاصه برای تیم</button>
                                    <button type="button" class="mr-btn" wire:click="saveBrief" @disabled($isRunning)>ذخیرهٔ ویرایش</button>
                                    @if ($briefStatus === \App\Enums\AiMissionBriefStatus::Approved && ! $briefStale)
                                        <span class="mr-sub" style="color: var(--mr-good);">تأیید شده{{ $mission->brief_approved_at ? ' · '.$mission->brief_approved_at->copy()->timezone(\App\Domain\Ai\Missions\MissionSchedule::TIMEZONE)->toJalali() : '' }}</span>
                                    @endif
                                </div>
                            </div>
                        @endif
                    </div>

                    {{-- notes to the team --}}
                    <div class="mr-card mr-stack" style="gap: 0.7rem;">
                        <div>
                            <h2 class="mr-h">💬 پیام به تیم</h2>
                            <p class="mr-sub">اگر مسیر کار را باید عوض کنید همین‌جا بنویسید؛ {{ $mission->status === \App\Enums\AiMissionStatus::Running ? 'از مرحلهٔ بعدی' : 'از دور بعدی' }} همهٔ اعضا آن را رعایت می‌کنند.</p>
                        </div>
                        @foreach ($mission->notes ?? [] as $index => $note)
                            <div class="mr-note" wire:key="note-{{ $index }}">
                                <span>{{ $note['text'] }}</span>
                                <button type="button" class="mr-mini" wire:click="removeNote({{ $index }})" aria-label="پاک کردن پیام">پاک</button>
                            </div>
                        @endforeach
                        <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                            <input id="mission-note" type="text" class="mr-input" style="flex: 1; min-width: 14rem;" wire:model="noteText" wire:keydown.enter="addNote" placeholder="مثلاً: روی ویلاهای شمال تمرکز کن" />
                            <button type="button" class="mr-btn" wire:click="addNote">ارسال به تیم</button>
                        </div>
                    </div>

                    {{-- result --}}
                    @if ($mission->report)
                        <div class="mr-card mr-stack" style="border-color: var(--mr-warn);">
                            <div class="mr-row">
                                <h2 class="mr-h" style="font-size: 1.1rem;">📋 نتیجه: پیش‌نویس تصمیم</h2>
                                <span class="mr-pill warn">تا تأیید شما هیچ اقدامی انجام نمی‌شود</span>
                            </div>
                            <p style="font-size: 0.9rem; line-height: 2; margin: 0;">{{ $mission->report['summary'] ?? '' }}</p>

                            @if (! empty($mission->report['decisions']))
                                <div class="mr-stack" style="gap: 0.6rem;">
                                    <strong style="font-size: 0.85rem;">تصمیم‌های پیشنهادی</strong>
                                    @foreach ($mission->report['decisions'] as $index => $decision)
                                        <div class="mr-decision" wire:key="decision-{{ $index }}">
                                            <span class="mr-num">{{ $fa($index + 1) }}</span>
                                            <div>
                                                <strong style="font-size: 0.9rem;">{{ $decision['title'] }}</strong>
                                                @if (filled($decision['detail'] ?? null))
                                                    <p class="mr-sub" style="margin-top: 0.2rem;">{{ $decision['detail'] }}</p>
                                                @endif
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @endif

                            @if (! empty($mission->report['uncertain']))
                                <div class="mr-stack" style="gap: 0.4rem; background: var(--mr-warn-soft); border-radius: 0.9rem; padding: 0.9rem 1rem;">
                                    <strong style="font-size: 0.85rem; color: var(--mr-warn);">⚠ چه چیزی مطمئن نیست (قبل از تصمیم بخوانید)</strong>
                                    <ul style="margin: 0; padding-inline-start: 1.2rem; font-size: 0.8rem; line-height: 2;">
                                        @foreach ($mission->report['uncertain'] as $line)
                                            <li>{{ $line }}</li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif
                        </div>
                    @endif

                    {{-- proposed actions --}}
                    @if ($mission->actions->isNotEmpty())
                        <div class="mr-card mr-stack" style="gap: 0.7rem;">
                            <div class="mr-row">
                                <h2 class="mr-h" style="font-size: 1.05rem;">✅ اقدام‌های پیشنهادی تیم</h2>
                                <span class="mr-sub">هر اقدام فقط بعد از تأیید شما انجام می‌شود</span>
                            </div>
                            @foreach ($mission->actions as $action)
                                <div class="mr-item" wire:key="action-{{ $action->id }}">
                                    <div style="min-width: 0; flex: 1;">
                                        <div class="mr-row" style="justify-content: flex-start; gap: 0.5rem;">
                                            <strong style="font-size: 0.88rem;">{{ $action->summary }}</strong>
                                            <span @class(['mr-pill', 'warn' => $action->status === \App\Enums\AiMissionActionStatus::Pending, 'good' => $action->status === \App\Enums\AiMissionActionStatus::Executed, 'bad' => $action->status === \App\Enums\AiMissionActionStatus::Failed])>{{ $action->status->getLabel() }}</span>
                                        </div>
                                        <span class="mr-sub">پیشنهاد «{{ $action->profile?->displayName() ?? 'تیم' }}»@if (filled($action->reason)) · {{ $action->reason }}@endif</span>
                                        @if (filled($action->error))
                                            <span class="mr-sub" style="color: var(--mr-bad);">{{ $action->error }}</span>
                                        @endif
                                    </div>
                                    @if ($action->status === \App\Enums\AiMissionActionStatus::Pending)
                                        <div class="mr-row" style="gap: 0.4rem;">
                                            <button type="button" class="mr-btn mr-btn-primary" style="min-height: 2.5rem; padding: 0 1.1rem; font-size: 0.85rem;" wire:click="approveAction({{ $action->id }})" wire:loading.attr="disabled" wire:target="approveAction({{ $action->id }})">تأیید و انجام</button>
                                            <button type="button" class="mr-btn" wire:click="rejectAction({{ $action->id }})">رد</button>
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @endif

                    {{-- recurrence --}}
                    <div class="mr-card mr-stack" style="gap: 0.7rem;">
                        <div class="mr-row">
                            <div>
                                <h2 class="mr-h">🔁 تکرار خودکار</h2>
                                <p class="mr-sub">تیم سر وقت خودش یک دور تازه را شروع می‌کند و وقتی پیش‌نویس تصمیم آماده شد به شما خبر می‌دهد. هر بار تخته پاک و از نو پر می‌شود؛ نتیجهٔ دورهای قبل در «اجراهای قبلی» می‌ماند.</p>
                            </div>
                            @if ($mission->schedule && $mission->next_run_at)
                                <span class="mr-pill accent">اجرای بعدی: {{ $fa($mission->next_run_at->copy()->timezone(\App\Domain\Ai\Missions\MissionSchedule::TIMEZONE)->toJalali('Y/m/d H:i')) }}</span>
                            @else
                                <span class="mr-pill">خاموش</span>
                            @endif
                        </div>
                        <div style="display: flex; flex-wrap: wrap; gap: 0.6rem; align-items: flex-end;">
                            <label class="mr-label" style="min-width: 9rem;">هر چند وقت؟
                                <select id="schedule-frequency" class="mr-input" wire:model.live="scheduleFrequency">
                                    <option value="">تکرار نشود</option>
                                    @foreach (\App\Domain\Ai\Missions\MissionSchedule::FREQUENCIES as $value => $label)
                                        <option value="{{ $value }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </label>
                            @if ($scheduleFrequency === 'weekly')
                                <label class="mr-label" style="min-width: 9rem;">چه روزی؟
                                    <select id="schedule-weekday" class="mr-input" wire:model="scheduleWeekday">
                                        @foreach (\App\Domain\Ai\Missions\MissionSchedule::WEEKDAYS as $value => $label)
                                            <option value="{{ $value }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </label>
                            @elseif ($scheduleFrequency === 'monthly')
                                <label class="mr-label" style="min-width: 7rem;">روز ماه
                                    <input id="schedule-day" type="number" min="1" max="28" class="mr-input" wire:model="scheduleDay" />
                                </label>
                            @endif
                            @if ($scheduleFrequency !== '')
                                <label class="mr-label" style="min-width: 7rem;">ساعت
                                    <input id="schedule-time" type="time" class="mr-input" wire:model="scheduleTime" />
                                </label>
                            @endif
                            <button type="button" class="mr-btn" wire:click="saveSchedule">ذخیره</button>
                        </div>
                        @error('scheduleTime') <span class="mr-err">{{ $message }}</span> @enderror
                        <p class="mr-sub">ساعت به وقت تهران است. هر اجرای زمان‌بندی‌شده فقط یک دور می‌دهد و سقف توکن همان مأموریت را رعایت می‌کند.</p>
                    </div>

                    {{-- board --}}
                    <div class="mr-stack">
                        <h2 class="mr-h" style="font-size: 1.1rem;">🗂 تختهٔ تیم</h2>

                        @if ($mission->cards->where('kind', \App\Enums\AiMissionCardKind::Finding)->isEmpty())
                            <div class="mr-card mr-empty">
                                <div class="mr-avatars">
                                    @foreach ($mission->roles->take(4) as $role)
                                        @if ($role->profile)
                                            <x-ai-assistant-avatar :shape="$role->profile->avatar_shape" :color="$role->profile->avatar_color" :size="56" />
                                        @endif
                                    @endforeach
                                </div>
                                <div>
                                    <strong>تیم آمادهٔ کار است</strong>
                                    <p class="mr-sub">«{{ $mission->rounds_run === 0 ? 'شروع کار تیم' : 'دور بعدی' }}» را بزنید.</p>
                                </div>
                                <ol class="mr-howto mr-sub" style="padding-inline-start: 1.2rem;">
                                    <li>متخصص‌ها داده را بررسی و یافته‌هایشان را (با منبع) روی تخته می‌گذارند.</li>
                                    <li>منتقد ادعاهای ضعیف را زیر همان کارت نقد می‌کند.</li>
                                    <li>هماهنگ‌کننده پیش‌نویس تصمیم را بالای صفحه می‌نویسد.</li>
                                </ol>
                            </div>
                        @else
                            <div class="mr-columns">
                                @foreach ($specialistRoles as $role)
                                    @php $own = $mission->cards->where('ai_assistant_profile_id', $role->ai_assistant_profile_id)->where('kind', \App\Enums\AiMissionCardKind::Finding); @endphp
                                    <section class="mr-stack" style="gap: 0.6rem;" wire:key="column-{{ $role->id }}">
                                        <div class="mr-member">
                                            @if ($role->profile)
                                                <x-ai-assistant-avatar :shape="$role->profile->avatar_shape" :color="$role->profile->avatar_color" :size="38" :animated="false" />
                                            @endif
                                            <span style="min-width: 0; flex: 1; font-size: 0.85rem; font-weight: 700;">{{ $role->profile?->displayName() ?? 'دستیار' }}</span>
                                            <span class="mr-pill">{{ $fa($own->count()) }} یافته</span>
                                        </div>
                                        @forelse ($own as $card)
                                            <article @class(['mr-finding', 'rejected' => $card->status === \App\Enums\AiMissionCardStatus::Rejected, 'locked' => $card->status === \App\Enums\AiMissionCardStatus::Locked]) wire:key="card-{{ $card->id }}">
                                                <div class="mr-row" style="gap: 0.3rem;">
                                                    <span class="mr-row" style="gap: 0.3rem; justify-content: flex-start;">
                                                        <span @class(['mr-pill', 'good' => $card->confidence->value === 'high', 'warn' => $card->confidence->value === 'medium', 'bad' => $card->confidence->value === 'low'])>{{ $card->confidence->getLabel() }}</span>
                                                        @if ($card->status === \App\Enums\AiMissionCardStatus::Locked)
                                                            <span class="mr-pill good">🔒 تأییدشده توسط شما</span>
                                                        @elseif ($card->status === \App\Enums\AiMissionCardStatus::Rejected)
                                                            <span class="mr-pill bad">رد شده توسط شما</span>
                                                        @endif
                                                    </span>
                                                    <span class="mr-sub">دور {{ $fa($card->round) }}</span>
                                                </div>
                                                <span class="mr-claim">{{ $card->claim }}</span>
                                                @if ($card->unsourced)
                                                    <span class="mr-pill bad" style="justify-self: start;">بدون منبع</span>
                                                @elseif ($card->source)
                                                    <span class="mr-sub">منبع: {{ $card->source }}</span>
                                                @endif
                                                @if ($card->sourceChunk)
                                                    <details class="mr-sub" style="background: var(--mr-soft); border-radius: 0.6rem; padding: 0.4rem 0.7rem;">
                                                        <summary style="cursor: pointer; font-weight: 600;">📄 متن همین بخش از فایل را ببین</summary>
                                                        <p style="margin: 0.4rem 0 0; white-space: pre-line; line-height: 1.9;">{{ \Illuminate\Support\Str::limit($card->sourceChunk->content, 900, '…') }}</p>
                                                    </details>
                                                @endif
                                                @if ($card->status !== \App\Enums\AiMissionCardStatus::Rejected)
                                                    @foreach ($mission->cards->where('target_card_id', $card->id) as $critique)
                                                        <div class="mr-critique">🧐 {{ $critique->profile?->displayName() }}: {{ $critique->claim }}</div>
                                                    @endforeach
                                                @endif
                                                @if ($mission->status !== \App\Enums\AiMissionStatus::Running)
                                                    <div class="mr-actions">
                                                        @if ($card->status === \App\Enums\AiMissionCardStatus::Open)
                                                            <button type="button" class="mr-mini" wire:click="setCardStatus({{ $card->id }}, 'locked')">✓ تأیید می‌کنم</button>
                                                            <button type="button" class="mr-mini" wire:click="setCardStatus({{ $card->id }}, 'rejected')">✕ رد می‌کنم</button>
                                                        @else
                                                            <button type="button" class="mr-mini" wire:click="setCardStatus({{ $card->id }}, 'open')">↺ بازگرداندن</button>
                                                        @endif
                                                    </div>
                                                @endif
                                            </article>
                                        @empty
                                            <p class="mr-sub">هنوز یافته‌ای ثبت نکرده.</p>
                                        @endforelse
                                    </section>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    {{-- history --}}
                    @if ($mission->runs->isNotEmpty())
                        <div class="mr-card mr-stack" style="gap: 0.6rem;">
                            <h2 class="mr-h">🕘 اجراهای قبلی</h2>
                            @foreach ($mission->runs->take(10) as $run)
                                <details class="mr-role" wire:key="run-{{ $run->id }}">
                                    <summary style="cursor: pointer; display: flex; flex-wrap: wrap; gap: 0.5rem; align-items: center; font-size: 0.85rem;">
                                        <strong>{{ $fa($run->finished_at->copy()->timezone(\App\Domain\Ai\Missions\MissionSchedule::TIMEZONE)->toJalali('Y/m/d H:i')) }}</strong>
                                        <span class="mr-pill {{ $run->trigger === 'scheduled' ? 'accent' : '' }}">{{ $run->trigger === 'scheduled' ? 'زمان‌بندی‌شده' : 'دستی' }}</span>
                                        <span class="mr-pill">دور {{ $fa($run->round) }}</span>
                                        <span class="mr-pill {{ count($run->report['decisions'] ?? []) > 0 ? 'warn' : '' }}">{{ $fa(count($run->report['decisions'] ?? [])) }} پیشنهاد</span>
                                        <span class="mr-sub">{{ \Illuminate\Support\Str::limit($run->summary, 90) }}</span>
                                    </summary>
                                    <div class="mr-stack" style="gap: 0.5rem; margin-top: 0.6rem;">
                                        <p style="font-size: 0.85rem; line-height: 2; margin: 0;">{{ $run->report['summary'] ?? '' }}</p>
                                        @foreach ($run->report['decisions'] ?? [] as $index => $decision)
                                            <div class="mr-decision">
                                                <span class="mr-num">{{ $fa($index + 1) }}</span>
                                                <div><strong style="font-size: 0.88rem;">{{ $decision['title'] }}</strong>
                                                    @if (filled($decision['detail'] ?? null))<p class="mr-sub">{{ $decision['detail'] }}</p>@endif
                                                </div>
                                            </div>
                                        @endforeach
                                        @if (! empty($run->report['uncertain']))
                                            <ul style="margin: 0; padding-inline-start: 1.2rem; font-size: 0.78rem; line-height: 1.9;">
                                                @foreach ($run->report['uncertain'] as $line)<li>{{ $line }}</li>@endforeach
                                            </ul>
                                        @endif
                                        <span class="mr-sub">مصرف: {{ $fa(number_format($run->tokens_used)) }} توکن</span>
                                    </div>
                                </details>
                            @endforeach
                        </div>
                    @endif
                @else
                    @php
                        $dashboard = $this->getDashboard();
                        $pendingActions = $this->getPendingActions();
                    @endphp
                    @if ($missions->isEmpty())
                        <div class="mr-card mr-empty">
                            <div style="font-size: 2.2rem;">🚩</div>
                            <div>
                                <strong style="font-size: 1rem;">اتاق عملیات</strong>
                                <p class="mr-sub">یک هدف را به تیمی از دستیارهایتان بسپارید؛ آنها داده را بررسی می‌کنند، همدیگر را نقد می‌کنند و یک پیشنهاد تصمیم می‌نویسند. تصمیم و اجرا همیشه با خود شماست.</p>
                            </div>
                            <button type="button" class="mr-btn mr-btn-primary" wire:click="startCreate">＋ ساخت اولین مأموریت</button>
                        </div>
                    @else
                        <div class="mr-stack" @if ($dashboard['running'] !== []) wire:poll.5s @endif>
                            <div class="mr-tiles">
                                <div @class(['mr-tile-stat', 'warn' => $dashboard['tiles']['waiting'] > 0])><strong>{{ $fa($dashboard['tiles']['waiting']) }}</strong><span class="mr-sub">منتظر نظر شما</span></div>
                                <div @class(['mr-tile-stat', 'warn' => $dashboard['tiles']['actions'] > 0])><strong>{{ $fa($dashboard['tiles']['actions']) }}</strong><span class="mr-sub">اقدام منتظر تأیید</span></div>
                                <div @class(['mr-tile-stat', 'bad' => $dashboard['tiles']['problems'] > 0])><strong>{{ $fa($dashboard['tiles']['problems']) }}</strong><span class="mr-sub">ناموفق یا متوقف</span></div>
                                <div class="mr-tile-stat"><strong>{{ $fa($dashboard['tiles']['running']) }}</strong><span class="mr-sub">در حال اجرا</span></div>
                                <div class="mr-tile-stat"><strong>{{ $fa($dashboard['tiles']['recurring']) }}</strong><span class="mr-sub">تکرارشونده</span></div>
                                <div class="mr-tile-stat"><strong>{{ $fa(number_format($dashboard['tiles']['tokens_month'])) }}</strong><span class="mr-sub">توکن مصرف‌شده در این ماه</span></div>
                            </div>

                            @if ($pendingActions->isNotEmpty())
                                <div class="mr-card mr-stack" style="gap: 0.6rem; border-color: var(--mr-warn);">
                                    <div class="mr-row">
                                        <h2 class="mr-h">✅ صف تأیید اقدام‌ها</h2>
                                        <span class="mr-sub">تیم فقط پیشنهاد داده؛ تا تأیید نکنید چیزی تغییر نمی‌کند</span>
                                    </div>
                                    @foreach ($pendingActions as $action)
                                        <div class="mr-item" wire:key="queue-{{ $action->id }}">
                                            <div style="min-width: 0; flex: 1;">
                                                <strong style="font-size: 0.88rem;">{{ $action->summary }}</strong>
                                                <span class="mr-sub">مأموریت «{{ $action->mission->title }}» · پیشنهاد «{{ $action->profile?->displayName() ?? 'تیم' }}»@if (filled($action->reason)) · {{ $action->reason }}@endif</span>
                                            </div>
                                            <div class="mr-row" style="gap: 0.4rem;">
                                                <button type="button" class="mr-btn mr-btn-primary" style="min-height: 2.5rem; padding: 0 1.1rem; font-size: 0.85rem;" wire:click="approveAction({{ $action->id }})" wire:loading.attr="disabled" wire:target="approveAction({{ $action->id }})">تأیید و انجام</button>
                                                <button type="button" class="mr-btn" wire:click="rejectAction({{ $action->id }})">رد</button>
                                                <button type="button" class="mr-btn mr-btn-quiet" wire:click="openMission({{ $action->ai_mission_id }})">مأموریت</button>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @endif

                            <div class="mr-card mr-stack" style="gap: 0.6rem;">
                                <h2 class="mr-h">🔔 نیاز به شما</h2>
                                @forelse ($dashboard['attention'] as $item)
                                    <div class="mr-item" wire:key="attention-{{ $item['id'] }}">
                                        <div style="min-width: 0;">
                                            <div class="mr-row" style="justify-content: flex-start; gap: 0.5rem;">
                                                <strong style="font-size: 0.88rem;">{{ $item['title'] }}</strong>
                                                <span class="mr-pill {{ $item['tone'] }}">{{ $item['status'] }}</span>
                                            </div>
                                            <span class="mr-sub">{{ $item['detail'] }} · {{ $item['at'] }}</span>
                                        </div>
                                        <button type="button" class="mr-btn" wire:click="openMission({{ $item['id'] }})">باز کردن</button>
                                    </div>
                                @empty
                                    <p class="mr-sub">✓ چیزی منتظر شما نیست.</p>
                                @endforelse
                            </div>

                            @if ($dashboard['running'] !== [])
                                <div class="mr-card mr-stack" style="gap: 0.6rem;">
                                    <h2 class="mr-h">⏳ در حال اجرا</h2>
                                    @foreach ($dashboard['running'] as $item)
                                        <div class="mr-item" wire:key="running-{{ $item['id'] }}" style="display: grid;">
                                            <div class="mr-row">
                                                <strong style="font-size: 0.88rem;">{{ $item['title'] }}</strong>
                                                <button type="button" class="mr-btn" wire:click="openMission({{ $item['id'] }})">باز کردن</button>
                                            </div>
                                            <span class="mr-sub">{{ $item['step'] }}</span>
                                            <div style="height: 0.35rem; border-radius: 9999px; background: var(--mr-line); overflow: hidden;"><div style="height: 100%; width: {{ $item['percent'] }}%; background: var(--mr-accent);"></div></div>
                                        </div>
                                    @endforeach
                                </div>
                            @endif

                            @if ($dashboard['recurring'] !== [])
                                <div class="mr-card mr-stack" style="gap: 0.6rem;">
                                    <h2 class="mr-h">🔁 تکرارشونده‌ها</h2>
                                    @foreach ($dashboard['recurring'] as $item)
                                        <div class="mr-item" wire:key="recurring-{{ $item['id'] }}">
                                            <div>
                                                <strong style="font-size: 0.88rem;">{{ $item['title'] }}</strong>
                                                <div class="mr-sub">{{ $item['every'] }}@if ($item['next']) · اجرای بعدی: {{ $fa($item['next']) }}@endif @if ($item['last']) · آخرین اجرا: {{ $item['last'] }}@endif</div>
                                            </div>
                                            <button type="button" class="mr-btn" wire:click="openMission({{ $item['id'] }})">باز کردن</button>
                                        </div>
                                    @endforeach
                                </div>
                            @endif

                            <div class="mr-card mr-stack" style="gap: 0.6rem;">
                                <h2 class="mr-h">🕘 آخرین نتیجه‌ها</h2>
                                @forelse ($dashboard['recent'] as $index => $item)
                                    <div class="mr-item" wire:key="recent-{{ $index }}">
                                        <div style="min-width: 0;">
                                            <div class="mr-row" style="justify-content: flex-start; gap: 0.5rem;">
                                                <strong style="font-size: 0.88rem;">{{ $item['title'] }}</strong>
                                                <span class="mr-pill {{ $item['scheduled'] ? 'accent' : '' }}">{{ $item['scheduled'] ? 'زمان‌بندی‌شده' : 'دستی' }}</span>
                                                <span class="mr-pill {{ $item['decisions'] > 0 ? 'warn' : '' }}">{{ $fa($item['decisions']) }} پیشنهاد</span>
                                            </div>
                                            <span class="mr-sub">{{ $fa($item['when']) }} · {{ $item['summary'] }}</span>
                                        </div>
                                        <button type="button" class="mr-btn" wire:click="openMission({{ $item['mission_id'] }})">باز کردن</button>
                                    </div>
                                @empty
                                    <p class="mr-sub">هنوز دوری تمام نشده است.</p>
                                @endforelse
                            </div>
                        </div>
                    @endif
                @endif
            </main>
        </div>

        {{-- ===== assistant picker ===== --}}
        @if ($pickingFor)
            @php
                $slotLabel = match (true) {
                    $pickingFor === 'coordinator' => 'هماهنگ‌کننده',
                    $pickingFor === 'critic' => 'منتقد',
                    default => 'متخصص',
                };
                $currentId = match (true) {
                    $pickingFor === 'coordinator' => $coordinatorId,
                    $pickingFor === 'critic' => $criticId,
                    default => $specialists[(int) str($pickingFor)->after(':')->toString()]['profile_id'] ?? null,
                };
                $slotNeeds = str_starts_with($pickingFor, 'specialist:') ? ($specialists[(int) str($pickingFor)->after(':')->toString()]['needs'] ?? []) : [];
                $fits = fn ($profile) => $slotNeeds === [] || array_diff($slotNeeds, $profile->allowed_tools ?? []) === [];
                $pickerProfiles = $slotNeeds === [] ? $profiles : $profiles->sortByDesc(fn ($profile) => $fits($profile) ? 1 : 0)->values();
            @endphp
            <div class="mr-overlay" wire:click.self="closePicker" role="dialog" aria-modal="true" aria-label="انتخاب دستیار">
                <div class="mr-modal mr-stack">
                    <div class="mr-row">
                        <div>
                            <h3 class="mr-h">دستیار «{{ $slotLabel }}» را انتخاب کنید</h3>
                            <p class="mr-sub">دستیارهایی که روی داده کار می‌کنند باید ابزار داشته باشند؛ برچسب‌های هر کارت ابزارهای او را نشان می‌دهد.</p>
                        </div>
                        <button type="button" class="mr-btn mr-btn-quiet" wire:click="closePicker">بستن</button>
                    </div>

                    @if ($profiles->isEmpty())
                        <p class="mr-sub">هنوز دستیاری ندارید. اول از «مرکز دستیاران هوشمند» یک دستیار بسازید.</p>
                    @endif

                    <div class="mr-grid">
                        @foreach ($pickerProfiles as $profile)
                            @php $taken = in_array($profile->id, $usedIds, true) && $profile->id !== $currentId; @endphp
                            <button type="button" class="mr-tile" wire:key="pick-{{ $profile->id }}" wire:click="pickProfile({{ $profile->id }})" @disabled($taken)>
                                <x-ai-assistant-avatar :shape="$profile->avatar_shape" :color="$profile->avatar_color" :size="56" :animated="false" />
                                <strong style="font-size: 0.88rem;">{{ $profile->displayName() }}</strong>
                                @if ($profile->description)
                                    <span class="mr-sub" style="display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;">{{ $profile->description }}</span>
                                @endif
                                <span class="mr-chips">
                                    @forelse ($toolLabels($profile) as $label)
                                        <span class="mr-pill accent">{{ $label }}</span>
                                    @empty
                                        <span class="mr-pill">بدون ابزار</span>
                                    @endforelse
                                    @if ($taken)
                                        <span class="mr-pill warn">در تیم هست</span>
                                    @elseif ($slotNeeds !== [] && $fits($profile))
                                        <span class="mr-pill good">✓ مناسب این جایگاه</span>
                                    @elseif ($slotNeeds !== [])
                                        <span class="mr-pill warn">ابزار لازم را ندارد</span>
                                    @endif
                                </span>
                            </button>
                        @endforeach
                    </div>
                </div>
            </div>
        @endif
    </div>
</x-filament-panels::page>
