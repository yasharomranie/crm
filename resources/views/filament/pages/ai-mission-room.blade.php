<x-filament-panels::page>
    @php
        $missions = $this->getMissions();
        $mission = $this->getSelectedMission();
        $profiles = $this->getProfiles();
        $roleLabels = collect(\App\Enums\AiMissionRoleType::cases())->mapWithKeys(fn ($case) => [$case->value => $case->getLabel()]);
    @endphp

    <div class="grid gap-4 lg:grid-cols-[18rem_minmax(0,1fr)]">
        {{-- missions --}}
        <div class="space-y-3">
            <x-filament::button wire:click="startCreate" icon="heroicon-o-plus" class="w-full">مأموریت جدید</x-filament::button>

            <div class="space-y-2">
                @forelse ($missions as $item)
                    <button type="button" wire:click="openMission({{ $item->id }})" wire:key="mission-{{ $item->id }}"
                        @class([
                            'w-full rounded-xl border px-3 py-2.5 text-start text-sm transition',
                            'border-primary-500 bg-primary-50 dark:bg-primary-500/10' => $mission?->id === $item->id,
                            'border-gray-200 bg-white hover:bg-gray-50 dark:border-white/10 dark:bg-gray-900 dark:hover:bg-white/5' => $mission?->id !== $item->id,
                        ])>
                        <div class="font-medium text-gray-950 dark:text-white">{{ $item->title }}</div>
                        <div class="mt-1 flex items-center justify-between gap-2">
                            <x-filament::badge :color="$item->status->getColor()" size="sm">{{ $item->status->getLabel() }}</x-filament::badge>
                            <span class="text-xs text-gray-500 dark:text-gray-400">دور {{ \Illuminate\Support\Number::toPersian($item->rounds_run) }} از {{ \Illuminate\Support\Number::toPersian($item->max_rounds) }}</span>
                        </div>
                    </button>
                @empty
                    <p class="rounded-xl border border-dashed border-gray-300 p-4 text-sm text-gray-500 dark:border-white/10 dark:text-gray-400">هنوز مأموریتی ندارید. یک هدف به چند دستیار بسپارید تا با هم روی آن کار کنند و گزارشی برای تصمیم‌گیری بدهند.</p>
                @endforelse
            </div>
        </div>

        {{-- main --}}
        <div class="min-w-0 space-y-4">
            @if ($showCreate)
                <div class="space-y-4 rounded-xl border border-gray-200 bg-white p-4 dark:border-white/10 dark:bg-gray-900 sm:p-6">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <h3 class="text-base font-semibold text-gray-950 dark:text-white">مأموریت جدید</h3>
                        <x-filament::button wire:click="applyTemplate" color="gray" size="sm" icon="heroicon-o-sparkles">پر کردن از قالب «رزرو و پشتیبانی هفتهٔ آینده»</x-filament::button>
                    </div>

                    <div class="grid gap-3">
                        <label class="grid gap-1 text-sm">
                            <span class="text-gray-700 dark:text-gray-300">عنوان</span>
                            <input id="mission-title" type="text" wire:model="missionTitle" class="rounded-lg border-gray-300 text-sm dark:border-white/10 dark:bg-gray-800" />
                            @error('missionTitle') <span class="text-xs text-danger-600">{{ $message }}</span> @enderror
                        </label>
                        <label class="grid gap-1 text-sm">
                            <span class="text-gray-700 dark:text-gray-300">هدف (چه نتیجه‌ای می‌خواهید؟)</span>
                            <textarea id="mission-goal" rows="3" wire:model="missionGoal" class="rounded-lg border-gray-300 text-sm dark:border-white/10 dark:bg-gray-800"></textarea>
                            @error('missionGoal') <span class="text-xs text-danger-600">{{ $message }}</span> @enderror
                        </label>
                        <div class="grid gap-3 sm:grid-cols-[minmax(0,1fr)_9rem]">
                            <label class="grid gap-1 text-sm">
                                <span class="text-gray-700 dark:text-gray-300">شرط پایان (اختیاری)</span>
                                <input id="mission-end" type="text" wire:model="missionEnd" class="rounded-lg border-gray-300 text-sm dark:border-white/10 dark:bg-gray-800" />
                            </label>
                            <label class="grid gap-1 text-sm">
                                <span class="text-gray-700 dark:text-gray-300">حداکثر دور</span>
                                <input id="mission-rounds" type="number" min="1" max="5" wire:model="maxRounds" class="rounded-lg border-gray-300 text-sm dark:border-white/10 dark:bg-gray-800" />
                            </label>
                        </div>
                    </div>

                    <div class="space-y-2">
                        <div class="text-sm font-medium text-gray-950 dark:text-white">تیم</div>
                        @foreach ($roleRows as $index => $row)
                            <div wire:key="role-row-{{ $index }}" class="grid gap-2 rounded-lg bg-gray-50 p-3 dark:bg-white/5 sm:grid-cols-[10rem_12rem_minmax(0,1fr)_auto]">
                                <select id="role-profile-{{ $index }}" wire:model="roleRows.{{ $index }}.profile_id" class="rounded-lg border-gray-300 text-sm dark:border-white/10 dark:bg-gray-800" aria-label="دستیار">
                                    <option value="">دستیار…</option>
                                    @foreach ($profiles as $profile)
                                        <option value="{{ $profile->id }}">{{ $profile->displayName() }}</option>
                                    @endforeach
                                </select>
                                <select id="role-type-{{ $index }}" wire:model="roleRows.{{ $index }}.role" class="rounded-lg border-gray-300 text-sm dark:border-white/10 dark:bg-gray-800" aria-label="نقش">
                                    @foreach ($roleLabels as $value => $label)
                                        <option value="{{ $value }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                                <input id="role-directive-{{ $index }}" type="text" placeholder="دستور این نقش (اختیاری)" wire:model="roleRows.{{ $index }}.directive" class="rounded-lg border-gray-300 text-sm dark:border-white/10 dark:bg-gray-800" />
                                <button type="button" wire:click="removeRoleRow({{ $index }})" class="text-xs text-danger-600 hover:underline">حذف</button>
                            </div>
                        @endforeach
                        @error('roleRows.*.profile_id') <p class="text-xs text-danger-600">برای هر نقش یک دستیار انتخاب کنید.</p> @enderror
                        <x-filament::button wire:click="addRoleRow" color="gray" size="sm">افزودن عضو</x-filament::button>
                    </div>

                    <p class="text-xs text-gray-500 dark:text-gray-400">تیم فقط می‌خواند و گزارش می‌دهد؛ هیچ داده‌ای را تغییر نمی‌دهد. هر عضو فقط ابزارهای خواندنیِ تنظیم‌شدهٔ همان دستیار را دارد و به اندازهٔ مجوزهای خود شما.</p>

                    <x-filament::button wire:click="createMission">ساخت مأموریت</x-filament::button>
                </div>
            @elseif ($mission)
                <div class="space-y-4 rounded-xl border border-gray-200 bg-white p-4 dark:border-white/10 dark:bg-gray-900 sm:p-6">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0">
                            <h3 class="text-base font-semibold text-gray-950 dark:text-white">{{ $mission->title }}</h3>
                            <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">{{ $mission->goal }}</p>
                            @if ($mission->end_condition)
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">شرط پایان: {{ $mission->end_condition }}</p>
                            @endif
                        </div>
                        <div class="flex items-center gap-2">
                            <x-filament::badge :color="$mission->status->getColor()">{{ $mission->status->getLabel() }}</x-filament::badge>
                            <x-filament::button wire:click="runRound" wire:loading.attr="disabled" wire:target="runRound" :disabled="! $mission->canRunAnotherRound()" icon="heroicon-o-play">
                                <span wire:loading.remove wire:target="runRound">یک دور اجرا</span>
                                <span wire:loading wire:target="runRound">در حال اجرا… (ممکن است چند دقیقه طول بکشد)</span>
                            </x-filament::button>
                            <x-filament::button tag="a" :href="route('filament.admin.pages.ai-team-map', ['mission' => $mission->id])" color="gray" size="sm" icon="heroicon-o-share">نمایش روی نقشه</x-filament::button>
                            <x-filament::button color="danger" outlined size="sm" wire:click="deleteMission({{ $mission->id }})" wire:confirm="این مأموریت و تختهٔ آن حذف شود؟">حذف</x-filament::button>
                        </div>
                    </div>

                    @if ($mission->last_error && $mission->status === \App\Enums\AiMissionStatus::Failed)
                        <p class="rounded-lg bg-danger-50 px-3 py-2 text-sm text-danger-700 dark:bg-danger-500/10 dark:text-danger-300">{{ $mission->last_error }}</p>
                    @endif

                    <div class="flex flex-wrap gap-2">
                        @foreach ($mission->roles as $role)
                            <span class="inline-flex items-center gap-2 rounded-full bg-gray-100 py-1 pe-3 ps-1 text-xs dark:bg-white/5">
                                @if ($role->profile)
                                    <x-ai-assistant-avatar :shape="$role->profile->avatar_shape" :color="$role->profile->avatar_color" :size="24" :animated="false" />
                                @endif
                                <span class="font-medium text-gray-950 dark:text-white">{{ $role->profile?->displayName() ?? 'دستیار حذف‌شده' }}</span>
                                <span class="text-gray-500 dark:text-gray-400">{{ $role->role->getLabel() }}</span>
                            </span>
                        @endforeach
                    </div>
                </div>

                @if ($mission->report)
                    <div class="space-y-3 rounded-xl border border-warning-300 bg-warning-50/60 p-4 dark:border-warning-500/30 dark:bg-warning-500/10 sm:p-6">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <h3 class="text-base font-semibold text-gray-950 dark:text-white">پیش‌نویس تصمیم</h3>
                            <span class="text-xs text-gray-600 dark:text-gray-300">این‌ها فقط پیشنهادند؛ هیچ اقدامی انجام نشده است.</span>
                        </div>
                        <p class="text-sm text-gray-800 dark:text-gray-100">{{ $mission->report['summary'] ?? '' }}</p>

                        @if (! empty($mission->report['decisions']))
                            <ol class="space-y-2">
                                @foreach ($mission->report['decisions'] as $decision)
                                    <li class="rounded-lg bg-white/70 px-3 py-2 text-sm dark:bg-gray-900/60">
                                        <div class="font-medium text-gray-950 dark:text-white">{{ $decision['title'] }}</div>
                                        @if (filled($decision['detail'] ?? null))
                                            <div class="mt-0.5 text-xs text-gray-600 dark:text-gray-300">{{ $decision['detail'] }}</div>
                                        @endif
                                    </li>
                                @endforeach
                            </ol>
                        @endif

                        @if (! empty($mission->report['uncertain']))
                            <div>
                                <div class="mb-1 text-sm font-medium text-gray-950 dark:text-white">چه چیزی مطمئن نیست</div>
                                <ul class="list-disc space-y-1 ps-5 text-xs text-gray-700 dark:text-gray-300">
                                    @foreach ($mission->report['uncertain'] as $line)
                                        <li>{{ $line }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                    </div>
                @endif

                <div class="space-y-4 rounded-xl border border-gray-200 bg-white p-4 dark:border-white/10 dark:bg-gray-900 sm:p-6">
                    <h3 class="text-base font-semibold text-gray-950 dark:text-white">تخته</h3>

                    @php $byRound = $mission->cards->groupBy('round'); @endphp
                    @forelse ($byRound as $round => $cards)
                        <div wire:key="round-{{ $round }}" class="space-y-2">
                            <div class="text-xs font-semibold text-gray-500 dark:text-gray-400">دور {{ \Illuminate\Support\Number::toPersian($round) }}</div>
                            @foreach ($cards->where('kind', \App\Enums\AiMissionCardKind::Finding) as $card)
                                <div wire:key="card-{{ $card->id }}" class="rounded-lg border border-gray-200 p-3 text-sm dark:border-white/10">
                                    <div class="flex flex-wrap items-center gap-2 text-xs text-gray-500 dark:text-gray-400">
                                        <span class="font-medium text-gray-950 dark:text-white">{{ $card->profile?->displayName() ?? 'دستیار' }}</span>
                                        <x-filament::badge :color="$card->confidence->getColor()" size="sm">{{ $card->confidence->getLabel() }}</x-filament::badge>
                                        @if ($card->unsourced)
                                            <x-filament::badge color="danger" size="sm">بدون منبع</x-filament::badge>
                                        @endif
                                    </div>
                                    <p class="mt-1.5 text-gray-800 dark:text-gray-100">{{ $card->claim }}</p>
                                    @if ($card->source)
                                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">منبع: {{ $card->source }}</p>
                                    @endif
                                    @foreach ($cards->where('target_card_id', $card->id) as $critique)
                                        <p class="mt-2 rounded-md bg-danger-50 px-2.5 py-1.5 text-xs text-danger-800 dark:bg-danger-500/10 dark:text-danger-200">
                                            نقد {{ $critique->profile?->displayName() }}: {{ $critique->claim }}
                                        </p>
                                    @endforeach
                                </div>
                            @endforeach
                        </div>
                    @empty
                        <p class="text-sm text-gray-500 dark:text-gray-400">تخته خالی است. «یک دور اجرا» را بزنید تا متخصص‌ها یافته‌هایشان را (هرکدام با منبع) روی تخته بگذارند.</p>
                    @endforelse
                </div>
            @else
                <div class="rounded-xl border border-dashed border-gray-300 p-8 text-center text-sm text-gray-500 dark:border-white/10 dark:text-gray-400">
                    یک مأموریت را از فهرست انتخاب کنید یا مأموریت جدید بسازید.
                </div>
            @endif
        </div>
    </div>
</x-filament-panels::page>
