<x-filament-panels::page>
    @php
        $activeProfile = $this->activeProfile();
    @endphp

    <style>
        .ai-markdown :where(h1, h2, h3) { font-weight: 700; margin-block: .5rem; }
        .ai-markdown p { margin-block: .4rem; line-height: 1.75; }
        .ai-markdown ul, .ai-markdown ol { margin-inline-start: 1.25rem; margin-block: .4rem; }
        .ai-markdown ul { list-style-type: disc; }
        .ai-markdown ol { list-style-type: decimal; }
        .ai-markdown strong { font-weight: 700; }
        .ai-swatch { transition: transform .15s ease, box-shadow .15s ease; }
        .ai-swatch:hover { transform: scale(1.08); }
        .ai-shape-pick { transition: transform .15s ease; }
        .ai-shape-pick:hover { transform: translateY(-2px); }
    </style>

    @if (! $activeProfile)
        {{-- ============ Hub grid ============ --}}
        <div class="space-y-6">
            @if (! \App\Models\AiProvider::active())
                <div class="flex items-center gap-3 rounded-xl border border-warning-200 bg-warning-50 p-4 text-sm text-warning-800 dark:border-warning-500/20 dark:bg-warning-500/10 dark:text-warning-300">
                    <x-heroicon-o-exclamation-triangle class="h-5 w-5 shrink-0" />
                    <span>هیچ ارائه‌دهندهٔ هوش مصنوعی فعالی تنظیم نشده است.</span>
                </div>
            @endif

            <div>
                <h2 class="mb-3 text-sm font-semibold text-gray-950 dark:text-white">دستیارهای اصلی</h2>
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($this->builtInAssistants() as $assistant)
                        <a
                            href="{{ $assistant['url'] }}"
                            class="flex items-start gap-3 rounded-2xl border border-gray-200 bg-white p-4 transition hover:-translate-y-0.5 hover:shadow-md dark:border-white/10 dark:bg-gray-900"
                        >
                            <x-ai-assistant-avatar :shape="$assistant['shape']" :color="$assistant['color']" :size="56" />
                            <div class="min-w-0">
                                <p class="text-sm font-semibold text-gray-950 dark:text-white">{{ $assistant['label'] }}</p>
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $assistant['description'] }}</p>
                            </div>
                        </a>
                    @endforeach
                </div>
            </div>

            <div>
                <div class="mb-3 flex items-center justify-between">
                    <h2 class="text-sm font-semibold text-gray-950 dark:text-white">دستیارهای اختصاصی شما</h2>
                </div>

                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($this->getMyProfiles() as $profile)
                        <div
                            wire:key="profile-{{ $profile->id }}"
                            class="group relative flex items-start gap-3 rounded-2xl border border-gray-200 bg-white p-4 transition hover:-translate-y-0.5 hover:shadow-md dark:border-white/10 dark:bg-gray-900"
                        >
                            <button type="button" wire:click="openProfile({{ $profile->id }})" class="absolute inset-0 cursor-pointer rounded-2xl" aria-label="باز کردن گفتگو با {{ $profile->displayName() }}"></button>

                            <x-ai-assistant-avatar :shape="$profile->avatar_shape" :color="$profile->avatar_color" :size="56" />

                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-1.5">
                                    <p class="truncate text-sm font-semibold text-gray-950 dark:text-white">{{ $profile->displayName() }}</p>
                                    @if ($profile->is_shared)
                                        <span class="shrink-0 rounded-full bg-primary-50 px-1.5 py-0.5 text-[10px] font-medium text-primary-700 dark:bg-primary-500/10 dark:text-primary-400">عمومی</span>
                                    @endif
                                </div>
                                <p class="mt-1 line-clamp-2 text-xs text-gray-500 dark:text-gray-400">{{ \Illuminate\Support\Str::limit($profile->description, 90) }}</p>
                                <p class="mt-1.5 text-[11px] text-gray-400">به‌روزرسانی {{ $profile->updated_at->diffForHumans() }}</p>
                            </div>

                            <div class="relative z-10 flex shrink-0 flex-col items-center gap-1 opacity-100 transition md:opacity-0 md:group-hover:opacity-100">
                                <button
                                    type="button"
                                    wire:click="duplicateProfile({{ $profile->id }})"
                                    aria-label="کپی از این دستیار"
                                    title="کپی از این دستیار"
                                    class="rounded-lg p-1.5 text-gray-300 hover:bg-primary-50 hover:text-primary-600 dark:text-gray-600 dark:hover:bg-primary-500/10"
                                >
                                    <x-heroicon-o-document-duplicate class="h-4 w-4" />
                                </button>

                                @if ($profile->created_by === auth()->id())
                                    <button
                                        type="button"
                                        wire:click="openEditForm({{ $profile->id }})"
                                        aria-label="ویرایش دستیار"
                                        title="ویرایش دستیار"
                                        class="rounded-lg p-1.5 text-gray-300 hover:bg-gray-100 hover:text-gray-600 dark:text-gray-600 dark:hover:bg-white/5"
                                    >
                                        <x-heroicon-o-pencil-square class="h-4 w-4" />
                                    </button>

                                    <button
                                        type="button"
                                        wire:click="deleteProfile({{ $profile->id }})"
                                        wire:confirm="این دستیار و تمام گفتگوهایش حذف شود؟"
                                        aria-label="حذف دستیار"
                                        title="حذف دستیار"
                                        class="rounded-lg p-1.5 text-gray-300 hover:bg-danger-50 hover:text-danger-600 dark:text-gray-600 dark:hover:bg-danger-500/10"
                                    >
                                        <x-heroicon-o-trash class="h-4 w-4" />
                                    </button>
                                @endif
                            </div>
                        </div>
                    @endforeach

                    <button
                        type="button"
                        wire:click="openCreateForm"
                        class="flex min-h-[7rem] flex-col items-center justify-center gap-2 rounded-2xl border-2 border-dashed border-gray-300 p-4 text-gray-400 transition hover:border-primary-400 hover:text-primary-600 dark:border-white/10 dark:text-gray-500"
                    >
                        <x-heroicon-o-plus-circle class="h-7 w-7" />
                        <span class="text-sm font-medium">دستیار جدید</span>
                    </button>
                </div>
            </div>
        </div>

        {{-- ============ Create assistant panel ============ --}}
        @if ($showCreateForm)
            <div class="fixed inset-0 z-50 flex items-center justify-center bg-gray-950/50 p-4" wire:click.self="closeCreateForm">
                <div class="max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-2xl bg-white p-5 shadow-xl dark:bg-gray-900">
                    <div class="mb-4 flex items-center justify-between">
                        <h3 class="text-base font-semibold text-gray-950 dark:text-white">{{ $editingProfileId ? 'ویرایش دستیار' : 'ساخت دستیار جدید' }}</h3>
                        <button type="button" wire:click="closeCreateForm" class="rounded-lg p-1 text-gray-400 hover:bg-gray-100 dark:hover:bg-white/5">
                            <x-heroicon-o-x-mark class="h-5 w-5" />
                        </button>
                    </div>

                    <div class="flex flex-col items-center gap-2 pb-4">
                        <x-ai-assistant-avatar :shape="$newShape" :color="$newColor" :size="96" wire:key="preview-{{ $newShape }}-{{ $newColor }}" />
                        <p class="text-xs text-gray-400">پیش‌نمایش زنده</p>
                    </div>

                    <div class="space-y-4">
                        <div>
                            <p class="mb-2 text-xs font-medium text-gray-600 dark:text-gray-300">شکل آواتار</p>
                            <div class="flex gap-2 overflow-x-auto pb-1">
                                @foreach (\App\Support\AiAssistantAvatar::SHAPES as $shapeKey => $shapeLabel)
                                    <button
                                        type="button"
                                        wire:click="$set('newShape', '{{ $shapeKey }}')"
                                        title="{{ $shapeLabel }}"
                                        class="ai-shape-pick shrink-0 rounded-xl p-1.5 {{ $newShape === $shapeKey ? 'ring-2 ring-primary-500' : '' }}"
                                    >
                                        <x-ai-assistant-avatar :shape="$shapeKey" :color="$newColor" :size="40" :animated="false" />
                                    </button>
                                @endforeach
                            </div>
                        </div>

                        <div>
                            <p class="mb-2 text-xs font-medium text-gray-600 dark:text-gray-300">رنگ</p>
                            <div class="flex gap-2 overflow-x-auto pb-1">
                                @foreach (\App\Support\AiAssistantAvatar::COLORS as $colorKey => $colorLabel)
                                    <button
                                        type="button"
                                        wire:click="$set('newColor', '{{ $colorKey }}')"
                                        title="{{ $colorLabel }}"
                                        style="background-color: {{ \App\Support\AiAssistantAvatar::shade($colorKey, 500) }};"
                                        class="ai-swatch h-8 w-8 shrink-0 rounded-full {{ $newColor === $colorKey ? 'ring-2 ring-offset-2 ring-primary-500 dark:ring-offset-gray-900' : '' }}"
                                    ></button>
                                @endforeach
                            </div>
                        </div>

                        <div>
                            <label for="newName" class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-300">نام (اختیاری)</label>
                            <input
                                id="newName"
                                type="text"
                                wire:model="newName"
                                placeholder="مثلاً «پشتیبان مهمانان»"
                                class="bg-white text-gray-950 placeholder:text-gray-400 dark:text-white block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500 dark:border-white/10 dark:bg-white/5"
                            />
                        </div>

                        <div>
                            <label for="newDescription" class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-300">دستورالعمل عملکرد (همین متن، پرامت سیستمی دستیار می‌شود)</label>
                            <textarea
                                id="newDescription"
                                wire:model="newDescription"
                                rows="5"
                                placeholder="وظیفه را دقیق و عملیاتی بنویس: کار این دستیار چیست، چه لحنی دارد، چه کاری نباید انجام دهد. از صفت‌های کلی مثل «فوق‌العاده باش» یا «حرفه‌ای باش» پرهیز کن."
                                class="bg-white text-gray-950 placeholder:text-gray-400 dark:text-white block w-full resize-y rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500 dark:border-white/10 dark:bg-white/5"
                            ></textarea>
                            @error('newDescription')
                                <p class="mt-1 text-xs text-danger-600 dark:text-danger-400">{{ $message }}</p>
                            @enderror
                        </div>

                        <label class="flex items-center justify-between gap-2 rounded-lg border border-gray-200 px-3 py-2.5 dark:border-white/10">
                            <span class="text-sm text-gray-700 dark:text-gray-300">
                                {{ $newIsShared ? 'عمومی (همهٔ کاربران می‌بینند)' : 'خصوصی (فقط شما می‌بینید)' }}
                            </span>
                            <input type="checkbox" wire:model="newIsShared" class="fi-toggle-input h-5 w-9 rounded-full" />
                        </label>

                        <div>
                            <label for="newSpecialties" class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-300">تخصص‌ها (اختیاری، با ویرگول جدا کن)</label>
                            <input
                                id="newSpecialties"
                                type="text"
                                wire:model="newSpecialties"
                                placeholder="مثلاً: حقوقی، قرارداد"
                                class="bg-white text-gray-950 placeholder:text-gray-400 dark:text-white block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500 dark:border-white/10 dark:bg-white/5"
                            />
                            <p class="mt-1 text-[11px] text-gray-400">دستیارهای دیگر، در صورت نیاز، بر اساس همین تخصص‌ها می‌توانند از این دستیار سؤال بپرسند (مشورت بین‌دستیاری).</p>
                        </div>

                        @unless ($editingProfileId)
                            <div>
                                <p class="mb-2 text-xs font-medium text-gray-600 dark:text-gray-300">یا از یک الگوی آماده شروع کن</p>
                                <div class="flex flex-wrap gap-2">
                                    @foreach ($this->starterTemplates() as $template)
                                        <button
                                            type="button"
                                            wire:click="applyStarterTemplate(@js($template['name']), @js($template['description']), '{{ $template['shape'] }}', '{{ $template['color'] }}')"
                                            class="flex items-center gap-1.5 rounded-full border border-gray-200 px-2.5 py-1.5 text-xs text-gray-600 transition hover:border-primary-300 hover:text-primary-600 dark:border-white/10 dark:text-gray-300"
                                        >
                                            <x-ai-assistant-avatar :shape="$template['shape']" :color="$template['color']" :size="18" :animated="false" />
                                            {{ $template['name'] }}
                                        </button>
                                    @endforeach
                                </div>
                            </div>
                        @endunless
                    </div>

                    <div class="mt-5 flex justify-end gap-2">
                        <button type="button" wire:click="closeCreateForm" class="rounded-lg px-4 py-2 text-sm text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-white/5">انصراف</button>
                        <button type="button" wire:click="{{ $editingProfileId ? 'updateProfile' : 'createProfile' }}" class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-500">{{ $editingProfileId ? 'ذخیره تغییرات' : 'ساخت دستیار' }}</button>
                    </div>
                </div>
            </div>
        @endif
    @else
        {{-- ============ Chat view for the active profile ============ --}}
        @php
            $conversations = $this->getProfileConversations();
            $selected = $this->getSelectedProfileConversation();
            $visibleMessages = $this->getProfileMessages()->filter(fn ($message) => $message->role === \App\Enums\AiMessageRole::User
                || ($message->role === \App\Enums\AiMessageRole::Assistant && filled($message->content))
                || ($message->role === \App\Enums\AiMessageRole::Tool && $this->describeToolActivity($message) !== null));
            $hasHandoff = $visibleMessages->contains(fn ($message) => $message->tool_name === 'handoff_to_assistant');
            $forwardTargets = $this->getForwardTargets();
            $lastAssistantMessageId = optional($visibleMessages->filter(fn ($message) => $message->role === \App\Enums\AiMessageRole::Assistant)->last())->id;
        @endphp

        <div class="space-y-3" x-data="aiAssistantsHubThread()" x-on:message-sent.window="streaming = false; scrollToBottom(true)">
            <div class="flex items-center gap-2">
                <button
                    type="button"
                    wire:click="closeProfile"
                    class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg text-gray-500 transition hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-white/5"
                    aria-label="بازگشت به مرکز دستیاران"
                    title="بازگشت به مرکز دستیاران"
                >
                    <x-heroicon-o-arrow-right class="h-5 w-5" />
                </button>
                <x-ai-assistant-avatar :shape="$activeProfile->avatar_shape" :color="$activeProfile->avatar_color" :size="36" :animated="false" />
                <span class="text-sm font-semibold text-gray-950 dark:text-white">{{ $activeProfile->displayName() }}</span>
            </div>

            <div class="flex h-[calc(100dvh-14rem)] min-h-[26rem] overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm md:h-[calc(100dvh-16rem)] md:min-h-[30rem] dark:border-white/10 dark:bg-gray-900">
                <div
                    class="w-full shrink-0 flex-col border-gray-200 md:flex md:w-auto md:max-w-xs md:border-e lg:max-w-sm dark:border-white/10"
                    :class="mobileView === 'thread' ? 'hidden md:flex' : 'flex'"
                >
                    <div class="flex items-center justify-between gap-2 border-b border-gray-200 p-3 dark:border-white/10">
                        <span class="text-sm font-semibold text-gray-950 dark:text-white">گفتگوها</span>
                        <button
                            type="button"
                            wire:click="newProfileConversation"
                            x-on:click="mobileView = 'thread'"
                            aria-label="گفتگوی جدید"
                            class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary-600 text-white transition hover:bg-primary-500"
                        >
                            <x-heroicon-o-plus-circle class="h-5 w-5" />
                        </button>
                    </div>

                    <div class="flex-1 space-y-1 overflow-y-auto p-2">
                        @forelse ($conversations as $conversation)
                            @php $isActive = $selected && $selected->id === $conversation->id; @endphp
                            <div
                                wire:key="conversation-{{ $conversation->id }}"
                                x-data="{ renaming: false }"
                                @class([
                                    'group relative flex items-center rounded-lg',
                                    'bg-primary-50 dark:bg-primary-500/10' => $isActive,
                                ])
                            >
                                <button
                                    type="button"
                                    wire:click="selectProfileConversation({{ $conversation->id }})"
                                    x-on:click="mobileView = 'thread'"
                                    x-show="! renaming"
                                    class="flex min-w-0 flex-1 items-center gap-2 rounded-lg px-2.5 py-3 text-start text-sm transition hover:bg-gray-50 md:py-2.5 dark:hover:bg-white/5 {{ $isActive ? 'font-medium text-primary-700 dark:text-primary-400' : 'text-gray-700 dark:text-gray-300' }}"
                                >
                                    <x-heroicon-o-chat-bubble-left-right class="h-4 w-4 shrink-0" />
                                    <span class="truncate">{{ $conversation->title ?: 'گفتگوی جدید' }}</span>
                                </button>

                                <div x-show="renaming" x-cloak class="min-w-0 flex-1 py-1 ps-2.5 pe-1">
                                    <input
                                        type="text"
                                        x-ref="renameInput{{ $conversation->id }}"
                                        value="{{ $conversation->title }}"
                                        x-on:keydown.enter="$wire.renameConversation({{ $conversation->id }}, $event.target.value); renaming = false"
                                        x-on:keydown.escape="renaming = false"
                                        x-on:blur="renaming = false"
                                        class="min-w-0 w-full rounded-lg border border-gray-300 bg-white px-2 py-1 text-xs text-gray-950 dark:border-white/10 dark:bg-white/5 dark:text-white"
                                    />
                                </div>

                                <div x-show="! renaming" class="relative z-10 flex shrink-0 items-center gap-0.5 pe-1 opacity-100 md:opacity-0 md:group-hover:opacity-100">
                                    <button
                                        type="button"
                                        x-on:click="renaming = true; $nextTick(() => $refs['renameInput{{ $conversation->id }}'].focus())"
                                        aria-label="تغییر نام گفتگو"
                                        title="تغییر نام گفتگو"
                                        class="rounded-lg p-1 text-gray-300 hover:bg-gray-100 hover:text-gray-600 dark:text-gray-600 dark:hover:bg-white/5"
                                    >
                                        <x-heroicon-o-pencil-square class="h-3.5 w-3.5" />
                                    </button>
                                    <button
                                        type="button"
                                        wire:click="deleteConversation({{ $conversation->id }})"
                                        wire:confirm="این گفتگو حذف شود؟"
                                        aria-label="حذف گفتگو"
                                        title="حذف گفتگو"
                                        class="rounded-lg p-1 text-gray-300 hover:bg-danger-50 hover:text-danger-600 dark:text-gray-600 dark:hover:bg-danger-500/10"
                                    >
                                        <x-heroicon-o-trash class="h-3.5 w-3.5" />
                                    </button>
                                </div>
                            </div>
                        @empty
                            <div class="flex h-full flex-col items-center justify-center gap-2 px-4 py-12 text-center">
                                <x-heroicon-o-sparkles class="h-8 w-8 text-gray-300 dark:text-gray-600" />
                                <p class="text-xs text-gray-500 dark:text-gray-400">هنوز گفتگویی نداشته‌اید.</p>
                            </div>
                        @endforelse
                    </div>
                </div>

                <div class="min-w-0 flex-1 flex-col md:flex" :class="mobileView === 'list' ? 'hidden' : 'flex'">
                    <div class="flex items-center gap-1 border-b border-gray-200 p-2 dark:border-white/10">
                        <button
                            type="button"
                            x-on:click="mobileView = 'list'"
                            class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg text-gray-500 transition hover:bg-gray-100 md:hidden dark:text-gray-400 dark:hover:bg-white/5"
                            aria-label="بازگشت به فهرست گفتگوها"
                        >
                            <x-heroicon-o-chevron-right class="h-5 w-5" />
                        </button>
                        <span class="min-w-0 flex-1 truncate text-sm font-semibold text-gray-950 dark:text-white">
                            {{ $selected?->title ?: 'گفتگوی جدید' }}
                        </span>

                        @if ($activeProfile->created_by === auth()->id())
                            <div class="relative shrink-0" x-data="{ open: false }">
                                <button
                                    type="button"
                                    wire:click="loadModelOptionsForProfile"
                                    x-on:click="open = ! open"
                                    title="مدل زبانی این دستیار"
                                    class="flex max-w-[6.5rem] items-center gap-1.5 rounded-lg border border-gray-200 px-2.5 py-1.5 text-xs text-gray-600 hover:border-primary-300 hover:text-primary-600 dark:border-white/10 dark:text-gray-300"
                                >
                                    <x-heroicon-o-cpu-chip class="h-3.5 w-3.5 shrink-0" />
                                    <span class="truncate">{{ $activeProfile->model ?: 'پیش‌فرض' }}</span>
                                </button>
                                <div x-show="open" x-cloak x-on:click.outside="open = false" class="absolute left-0 top-full z-20 mt-1 max-h-64 w-56 max-w-[calc(100vw-2rem)] overflow-y-auto rounded-lg border border-gray-200 bg-white py-1 shadow-lg dark:border-white/10 dark:bg-gray-800">
                                    <button type="button" wire:click="updateProfileModel('')" x-on:click="open = false" @class(['flex w-full items-center px-3 py-2 text-start text-xs hover:bg-gray-50 dark:hover:bg-white/5', 'font-medium text-primary-600 dark:text-primary-400' => blank($activeProfile->model), 'text-gray-700 dark:text-gray-200' => filled($activeProfile->model)])>
                                        پیش‌فرض ارائه‌دهنده
                                    </button>
                                    @foreach ($this->modelOptionsForProfile as $modelId)
                                        <button type="button" wire:click="updateProfileModel('{{ $modelId }}')" x-on:click="open = false" @class(['flex w-full items-center px-3 py-2 text-start text-xs hover:bg-gray-50 dark:hover:bg-white/5', 'font-medium text-primary-600 dark:text-primary-400' => $activeProfile->model === $modelId, 'text-gray-700 dark:text-gray-200' => $activeProfile->model !== $modelId])>
                                            {{ $modelId }}
                                        </button>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        @if ($activeProfile->created_by === auth()->id())
                            <div class="relative shrink-0" x-data="{ open: false }">
                                <button
                                    type="button"
                                    x-on:click="open = true"
                                    title="دانش‌نامهٔ این دستیار"
                                    class="relative flex h-9 w-9 shrink-0 items-center justify-center rounded-lg text-gray-500 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-white/5"
                                >
                                    <x-heroicon-o-book-open class="h-4 w-4" />
                                    @if ($this->pendingKnowledgeCount() > 0)
                                        <span class="absolute -end-0.5 -top-0.5 flex h-4 w-4 items-center justify-center rounded-full bg-danger-500 text-[9px] font-bold text-white">{{ $this->pendingKnowledgeCount() }}</span>
                                    @endif
                                </button>

                                <div x-show="open" x-cloak x-on:click.self="open = false" class="fixed inset-0 z-50 flex items-center justify-center bg-gray-950/50 p-4">
                                    <div class="flex max-h-[85vh] w-full max-w-lg flex-col overflow-hidden rounded-2xl bg-white shadow-xl dark:bg-gray-900">
                                        <div class="flex shrink-0 items-center justify-between border-b border-gray-100 px-5 py-4 dark:border-white/5">
                                            <h3 class="text-base font-semibold text-gray-950 dark:text-white">دانش‌نامهٔ {{ $activeProfile->displayName() }}</h3>
                                            <button type="button" x-on:click="open = false" class="rounded-lg p-1 text-gray-400 hover:bg-gray-100 dark:hover:bg-white/5">
                                                <x-heroicon-o-x-mark class="h-5 w-5" />
                                            </button>
                                        </div>

                                        <div class="overflow-y-auto p-5">

                                        <button
                                            type="button"
                                            wire:click="toggleKnowledgeAutoSave"
                                            class="mb-4 flex w-full items-center justify-between gap-2 rounded-lg border border-gray-200 px-3 py-2.5 text-start dark:border-white/10"
                                        >
                                            <span class="text-sm text-gray-700 dark:text-gray-300">ذخیرهٔ خودکار نکات پیشنهادی دستیار (بدون نیاز به تأیید شما)</span>
                                            <span @class([
                                                'inline-flex h-5 w-9 shrink-0 items-center rounded-full px-0.5 transition',
                                                'bg-primary-600 justify-end' => $activeProfile->knowledge_auto_save,
                                                'bg-gray-300 justify-start dark:bg-white/10' => ! $activeProfile->knowledge_auto_save,
                                            ])>
                                                <span class="h-4 w-4 rounded-full bg-white shadow"></span>
                                            </span>
                                        </button>

                                        <div class="mb-4 rounded-lg border border-gray-200 p-3 dark:border-white/10">
                                            <button
                                                type="button"
                                                wire:click="toggleProfileConsultable"
                                                class="flex w-full items-center justify-between gap-2 text-start"
                                            >
                                                <span class="text-sm text-gray-700 dark:text-gray-300">قابل‌مشورت توسط دستیارهای دیگر</span>
                                                <span @class([
                                                    'inline-flex h-5 w-9 shrink-0 items-center rounded-full px-0.5 transition',
                                                    'bg-primary-600 justify-end' => $activeProfile->consultable,
                                                    'bg-gray-300 justify-start dark:bg-white/10' => ! $activeProfile->consultable,
                                                ])>
                                                    <span class="h-4 w-4 rounded-full bg-white shadow"></span>
                                                </span>
                                            </button>

                                            <label for="profileSpecialties" class="mb-1 mt-3 block text-xs font-medium text-gray-600 dark:text-gray-300">تخصص‌ها (با ویرگول جدا کن)</label>
                                            <div class="flex gap-2">
                                                <input
                                                    id="profileSpecialties"
                                                    type="text"
                                                    x-ref="specialtiesInput"
                                                    value="{{ implode('، ', $activeProfile->specialties ?? []) }}"
                                                    placeholder="مثلاً: حقوقی، قرارداد"
                                                    class="bg-white text-gray-950 placeholder:text-gray-400 dark:text-white min-w-0 flex-1 rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-white/10 dark:bg-white/5"
                                                />
                                                <button
                                                    type="button"
                                                    x-on:click="$wire.updateProfileSpecialties($refs.specialtiesInput.value)"
                                                    class="shrink-0 rounded-lg bg-primary-600 px-3 py-2 text-xs font-medium text-white hover:bg-primary-500"
                                                >
                                                    ذخیره
                                                </button>
                                            </div>

                                            @php $consultants = $this->getAvailableConsultants(); @endphp
                                            @if ($consultants->isNotEmpty())
                                                <p class="mt-3 text-[11px] text-gray-400">
                                                    این دستیار، در صورت نیاز، می‌تواند از این دستیارها مشورت بگیرد:
                                                    {{ $consultants->map(fn ($c) => $c->displayName())->implode('، ') }}
                                                </p>
                                            @endif

                                            @php $consultationHistory = $this->getConsultationHistory(); @endphp
                                            @if ($consultationHistory->isNotEmpty())
                                                <div class="mt-3 border-t border-gray-100 pt-3 dark:border-white/5">
                                                    <p class="mb-2 text-[11px] font-medium text-gray-500 dark:text-gray-400">تاریخچهٔ مشورت‌ها</p>
                                                    <div class="space-y-2">
                                                        @foreach ($consultationHistory as $consultation)
                                                            @php $askedByThisProfile = $consultation->consulting_ai_assistant_profile_id === $activeProfile->id; @endphp
                                                            <div class="rounded-lg bg-gray-50 p-2 text-[11px] dark:bg-white/5">
                                                                <p class="text-gray-500 dark:text-gray-400">
                                                                    {{ $askedByThisProfile ? '🔄 از «'.($consultation->consultedProfile?->displayName() ?? 'دستیاری حذف‌شده').'» پرسید' : '🔄 «'.($consultation->consultingProfile?->displayName() ?? 'دستیاری حذف‌شده').'» از این دستیار پرسید' }}
                                                                </p>
                                                                <p class="mt-1 text-gray-700 dark:text-gray-300">{{ \Illuminate\Support\Str::limit($consultation->question, 100) }}</p>
                                                            </div>
                                                        @endforeach
                                                    </div>
                                                </div>
                                            @endif
                                        </div>

                                        <button
                                            type="button"
                                            wire:click="toggleProfileFetchUrl"
                                            class="mb-4 flex w-full items-center justify-between gap-2 rounded-lg border border-gray-200 px-3 py-2.5 text-start dark:border-white/10"
                                        >
                                            <span class="text-sm text-gray-700 dark:text-gray-300">دسترسی به خواندن صفحات وب (fetch_url)</span>
                                            <span @class([
                                                'inline-flex h-5 w-9 shrink-0 items-center rounded-full px-0.5 transition',
                                                'bg-primary-600 justify-end' => $activeProfile->fetch_url_enabled,
                                                'bg-gray-300 justify-start dark:bg-white/10' => ! $activeProfile->fetch_url_enabled,
                                            ])>
                                                <span class="h-4 w-4 rounded-full bg-white shadow"></span>
                                            </span>
                                        </button>

                                        @php $crmToolCatalog = $this->getCrmToolCatalogForOwner(); @endphp
                                        @if ($crmToolCatalog !== [])
                                            <div class="mb-4 rounded-lg border border-gray-200 p-3 dark:border-white/10">
                                                <p class="mb-2 text-sm text-gray-700 dark:text-gray-300">دسترسی به ابزارهای واقعی CRM (فقط‌خواندنی)</p>
                                                <div class="space-y-1">
                                                    @foreach ($crmToolCatalog as $toolName => $toolLabel)
                                                        @php $isGranted = in_array($toolName, $activeProfile->allowed_tools ?? [], true); @endphp
                                                        <button
                                                            type="button"
                                                            wire:click="toggleAllowedCrmTool('{{ $toolName }}')"
                                                            class="flex w-full items-center justify-between gap-2 rounded-lg px-2 py-1.5 text-start hover:bg-gray-50 dark:hover:bg-white/5"
                                                        >
                                                            <span class="text-xs text-gray-600 dark:text-gray-300">{{ $toolLabel }}</span>
                                                            <span @class([
                                                                'inline-flex h-4 w-7 shrink-0 items-center rounded-full px-0.5 transition',
                                                                'bg-primary-600 justify-end' => $isGranted,
                                                                'bg-gray-300 justify-start dark:bg-white/10' => ! $isGranted,
                                                            ])>
                                                                <span class="h-3 w-3 rounded-full bg-white shadow"></span>
                                                            </span>
                                                        </button>
                                                    @endforeach
                                                </div>
                                            </div>
                                        @endif

                                        @php
                                            $knowledgeEntries = $this->getKnowledgeEntries();
                                            $pendingEntries = $knowledgeEntries->where('status', \App\Enums\AiKnowledgeEntryStatus::Pending);
                                            $approvedEntries = $knowledgeEntries->where('status', \App\Enums\AiKnowledgeEntryStatus::Approved);
                                        @endphp

                                        @if ($pendingEntries->isNotEmpty())
                                            <div class="mb-4">
                                                <p class="mb-2 text-xs font-medium text-gray-600 dark:text-gray-300">پیشنهادهای دستیار برای بررسی ({{ $pendingEntries->count() }})</p>
                                                <div class="space-y-2">
                                                    @foreach ($pendingEntries as $entry)
                                                        <div wire:key="pending-{{ $entry->id }}" class="rounded-lg border border-warning-200 bg-warning-50 p-3 dark:border-warning-500/20 dark:bg-warning-500/10" x-data="{ editing: false }">
                                                            <div x-show="! editing" class="ai-markdown text-sm text-gray-800 dark:text-gray-100">{!! \App\Support\SafeMarkdown::toHtml($entry->content) !!}</div>
                                                            <div x-show="editing" x-cloak>
                                                                <textarea x-ref="editInput{{ $entry->id }}" rows="3" class="w-full min-w-0 rounded-lg border border-gray-300 bg-white px-2 py-1 text-sm text-gray-950 dark:border-white/10 dark:bg-white/5 dark:text-white">{{ $entry->content }}</textarea>
                                                            </div>
                                                            <div class="mt-2 flex gap-2" x-show="! editing">
                                                                <button type="button" wire:click="approveKnowledgeEntry({{ $entry->id }})" class="rounded-lg bg-success-600 px-2.5 py-1 text-xs font-medium text-white hover:bg-success-500">تأیید</button>
                                                                <button type="button" x-on:click="editing = true" class="rounded-lg border border-gray-300 px-2.5 py-1 text-xs text-gray-600 hover:bg-gray-100 dark:border-white/10 dark:text-gray-300 dark:hover:bg-white/5">ویرایش</button>
                                                                <button type="button" wire:click="deleteKnowledgeEntry({{ $entry->id }})" class="rounded-lg border border-gray-300 px-2.5 py-1 text-xs text-gray-600 hover:bg-gray-100 dark:border-white/10 dark:text-gray-300 dark:hover:bg-white/5">رد</button>
                                                            </div>
                                                            <div class="mt-2 flex gap-2" x-show="editing" x-cloak>
                                                                <button type="button" x-on:click="$wire.updateKnowledgeEntry({{ $entry->id }}, $refs['editInput{{ $entry->id }}'].value); editing = false" class="rounded-lg bg-primary-600 px-2.5 py-1 text-xs font-medium text-white hover:bg-primary-500">ذخیره</button>
                                                                <button type="button" x-on:click="editing = false" class="rounded-lg border border-gray-300 px-2.5 py-1 text-xs text-gray-600 hover:bg-gray-100 dark:border-white/10 dark:text-gray-300 dark:hover:bg-white/5">انصراف</button>
                                                            </div>
                                                        </div>
                                                    @endforeach
                                                </div>
                                            </div>
                                        @endif

                                        <div class="mb-4">
                                            <p class="mb-2 text-xs font-medium text-gray-600 dark:text-gray-300">دانستنی‌های تأییدشده ({{ $approvedEntries->count() }})</p>
                                            @forelse ($approvedEntries as $entry)
                                                @php
                                                    $isLong = mb_strlen($entry->content) > 140;
                                                    $entryHtml = \App\Support\SafeMarkdown::toHtml($entry->content);
                                                @endphp
                                                <div wire:key="approved-{{ $entry->id }}" class="group flex items-start justify-between gap-2 border-b border-gray-100 py-2 dark:border-white/5" x-data="{ expanded: false, editing: false }">
                                                    <div class="min-w-0 flex-1">
                                                        <div x-show="! editing" class="ai-markdown text-sm text-gray-700 dark:text-gray-300" :class="{{ $isLong ? 'true' : 'false' }} && ! expanded ? 'line-clamp-4' : ''">{!! $entryHtml !!}</div>
                                                        <div x-show="editing" x-cloak>
                                                            <textarea x-ref="editInput{{ $entry->id }}" rows="3" class="w-full min-w-0 rounded-lg border border-gray-300 bg-white px-2 py-1 text-sm text-gray-950 dark:border-white/10 dark:bg-white/5 dark:text-white">{{ $entry->content }}</textarea>
                                                            <div class="mt-1 flex gap-1">
                                                                <button type="button" x-on:click="$wire.updateKnowledgeEntry({{ $entry->id }}, $refs['editInput{{ $entry->id }}'].value); editing = false" class="rounded-lg bg-primary-600 px-2 py-1 text-[11px] font-medium text-white hover:bg-primary-500">ذخیره</button>
                                                                <button type="button" x-on:click="editing = false" class="rounded-lg border border-gray-300 px-2 py-1 text-[11px] text-gray-600 hover:bg-gray-100 dark:border-white/10 dark:text-gray-300 dark:hover:bg-white/5">انصراف</button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="flex shrink-0 items-start gap-1" x-show="! editing">
                                                        @if ($isLong)
                                                            <button type="button" x-on:click="expanded = ! expanded" class="shrink-0 rounded-lg p-1 text-[11px] font-medium text-primary-600 hover:bg-primary-50 dark:text-primary-400 dark:hover:bg-primary-500/10" x-text="expanded ? 'کمتر' : 'بیشتر'"></button>
                                                        @endif
                                                        <button type="button" x-on:click="editing = true" class="relative shrink-0 rounded-lg p-1 text-gray-300 opacity-100 hover:bg-gray-100 hover:text-gray-600 md:opacity-0 md:group-hover:opacity-100 dark:text-gray-600 dark:hover:bg-white/5">
                                                            <x-heroicon-o-pencil-square class="h-3.5 w-3.5" />
                                                        </button>
                                                        <button type="button" wire:click="deleteKnowledgeEntry({{ $entry->id }})" class="relative shrink-0 rounded-lg p-1 text-gray-300 opacity-100 hover:bg-danger-50 hover:text-danger-600 md:opacity-0 md:group-hover:opacity-100 dark:text-gray-600 dark:hover:bg-danger-500/10">
                                                            <x-heroicon-o-trash class="h-3.5 w-3.5" />
                                                        </button>
                                                    </div>
                                                </div>
                                            @empty
                                                <p class="text-xs text-gray-400">هنوز دانشی ثبت نشده - یا خودتان یکی اضافه کنید، یا بگذارید دستیار حین گفتگو یاد بگیرد.</p>
                                            @endforelse
                                        </div>

                                        <div>
                                            <label for="newKnowledgeContent" class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-300">افزودن نکتهٔ جدید</label>
                                            <div class="flex gap-2">
                                                <input
                                                    id="newKnowledgeContent"
                                                    type="text"
                                                    x-ref="knowledgeInput"
                                                    placeholder="مثلاً «نرخ کمیسیون فروش ۵ درصد است»"
                                                    x-on:keydown.enter.prevent="$wire.addManualKnowledgeEntry($refs.knowledgeInput.value); $refs.knowledgeInput.value = ''"
                                                    class="bg-white text-gray-950 placeholder:text-gray-400 dark:text-white min-w-0 flex-1 rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-white/10 dark:bg-white/5"
                                                />
                                                <button
                                                    type="button"
                                                    x-on:click="$wire.addManualKnowledgeEntry($refs.knowledgeInput.value); $refs.knowledgeInput.value = ''"
                                                    class="shrink-0 rounded-lg bg-primary-600 px-3 py-2 text-xs font-medium text-white hover:bg-primary-500"
                                                >
                                                    افزودن
                                                </button>
                                            </div>
                                        </div>

                                        <div class="mt-4 border-t border-gray-100 pt-4 dark:border-white/5">
                                            <label for="knowledgeUploadFile" class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-300">افزودن از فایل (txt، docx یا pdf - حداکثر ۱۰ مگابایت)</label>
                                            <div class="flex gap-2">
                                                <input
                                                    id="knowledgeUploadFile"
                                                    type="file"
                                                    wire:model="knowledgeUploadFile"
                                                    accept=".txt,.docx,.pdf"
                                                    class="bg-white text-gray-950 placeholder:text-gray-400 dark:text-white min-w-0 flex-1 rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-white/10 dark:bg-white/5"
                                                />
                                                <button
                                                    type="button"
                                                    wire:click="processKnowledgeUpload"
                                                    wire:loading.attr="disabled"
                                                    wire:target="processKnowledgeUpload,knowledgeUploadFile"
                                                    class="shrink-0 rounded-lg bg-primary-600 px-3 py-2 text-xs font-medium text-white hover:bg-primary-500 disabled:cursor-not-allowed disabled:opacity-50"
                                                >
                                                    استخراج نکات
                                                </button>
                                            </div>
                                            @error('knowledgeUploadFile')
                                                <p class="mt-1 text-xs text-danger-600">{{ $message }}</p>
                                            @enderror
                                        </div>

                                        @php
                                            $ocrUploads = $this->getOcrUploadsForActiveProfile();
                                            $hasActiveOcrUpload = $ocrUploads->whereIn('status', [\App\Enums\AiAssistantOcrUploadStatus::Pending, \App\Enums\AiAssistantOcrUploadStatus::Processing])->isNotEmpty();
                                        @endphp
                                        @if ($ocrUploads->isNotEmpty())
                                            <div class="mt-4 border-t border-gray-100 pt-4 dark:border-white/5" @if ($hasActiveOcrUpload) wire:poll.5s @endif>
                                                <p class="mb-2 text-xs font-medium text-gray-600 dark:text-gray-300">وضعیت فایل‌های اسکن‌شده (OCR)</p>
                                                <div class="space-y-1.5">
                                                    @foreach ($ocrUploads as $upload)
                                                        <div wire:key="ocr-upload-{{ $upload->id }}" class="flex items-center justify-between gap-2 text-xs">
                                                            <span class="truncate text-gray-700 dark:text-gray-300">{{ $upload->original_filename }}</span>
                                                            @if ($upload->status === \App\Enums\AiAssistantOcrUploadStatus::Completed)
                                                                <span class="shrink-0 rounded-full bg-success-50 px-2 py-0.5 font-medium text-success-700 dark:bg-success-500/10 dark:text-success-400">✅ {{ $upload->note_count }} نکته</span>
                                                            @elseif ($upload->status === \App\Enums\AiAssistantOcrUploadStatus::Failed)
                                                                <span class="shrink-0 rounded-full bg-danger-50 px-2 py-0.5 font-medium text-danger-700 dark:bg-danger-500/10 dark:text-danger-400" title="{{ $upload->error_message }}">❌ خطا</span>
                                                            @else
                                                                <span class="shrink-0 rounded-full bg-warning-50 px-2 py-0.5 font-medium text-warning-700 dark:bg-warning-500/10 dark:text-warning-400">⏳ در حال OCR...</span>
                                                            @endif
                                                        </div>
                                                    @endforeach
                                                </div>
                                            </div>
                                        @endif

                                        <div class="mt-4 border-t border-gray-100 pt-4 dark:border-white/5">
                                            <p class="mb-2 text-xs font-medium text-gray-600 dark:text-gray-300">منابع تحت نظر (هر روز بررسی می‌شود؛ در صورت تغییر، نکته‌ای پیشنهاد می‌شود)</p>
                                            @php $watchedSources = $this->getWatchedSources(); @endphp
                                            @forelse ($watchedSources as $source)
                                                <div wire:key="watched-{{ $source->id }}" class="group flex items-start justify-between gap-2 border-b border-gray-100 py-2 dark:border-white/5">
                                                    <div class="min-w-0">
                                                        <p class="truncate text-sm text-gray-700 dark:text-gray-300">{{ $source->displayLabel() }}</p>
                                                        <p class="text-[11px] text-gray-400">
                                                            {{ $source->last_checked_at ? 'آخرین بررسی: '.$source->last_checked_at->format('Y/m/d H:i') : 'هنوز بررسی نشده' }}
                                                        </p>
                                                    </div>
                                                    <button type="button" wire:click="deleteWatchedSource({{ $source->id }})" class="relative shrink-0 rounded-lg p-1 text-gray-300 opacity-100 hover:bg-danger-50 hover:text-danger-600 md:opacity-0 md:group-hover:opacity-100 dark:text-gray-600 dark:hover:bg-danger-500/10">
                                                        <x-heroicon-o-trash class="h-3.5 w-3.5" />
                                                    </button>
                                                </div>
                                            @empty
                                                <p class="text-xs text-gray-400">هیچ منبعی تحت نظر نیست.</p>
                                            @endforelse

                                            <div class="mt-2 flex flex-col gap-2 sm:flex-row">
                                                <input
                                                    type="url"
                                                    x-ref="watchedSourceUrlInput"
                                                    placeholder="آدرس صفحه (https://...)"
                                                    class="bg-white text-gray-950 placeholder:text-gray-400 dark:text-white min-w-0 flex-1 rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-white/10 dark:bg-white/5"
                                                />
                                                <input
                                                    type="text"
                                                    x-ref="watchedSourceLabelInput"
                                                    placeholder="برچسب (اختیاری)"
                                                    class="bg-white text-gray-950 placeholder:text-gray-400 dark:text-white min-w-0 flex-1 rounded-lg border border-gray-300 px-3 py-2 text-sm sm:max-w-[8rem] dark:border-white/10 dark:bg-white/5"
                                                />
                                                <button
                                                    type="button"
                                                    x-on:click="$wire.addWatchedSource($refs.watchedSourceUrlInput.value, $refs.watchedSourceLabelInput.value); $refs.watchedSourceUrlInput.value = ''; $refs.watchedSourceLabelInput.value = ''"
                                                    class="shrink-0 rounded-lg bg-primary-600 px-3 py-2 text-xs font-medium text-white hover:bg-primary-500"
                                                >
                                                    افزودن
                                                </button>
                                            </div>
                                        </div>

                                        <div class="mt-4 border-t border-gray-100 pt-4 dark:border-white/5">
                                            <p class="mb-2 text-xs font-medium text-gray-600 dark:text-gray-300">اجرای خودکار روزانه (هر روز در ساعت تعیین‌شده اجرا می‌شود و نتیجه به‌عنوان نکتهٔ پیشنهادی ثبت می‌شود)</p>
                                            @php $scheduledPrompts = $this->getScheduledPrompts(); @endphp
                                            @forelse ($scheduledPrompts as $scheduledPrompt)
                                                <div wire:key="scheduled-{{ $scheduledPrompt->id }}" class="group flex items-start justify-between gap-2 border-b border-gray-100 py-2 dark:border-white/5">
                                                    <div class="min-w-0 flex-1">
                                                        <p class="truncate text-sm text-gray-700 dark:text-gray-300">{{ $scheduledPrompt->prompt }}</p>
                                                        <p class="text-[11px] text-gray-400">
                                                            ساعت {{ $scheduledPrompt->run_at_time }} -
                                                            {{ $scheduledPrompt->last_run_at ? 'آخرین اجرا: '.$scheduledPrompt->last_run_at->format('Y/m/d H:i') : 'هنوز اجرا نشده' }}
                                                        </p>
                                                    </div>
                                                    <div class="flex shrink-0 items-start gap-1">
                                                        <button type="button" wire:click="toggleScheduledPromptActive({{ $scheduledPrompt->id }})" title="{{ $scheduledPrompt->is_active ? 'موقوف کردن' : 'از سرگیری' }}" class="relative shrink-0 rounded-lg p-1 text-gray-300 opacity-100 hover:bg-gray-100 hover:text-gray-600 md:opacity-0 md:group-hover:opacity-100 dark:text-gray-600 dark:hover:bg-white/5">
                                                            @if ($scheduledPrompt->is_active)
                                                                <x-heroicon-o-pause class="h-3.5 w-3.5" />
                                                            @else
                                                                <x-heroicon-o-play class="h-3.5 w-3.5" />
                                                            @endif
                                                        </button>
                                                        <button type="button" wire:click="deleteScheduledPrompt({{ $scheduledPrompt->id }})" class="relative shrink-0 rounded-lg p-1 text-gray-300 opacity-100 hover:bg-danger-50 hover:text-danger-600 md:opacity-0 md:group-hover:opacity-100 dark:text-gray-600 dark:hover:bg-danger-500/10">
                                                            <x-heroicon-o-trash class="h-3.5 w-3.5" />
                                                        </button>
                                                    </div>
                                                </div>
                                            @empty
                                                <p class="text-xs text-gray-400">هیچ اجرای خودکاری تنظیم نشده.</p>
                                            @endforelse

                                            <div class="mt-2 flex flex-col gap-2 sm:flex-row">
                                                <input
                                                    type="text"
                                                    x-ref="scheduledPromptInput"
                                                    placeholder="مثلاً «مهمان‌های امروز رو چک کن و اگه کسی چک‌این دیر کرده بگو»"
                                                    class="bg-white text-gray-950 placeholder:text-gray-400 dark:text-white min-w-0 flex-1 rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-white/10 dark:bg-white/5"
                                                />
                                                <input
                                                    type="time"
                                                    x-ref="scheduledPromptTimeInput"
                                                    value="08:00"
                                                    class="bg-white text-gray-950 placeholder:text-gray-400 dark:text-white min-w-0 rounded-lg border border-gray-300 px-3 py-2 text-sm sm:max-w-[7rem] dark:border-white/10 dark:bg-white/5"
                                                />
                                                <button
                                                    type="button"
                                                    x-on:click="$wire.addScheduledPrompt($refs.scheduledPromptInput.value, $refs.scheduledPromptTimeInput.value); $refs.scheduledPromptInput.value = ''"
                                                    class="shrink-0 rounded-lg bg-primary-600 px-3 py-2 text-xs font-medium text-white hover:bg-primary-500"
                                                >
                                                    افزودن
                                                </button>
                                            </div>
                                        </div>

                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endif

                        @if ($selected && $visibleMessages->isNotEmpty() && $forwardTargets->isNotEmpty())
                            <div class="relative" x-data="{ open: false }">
                                <button type="button" x-on:click="open = ! open" title="ارسال گفتگو به دستیار دیگر" class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg text-gray-500 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-white/5">
                                    <x-heroicon-o-arrow-up-tray class="h-4 w-4" />
                                </button>
                                <div x-show="open" x-cloak x-on:click.outside="open = false" class="absolute left-0 top-full z-10 mt-1 w-48 rounded-lg border border-gray-200 bg-white py-1 shadow-lg dark:border-white/10 dark:bg-gray-800">
                                    <p class="px-3 py-1 text-[11px] text-gray-400">ارسال گفتگو به:</p>
                                    @foreach ($forwardTargets as $target)
                                        <button type="button" wire:click="forwardConversationTo({{ $target->id }})" x-on:click="open = false" class="flex w-full items-center gap-2 px-3 py-2 text-start text-xs text-gray-700 hover:bg-gray-50 dark:text-gray-200 dark:hover:bg-white/5">
                                            <x-ai-assistant-avatar :shape="$target->avatar_shape" :color="$target->avatar_color" :size="20" :animated="false" />
                                            {{ $target->displayName() }}
                                        </button>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>

                    <div x-ref="messages" class="flex-1 space-y-4 overflow-y-auto px-4 py-4">
                        @forelse ($visibleMessages as $message)
                            @if ($message->role === \App\Enums\AiMessageRole::Tool)
                                @php $consulted = $this->consultationAnswer($message); @endphp
                                <div class="flex flex-col items-center gap-2">
                                    <span class="inline-flex items-center gap-1 rounded-full bg-gray-100 px-3 py-1 text-[11px] text-gray-500 dark:bg-white/5 dark:text-gray-400">
                                        {{ match ($message->tool_name) { 'consult_assistant' => '🔄', 'handoff_to_assistant' => '🤝', default => '🌐' } }}
                                        {{ $this->describeToolActivity($message) }}
                                    </span>
                                    @if ($consulted)
                                        <details open class="w-full max-w-[85%] rounded-xl border border-primary-200 bg-primary-50/60 px-4 py-2.5 text-sm dark:border-primary-500/30 dark:bg-primary-500/10">
                                            <summary class="cursor-pointer text-xs font-semibold text-primary-700 dark:text-primary-300">پاسخ دستیار «{{ $consulted['name'] }}»</summary>
                                            <div class="mt-2 whitespace-pre-line text-gray-700 dark:text-gray-200">{{ $consulted['answer'] }}</div>
                                            @if ($consulted['nested'] !== [])
                                                <div class="mt-2 text-[11px] text-gray-500 dark:text-gray-400">این دستیار برای جواب از «{{ implode('»، «', $consulted['nested']) }}» هم پرسید.</div>
                                            @endif
                                        </details>
                                    @endif
                                </div>
                            @else
                                @php $isUser = $message->role === \App\Enums\AiMessageRole::User; @endphp
                                <div class="flex {{ $isUser ? 'justify-start' : 'justify-end' }}">
                                    <div class="flex max-w-[85%] flex-col gap-1 {{ $isUser ? 'items-start' : 'items-end' }}">
                                        @if (! $isUser && $hasHandoff && $message->author)
                                            <span class="px-1 text-[11px] font-medium text-primary-700 dark:text-primary-300">{{ $message->author->displayName() }}</span>
                                        @endif
                                        <div @class([
                                            'rounded-2xl px-4 py-2.5 text-sm',
                                            'rounded-bl-md bg-primary-600 text-white' => $isUser,
                                            'ai-markdown rounded-br-md bg-gray-100 text-gray-900 dark:bg-white/5 dark:text-gray-100' => ! $isUser,
                                        ])>
                                            @if ($isUser)
                                                <span class="whitespace-pre-line">{{ $message->content }}</span>
                                            @else
                                                {!! \App\Support\SafeMarkdown::toHtml($message->content ?? '') !!}
                                            @endif
                                        </div>
                                        <div class="flex items-center gap-1 px-1" @if (! $isUser) x-data="{ copied: false }" @endif>
                                            <span class="text-[11px] text-gray-400">{{ $message->created_at->format('H:i') }}</span>
                                            @if (! $isUser)
                                                <button
                                                    type="button"
                                                    x-on:click="copied = true; navigator.clipboard.writeText(@js($message->content ?? '')); setTimeout(() => copied = false, 1500)"
                                                    title="کپی پاسخ"
                                                    class="rounded p-0.5 text-gray-300 hover:text-gray-500 dark:text-gray-600"
                                                >
                                                    <x-heroicon-o-check class="h-3.5 w-3.5 text-success-600 dark:text-success-400" x-show="copied" x-cloak />
                                                    <x-heroicon-o-clipboard class="h-3.5 w-3.5" x-show="! copied" />
                                                </button>
                                                @if ($message->id === $lastAssistantMessageId)
                                                    <button type="button" x-on:click="streaming = true; streamHasText = false; $wire.regenerateLastResponse().then(() => streaming = false)" wire:loading.attr="disabled" wire:target="regenerateLastResponse" title="تولید دوباره" class="rounded p-0.5 text-gray-300 hover:text-gray-500 disabled:cursor-not-allowed disabled:opacity-50 dark:text-gray-600">
                                                        <x-heroicon-o-arrow-path class="h-3.5 w-3.5" />
                                                    </button>
                                                @endif
                                                <button type="button" wire:click="setMessageFeedback({{ $message->id }}, 'up')" title="پاسخ خوبی بود" class="rounded p-0.5 {{ $message->feedback === 'up' ? 'text-success-600 dark:text-success-400' : 'text-gray-300 hover:text-gray-500 dark:text-gray-600' }}">
                                                    <x-heroicon-o-hand-thumb-up class="h-3.5 w-3.5" />
                                                </button>
                                                <button type="button" wire:click="setMessageFeedback({{ $message->id }}, 'down')" title="پاسخ ضعیفی بود" class="rounded p-0.5 {{ $message->feedback === 'down' ? 'text-danger-600 dark:text-danger-400' : 'text-gray-300 hover:text-gray-500 dark:text-gray-600' }}">
                                                    <x-heroicon-o-hand-thumb-down class="h-3.5 w-3.5" />
                                                </button>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            @endif
                        @empty
                            <div class="flex h-full flex-col items-center justify-center gap-3 text-center">
                                <x-ai-assistant-avatar :shape="$activeProfile->avatar_shape" :color="$activeProfile->avatar_color" :size="64" />
                                <p class="max-w-sm text-xs text-gray-500 dark:text-gray-400">{{ \Illuminate\Support\Str::limit($activeProfile->description, 160) }}</p>
                            </div>
                        @endforelse

                        <div x-show="streaming" x-cloak class="flex justify-end">
                            <div class="ai-markdown max-w-[85%] whitespace-pre-wrap rounded-2xl rounded-br-md bg-gray-100 px-4 py-3 text-sm text-gray-900 dark:bg-white/5 dark:text-gray-100">
                                <span wire:stream="assistant-stream"></span>
                                <span x-show="! streamHasText" class="inline-flex gap-1 align-middle">
                                    <span class="h-1.5 w-1.5 animate-bounce rounded-full bg-gray-400" style="animation-delay: 0ms"></span>
                                    <span class="h-1.5 w-1.5 animate-bounce rounded-full bg-gray-400" style="animation-delay: 150ms"></span>
                                    <span class="h-1.5 w-1.5 animate-bounce rounded-full bg-gray-400" style="animation-delay: 300ms"></span>
                                </span>
                            </div>
                        </div>
                    </div>

                    <form x-on:submit.prevent="streaming = true; streamHasText = false; $wire.sendToProfile().then(() => streaming = false)" class="flex items-end gap-2 border-t border-gray-200 p-3 pb-[max(0.75rem,env(safe-area-inset-bottom))] dark:border-white/10">
                        <textarea
                            wire:model="body"
                            x-on:input="autoGrow($event.target)"
                            @keydown.enter="if (! $event.shiftKey) { $event.preventDefault(); $event.target.closest('form').requestSubmit(); }"
                            rows="2"
                            placeholder="پیام خود را بنویسید..."
                            wire:loading.attr="disabled"
                            wire:target="sendToProfile"
                            class="bg-white text-gray-950 placeholder:text-gray-400 dark:text-white min-w-0 max-h-32 min-h-[3.25rem] flex-1 resize-none rounded-xl border border-gray-300 px-3.5 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500 dark:border-white/10 dark:bg-white/5"
                        ></textarea>
                        <button
                            type="submit"
                            aria-label="ارسال"
                            wire:loading.attr="disabled"
                            wire:target="sendToProfile"
                            class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-primary-600 text-white transition hover:bg-primary-500 disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            <x-heroicon-o-paper-airplane class="h-5 w-5 -scale-x-100" />
                        </button>
                    </form>
                </div>
            </div>
        </div>
    @endif

    @script
    <script>
        Alpine.data('aiAssistantsHubThread', () => ({
            wasNearBottom: true,
            observer: null,
            mobileView: 'thread',
            streaming: false,
            streamHasText: false,

            init() {
                this.scrollToBottom(true);

                const el = this.$refs.messages;
                if (! el) {
                    return;
                }

                this.observer = new MutationObserver(() => {
                    if (this.wasNearBottom) {
                        this.scrollToBottom();
                    }

                    const streamEl = el.querySelector('[wire\\:stream="assistant-stream"]');
                    this.streamHasText = !! (streamEl && streamEl.textContent.trim() !== '');
                });
                this.observer.observe(el, { childList: true, subtree: true, characterData: true });

                el.addEventListener('scroll', () => {
                    this.wasNearBottom = this.isNearBottom();
                });
            },

            autoGrow(el) {
                el.style.height = 'auto';
                el.style.height = Math.min(el.scrollHeight, 128) + 'px';
            },

            isNearBottom() {
                const el = this.$refs.messages;
                if (! el) {
                    return true;
                }

                return el.scrollHeight - el.scrollTop - el.clientHeight < 150;
            },

            scrollToBottom(force = false) {
                this.$nextTick(() => {
                    const el = this.$refs.messages;
                    if (el && (force || this.wasNearBottom)) {
                        el.scrollTop = el.scrollHeight;
                    }
                });
            },
        }));
    </script>
    @endscript
</x-filament-panels::page>
