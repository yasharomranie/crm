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

                            @if ($profile->created_by === auth()->id())
                                <button
                                    type="button"
                                    wire:click="deleteProfile({{ $profile->id }})"
                                    wire:confirm="این دستیار و تمام گفتگوهایش حذف شود؟"
                                    aria-label="حذف دستیار"
                                    title="حذف دستیار"
                                    class="relative z-10 shrink-0 rounded-lg p-1.5 text-gray-300 opacity-100 transition hover:bg-danger-50 hover:text-danger-600 md:opacity-0 md:group-hover:opacity-100 dark:text-gray-600 dark:hover:bg-danger-500/10"
                                >
                                    <x-heroicon-o-trash class="h-4 w-4" />
                                </button>
                            @endif
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
                        <h3 class="text-base font-semibold text-gray-950 dark:text-white">ساخت دستیار جدید</h3>
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
                                class="fi-input block w-full rounded-lg border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500 dark:border-white/10 dark:bg-white/5"
                            />
                        </div>

                        <div>
                            <label for="newDescription" class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-300">دستورالعمل عملکرد (همین متن، پرامت سیستمی دستیار می‌شود)</label>
                            <textarea
                                id="newDescription"
                                wire:model="newDescription"
                                rows="5"
                                placeholder="وظیفه را دقیق و عملیاتی بنویس: کار این دستیار چیست، چه لحنی دارد، چه کاری نباید انجام دهد. از صفت‌های کلی مثل «فوق‌العاده باش» یا «حرفه‌ای باش» پرهیز کن."
                                class="fi-input block w-full resize-y rounded-lg border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500 dark:border-white/10 dark:bg-white/5"
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
                    </div>

                    <div class="mt-5 flex justify-end gap-2">
                        <button type="button" wire:click="closeCreateForm" class="rounded-lg px-4 py-2 text-sm text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-white/5">انصراف</button>
                        <button type="button" wire:click="createProfile" class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-500">ساخت دستیار</button>
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
                || ($message->role === \App\Enums\AiMessageRole::Assistant && filled($message->content)));
            $forwardTargets = $this->getForwardTargets();
        @endphp

        <div class="space-y-3" x-data="aiAssistantsHubThread()" x-on:message-sent.window="scrollToBottom(true)">
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
                            <button
                                type="button"
                                wire:click="selectProfileConversation({{ $conversation->id }})"
                                x-on:click="mobileView = 'thread'"
                                class="flex w-full items-center gap-2 rounded-lg px-2.5 py-3 text-start text-sm transition hover:bg-gray-50 md:py-2.5 dark:hover:bg-white/5 {{ $isActive ? 'bg-primary-50 font-medium text-primary-700 dark:bg-primary-500/10 dark:text-primary-400' : 'text-gray-700 dark:text-gray-300' }}"
                            >
                                <x-heroicon-o-chat-bubble-left-right class="h-4 w-4 shrink-0" />
                                <span class="truncate">{{ $conversation->title ?: 'گفتگوی جدید' }}</span>
                            </button>
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
                                    <div class="max-h-[85vh] w-full max-w-lg overflow-y-auto rounded-2xl bg-white p-5 shadow-xl dark:bg-gray-900">
                                        <div class="mb-4 flex items-center justify-between">
                                            <h3 class="text-base font-semibold text-gray-950 dark:text-white">دانش‌نامهٔ {{ $activeProfile->displayName() }}</h3>
                                            <button type="button" x-on:click="open = false" class="rounded-lg p-1 text-gray-400 hover:bg-gray-100 dark:hover:bg-white/5">
                                                <x-heroicon-o-x-mark class="h-5 w-5" />
                                            </button>
                                        </div>

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
                                                        <div wire:key="pending-{{ $entry->id }}" class="rounded-lg border border-warning-200 bg-warning-50 p-3 dark:border-warning-500/20 dark:bg-warning-500/10">
                                                            <p class="text-sm text-gray-800 dark:text-gray-100">{{ $entry->content }}</p>
                                                            <div class="mt-2 flex gap-2">
                                                                <button type="button" wire:click="approveKnowledgeEntry({{ $entry->id }})" class="rounded-lg bg-success-600 px-2.5 py-1 text-xs font-medium text-white hover:bg-success-500">تأیید</button>
                                                                <button type="button" wire:click="deleteKnowledgeEntry({{ $entry->id }})" class="rounded-lg border border-gray-300 px-2.5 py-1 text-xs text-gray-600 hover:bg-gray-100 dark:border-white/10 dark:text-gray-300 dark:hover:bg-white/5">رد</button>
                                                            </div>
                                                        </div>
                                                    @endforeach
                                                </div>
                                            </div>
                                        @endif

                                        <div class="mb-4">
                                            <p class="mb-2 text-xs font-medium text-gray-600 dark:text-gray-300">دانستنی‌های تأییدشده ({{ $approvedEntries->count() }})</p>
                                            @forelse ($approvedEntries as $entry)
                                                <div wire:key="approved-{{ $entry->id }}" class="group flex items-start justify-between gap-2 border-b border-gray-100 py-2 dark:border-white/5">
                                                    <p class="text-sm text-gray-700 dark:text-gray-300">{{ $entry->content }}</p>
                                                    <button type="button" wire:click="deleteKnowledgeEntry({{ $entry->id }})" class="relative shrink-0 rounded-lg p-1 text-gray-300 opacity-100 hover:bg-danger-50 hover:text-danger-600 md:opacity-0 md:group-hover:opacity-100 dark:text-gray-600 dark:hover:bg-danger-500/10">
                                                        <x-heroicon-o-trash class="h-3.5 w-3.5" />
                                                    </button>
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
                                                    class="fi-input flex-1 rounded-lg border-gray-300 px-3 py-2 text-sm dark:border-white/10 dark:bg-white/5"
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
                                            <label for="knowledgeUploadFile" class="mb-1 block text-xs font-medium text-gray-600 dark:text-gray-300">افزودن از فایل (txt، docx یا pdf - حداکثر ۵ مگابایت)</label>
                                            <div class="flex gap-2">
                                                <input
                                                    id="knowledgeUploadFile"
                                                    type="file"
                                                    wire:model="knowledgeUploadFile"
                                                    accept=".txt,.docx,.pdf"
                                                    class="fi-input flex-1 rounded-lg border-gray-300 px-3 py-2 text-sm dark:border-white/10 dark:bg-white/5"
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
                                                    class="fi-input flex-1 rounded-lg border-gray-300 px-3 py-2 text-sm dark:border-white/10 dark:bg-white/5"
                                                />
                                                <input
                                                    type="text"
                                                    x-ref="watchedSourceLabelInput"
                                                    placeholder="برچسب (اختیاری)"
                                                    class="fi-input flex-1 rounded-lg border-gray-300 px-3 py-2 text-sm sm:max-w-[8rem] dark:border-white/10 dark:bg-white/5"
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
                            @php $isUser = $message->role === \App\Enums\AiMessageRole::User; @endphp
                            <div class="flex {{ $isUser ? 'justify-start' : 'justify-end' }}">
                                <div class="flex max-w-[85%] flex-col gap-1 {{ $isUser ? 'items-start' : 'items-end' }}">
                                    <div @class([
                                        'rounded-2xl px-4 py-2.5 text-sm',
                                        'rounded-bl-md bg-primary-600 text-white' => $isUser,
                                        'ai-markdown rounded-br-md bg-gray-100 text-gray-900 dark:bg-white/5 dark:text-gray-100' => ! $isUser,
                                    ])>
                                        @if ($isUser)
                                            <span class="whitespace-pre-line">{{ $message->content }}</span>
                                        @else
                                            {!! \Illuminate\Support\Str::markdown($message->content ?? '') !!}
                                        @endif
                                    </div>
                                    <span class="px-1 text-[11px] text-gray-400">{{ $message->created_at->format('H:i') }}</span>
                                </div>
                            </div>
                        @empty
                            <div class="flex h-full flex-col items-center justify-center gap-3 text-center">
                                <x-ai-assistant-avatar :shape="$activeProfile->avatar_shape" :color="$activeProfile->avatar_color" :size="64" />
                                <p class="max-w-sm text-xs text-gray-500 dark:text-gray-400">{{ \Illuminate\Support\Str::limit($activeProfile->description, 160) }}</p>
                            </div>
                        @endforelse

                        @if ($isThinking)
                            <div class="flex justify-end">
                                <div class="flex items-center gap-1 rounded-2xl rounded-br-md bg-gray-100 px-4 py-3 dark:bg-white/5">
                                    <span class="h-1.5 w-1.5 animate-bounce rounded-full bg-gray-400" style="animation-delay: 0ms"></span>
                                    <span class="h-1.5 w-1.5 animate-bounce rounded-full bg-gray-400" style="animation-delay: 150ms"></span>
                                    <span class="h-1.5 w-1.5 animate-bounce rounded-full bg-gray-400" style="animation-delay: 300ms"></span>
                                </div>
                            </div>
                        @endif
                    </div>

                    <form wire:submit.prevent="sendToProfile" class="flex items-end gap-2 border-t border-gray-200 p-3 pb-[max(0.75rem,env(safe-area-inset-bottom))] dark:border-white/10">
                        <textarea
                            wire:model="body"
                            x-on:input="autoGrow($event.target)"
                            @keydown.enter="if (! $event.shiftKey) { $event.preventDefault(); $wire.sendToProfile(); }"
                            rows="2"
                            placeholder="پیام خود را بنویسید..."
                            wire:loading.attr="disabled"
                            wire:target="sendToProfile"
                            class="fi-input max-h-32 min-h-[3.25rem] flex-1 resize-none rounded-xl border-gray-300 px-3.5 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500 dark:border-white/10 dark:bg-white/5"
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
                });
                this.observer.observe(el, { childList: true, subtree: true });

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
