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
        .mr-slot { display: flex; align-items: center; gap: 0.8rem; width: 100%; padding: 0.7rem 0.9rem; border-radius: 0.8rem; border: 2px dashed var(--mr-line); background: var(--mr-card); color: var(--mr-dim); font-size: 0.85rem; cursor: pointer; text-align: start; }
        .mr-slot.filled { border-style: solid; border-color: var(--mr-line); color: var(--mr-text); }
        .mr-slot:hover { border-color: var(--mr-accent); }
        .mr-overlay { position: fixed; inset: 0; z-index: 60; background: rgb(8 14 26 / 0.6); display: grid; place-items: center; padding: 1rem; }
        .mr-modal { width: min(46rem, 100%); max-height: 86vh; overflow: auto; background: var(--mr-card); border: 1px solid var(--mr-line); border-radius: 1.1rem; padding: 1.25rem; }
        .mr-grid { display: grid; gap: 0.7rem; grid-template-columns: repeat(auto-fill, minmax(13rem, 1fr)); }
        .mr-tile { display: grid; justify-items: center; gap: 0.5rem; text-align: center; padding: 1rem 0.8rem; border-radius: 0.9rem; border: 1px solid var(--mr-line); background: var(--mr-soft); color: inherit; cursor: pointer; }
        .mr-tile:hover:not([disabled]) { border-color: var(--mr-accent); background: var(--mr-accent-soft); }
        .mr-tile[disabled] { opacity: 0.45; cursor: not-allowed; }
        .mr-chips { display: flex; flex-wrap: wrap; gap: 0.3rem; justify-content: center; }
        .mr-team { display: grid; gap: 0.75rem; grid-template-columns: minmax(0, 1fr); align-items: stretch; }
        @media (min-width: 900px) { .mr-team.has-critic { grid-template-columns: minmax(0, 1.4fr) auto minmax(0, 1fr) auto minmax(0, 1fr); } .mr-team.no-critic { grid-template-columns: minmax(0, 1.4fr) auto minmax(0, 1fr); } }
        .mr-arrow { display: none; align-self: center; color: var(--mr-dim); font-size: 1.4rem; }
        @media (min-width: 900px) { .mr-arrow { display: block; } }
        .mr-member { display: flex; align-items: center; gap: 0.6rem; padding: 0.5rem 0.7rem; border-radius: 0.8rem; background: var(--mr-soft); }
        .mr-decision { display: grid; grid-template-columns: 2rem minmax(0, 1fr); gap: 0.8rem; padding: 0.9rem 1rem; border-radius: 0.9rem; background: var(--mr-card); border: 1px solid var(--mr-line); }
        .mr-finding { border: 1px solid var(--mr-line); border-radius: 0.85rem; padding: 0.8rem 0.9rem; background: var(--mr-card); display: grid; gap: 0.5rem; font-size: 0.85rem; line-height: 1.9; }
        .mr-critique { background: var(--mr-bad-soft); color: var(--mr-bad); border-radius: 0.6rem; padding: 0.45rem 0.7rem; font-size: 0.78rem; line-height: 1.8; }
        .mr-columns { display: grid; gap: 1rem; grid-template-columns: repeat(auto-fit, minmax(17rem, 1fr)); align-items: start; }
        .mr-empty { text-align: center; padding: 2rem 1rem; display: grid; gap: 1rem; justify-items: center; }
        .mr-howto { display: grid; gap: 0.6rem; text-align: start; max-width: 28rem; }
        .mr-spinner { width: 1rem; height: 1rem; border-radius: 9999px; border: 2px solid rgb(255 255 255 / 0.4); border-top-color: #fff; animation: mr-spin 0.8s linear infinite; }
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
                        <div class="mr-row">
                            <div>
                                <h2 class="mr-h" style="font-size: 1.15rem;">مأموریت جدید</h2>
                                <p class="mr-sub">سه قدم: بگویید چه می‌خواهید، تیم را انتخاب کنید و شروع کنید.</p>
                            </div>
                            <button type="button" class="mr-btn" wire:click="applyTemplate">✨ شروع سریع با نمونه «رزرو و پشتیبانی هفتهٔ آینده»</button>
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
                                            <div style="display: flex; gap: 0.5rem; align-items: stretch;">
                                                <button type="button" wire:click="openPicker('specialist:{{ $index }}')" @class(['mr-slot', 'filled' => $chosen])>
                                                    @if ($chosen)
                                                        <x-ai-assistant-avatar :shape="$chosen->avatar_shape" :color="$chosen->avatar_color" :size="44" :animated="false" />
                                                        <span style="min-width: 0;"><strong style="display: block;">{{ $chosen->displayName() }}</strong><span class="mr-sub">برای تغییر بزنید</span></span>
                                                    @else
                                                        <span style="font-size: 1.4rem;">＋</span> دستیار را انتخاب کنید
                                                    @endif
                                                </button>
                                                @if (count($specialists) > 1)
                                                    <button type="button" class="mr-btn mr-btn-quiet" wire:click="removeSpecialist({{ $index }})" aria-label="حذف این متخصص">حذف</button>
                                                @endif
                                            </div>
                                            @if ($chosen && $toolLabels($chosen)->isEmpty())
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
                                                <span><strong style="display: block;">{{ $chosen->displayName() }}</strong><span class="mr-sub">برای تغییر بزنید</span></span>
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
                                            <span><strong style="display: block;">{{ $chosen->displayName() }}</strong><span class="mr-sub">برای تغییر بزنید</span></span>
                                        @else
                                            <span style="font-size: 1.4rem;">＋</span> دستیار را انتخاب کنید
                                        @endif
                                    </button>
                                    <input id="coordinator-directive" type="text" class="mr-input" wire:model="coordinatorDirective" placeholder="چه نوع جمع‌بندی بنویسد؟ (اختیاری)" />
                                </div>
                            </div>
                        </section>

                        {{-- step 3 --}}
                        <section class="mr-step">
                            <span class="mr-num">۳</span>
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
                            <button type="button" class="mr-btn mr-btn-quiet" wire:click="$set('showCreate', false)">انصراف</button>
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

                    {{-- result --}}
                    @if ($mission->report)
                        <div class="mr-card mr-stack" style="border-color: var(--mr-warn);">
                            <div class="mr-row">
                                <h2 class="mr-h" style="font-size: 1.1rem;">📋 نتیجه: پیش‌نویس تصمیم</h2>
                                <span class="mr-pill warn">فقط پیشنهاد است؛ هیچ اقدامی انجام نشده</span>
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
                                            <article class="mr-finding" wire:key="card-{{ $card->id }}">
                                                <div class="mr-row" style="gap: 0.3rem;">
                                                    <span @class(['mr-pill', 'good' => $card->confidence->value === 'high', 'warn' => $card->confidence->value === 'medium', 'bad' => $card->confidence->value === 'low'])>{{ $card->confidence->getLabel() }}</span>
                                                    <span class="mr-sub">دور {{ $fa($card->round) }}</span>
                                                </div>
                                                <span>{{ $card->claim }}</span>
                                                @if ($card->unsourced)
                                                    <span class="mr-pill bad" style="justify-self: start;">بدون منبع</span>
                                                @elseif ($card->source)
                                                    <span class="mr-sub">منبع: {{ $card->source }}</span>
                                                @endif
                                                @foreach ($mission->cards->where('target_card_id', $card->id) as $critique)
                                                    <div class="mr-critique">🧐 {{ $critique->profile?->displayName() }}: {{ $critique->claim }}</div>
                                                @endforeach
                                            </article>
                                        @empty
                                            <p class="mr-sub">هنوز یافته‌ای ثبت نکرده.</p>
                                        @endforelse
                                    </section>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @else
                    <div class="mr-card mr-empty">
                        <div style="font-size: 2.2rem;">🚩</div>
                        <div>
                            <strong style="font-size: 1rem;">اتاق عملیات</strong>
                            <p class="mr-sub">یک هدف را به تیمی از دستیارهایتان بسپارید؛ آنها داده را بررسی می‌کنند، همدیگر را نقد می‌کنند و یک پیشنهاد تصمیم می‌نویسند. تصمیم و اجرا همیشه با خود شماست.</p>
                        </div>
                        <button type="button" class="mr-btn mr-btn-primary" wire:click="startCreate">＋ ساخت اولین مأموریت</button>
                    </div>
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
                        @foreach ($profiles as $profile)
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
