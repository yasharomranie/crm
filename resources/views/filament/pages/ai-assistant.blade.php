<x-filament-panels::page>
    @php
        $conversations = $this->getConversations();
        $selected = $this->getSelectedConversation();
        $visibleMessages = $this->getMessages()->filter(fn ($message) => $message->role === \App\Enums\AiMessageRole::User
            || ($message->role === \App\Enums\AiMessageRole::Assistant && filled($message->content)));
        $alerts = \App\Filament\Pages\AiAssistant::computeAlerts();

        $suggestions = [
            'وضعیت کلی امروز رو خلاصه کن و هشدارها رو بگو',
            'گزارش اشغال و درآمد اقامتگاه این ماه',
            'وضعیت پروژه‌های فعال از نظر بودجه و زمان‌بندی چطوره؟',
            'کارمندهایی که این ماه بیشترین تأخیر رو داشتن کیا هستن؟',
        ];

        $alertStyles = [
            'danger' => ['bg-danger-50 dark:bg-danger-500/10', 'text-danger-700 dark:text-danger-400', 'heroicon-o-exclamation-circle'],
            'warning' => ['bg-warning-50 dark:bg-warning-500/10', 'text-warning-700 dark:text-warning-400', 'heroicon-o-exclamation-triangle'],
            'info' => ['bg-info-50 dark:bg-info-500/10', 'text-info-700 dark:text-info-400', 'heroicon-o-information-circle'],
        ];
    @endphp

    <style>
        .ai-markdown :where(h1, h2, h3) { font-weight: 700; margin-block: .5rem; }
        .ai-markdown h1 { font-size: 1.1rem; }
        .ai-markdown h2 { font-size: 1.05rem; }
        .ai-markdown h3 { font-size: 1rem; }
        .ai-markdown p { margin-block: .4rem; line-height: 1.75; }
        .ai-markdown ul, .ai-markdown ol { margin-inline-start: 1.25rem; margin-block: .4rem; }
        .ai-markdown ul { list-style-type: disc; }
        .ai-markdown ol { list-style-type: decimal; }
        .ai-markdown strong { font-weight: 700; }
        .ai-markdown a { text-decoration: underline; }
        .ai-markdown table { width: 100%; border-collapse: collapse; margin-block: .5rem; font-size: .875rem; }
        .ai-markdown th, .ai-markdown td { border: 1px solid rgb(0 0 0 / 0.08); padding: .375rem .5rem; text-align: start; }
        .dark .ai-markdown th, .dark .ai-markdown td { border-color: rgb(255 255 255 / 0.1); }
        .ai-markdown thead { background: rgb(0 0 0 / 0.04); }
        .dark .ai-markdown thead { background: rgb(255 255 255 / 0.05); }
        .ai-markdown code { background: rgb(0 0 0 / 0.06); border-radius: .25rem; padding: .1rem .3rem; font-size: .85em; }
        .dark .ai-markdown code { background: rgb(255 255 255 / 0.08); }
    </style>

    <div class="space-y-4" x-data="aiAssistantThread()" x-on:message-sent.window="scrollToBottom(true)" x-on:conversation-selected.window="scrollToBottom(true)">
        @if (! \App\Models\AiProvider::active())
            <div class="flex items-center gap-3 rounded-xl border border-warning-200 bg-warning-50 p-4 text-sm text-warning-800 dark:border-warning-500/20 dark:bg-warning-500/10 dark:text-warning-300">
                <x-heroicon-o-exclamation-triangle class="h-5 w-5 shrink-0" />
                <span>
                    هیچ ارائه‌دهندهٔ هوش مصنوعی فعالی تنظیم نشده است.
                    <a href="{{ \App\Filament\Resources\AiProviders\AiProviderResource::getUrl() }}" class="font-semibold underline">از اینجا یکی تنظیم کنید.</a>
                </span>
            </div>
        @endif

        @if (filled($alerts))
            <div class="flex gap-2 overflow-x-auto pb-1">
                @foreach ($alerts as $alert)
                    @php [$bg, $text, $icon] = $alertStyles[$alert['severity']] ?? $alertStyles['info']; @endphp
                    <div class="flex shrink-0 items-center gap-2 rounded-lg {{ $bg }} {{ $text }} px-3 py-2 text-xs font-medium whitespace-nowrap">
                        <x-dynamic-component :component="$icon" class="h-4 w-4 shrink-0" />
                        {{ $alert['message'] }}
                    </div>
                @endforeach
            </div>
        @endif

        <div class="flex h-[calc(100dvh-12rem)] min-h-[26rem] overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm md:h-[calc(100dvh-14rem)] md:min-h-[30rem] dark:border-white/10 dark:bg-gray-900">
            {{-- Conversation list: full-width "screen" on mobile (hidden while
                a thread is open), a fixed-width sidebar from md: up, always
                visible alongside the thread. --}}
            <div
                class="w-full shrink-0 flex-col border-gray-200 md:flex md:w-auto md:max-w-xs md:border-e lg:max-w-sm dark:border-white/10"
                :class="mobileView === 'thread' ? 'hidden md:flex' : 'flex'"
            >
                <div class="flex items-center justify-between gap-2 border-b border-gray-200 p-3 dark:border-white/10">
                    <span class="text-sm font-semibold text-gray-950 dark:text-white">گفتگوها</span>
                    <button
                        type="button"
                        wire:click="newConversation"
                        x-on:click="mobileView = 'thread'"
                        aria-label="گفتگوی جدید"
                        title="گفتگوی جدید"
                        class="flex h-10 w-10 shrink-0 cursor-pointer items-center justify-center rounded-lg bg-primary-600 text-white transition hover:bg-primary-500"
                    >
                        <x-heroicon-o-plus-circle class="h-5 w-5" />
                    </button>
                </div>

                <div class="flex-1 space-y-1 overflow-y-auto p-2">
                    @forelse ($conversations as $conversation)
                        @php $isActive = $selected && $selected->id === $conversation->id; @endphp
                        <button
                            type="button"
                            wire:click="selectConversation({{ $conversation->id }})"
                            x-on:click="mobileView = 'thread'"
                            class="flex w-full items-center gap-2 rounded-lg px-2.5 py-3 text-start text-sm transition hover:bg-gray-50 md:py-2.5 dark:hover:bg-white/5 {{ $isActive ? 'bg-primary-50 font-medium text-primary-700 dark:bg-primary-500/10 dark:text-primary-400' : 'text-gray-700 dark:text-gray-300' }}"
                        >
                            <x-heroicon-o-chat-bubble-left-right class="h-4 w-4 shrink-0" />
                            <span class="truncate">{{ $conversation->title ?: 'گفتگوی جدید' }}</span>
                        </button>
                    @empty
                        <div class="flex h-full flex-col items-center justify-center gap-2 px-4 py-12 text-center">
                            <x-heroicon-o-sparkles class="h-8 w-8 text-gray-300 dark:text-gray-600" />
                            <p class="text-xs text-gray-500 dark:text-gray-400">هنوز گفتگویی با دستیار نداشته‌اید.</p>
                        </div>
                    @endforelse
                </div>
            </div>

            {{-- Thread: full-width "screen" on mobile (hidden while the list
                is open), the remaining flexible column from md: up. --}}
            <div
                class="min-w-0 flex-1 flex-col md:flex"
                :class="mobileView === 'list' ? 'hidden' : 'flex'"
            >
                {{-- Mobile-only header: the sidebar is off-screen here, so this
                    is the only way back to it (and the only place a title/new-
                    conversation button can live) below md:. --}}
                <div class="flex items-center gap-1 border-b border-gray-200 p-2 md:hidden dark:border-white/10">
                    <button
                        type="button"
                        x-on:click="mobileView = 'list'"
                        aria-label="بازگشت به فهرست گفتگوها"
                        title="بازگشت به فهرست گفتگوها"
                        class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg text-gray-500 transition hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-white/5"
                    >
                        <x-heroicon-o-chevron-right class="h-5 w-5" />
                    </button>
                    <span class="min-w-0 flex-1 truncate text-sm font-semibold text-gray-950 dark:text-white">
                        {{ $selected?->title ?: 'گفتگوی جدید' }}
                    </span>
                    <button
                        type="button"
                        wire:click="newConversation"
                        aria-label="گفتگوی جدید"
                        title="گفتگوی جدید"
                        class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg text-gray-500 transition hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-white/5"
                    >
                        <x-heroicon-o-plus-circle class="h-5 w-5" />
                    </button>
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
                            <x-heroicon-o-sparkles class="h-12 w-12 text-primary-300 dark:text-primary-700" />
                            <p class="text-sm font-medium text-gray-700 dark:text-gray-300">از دستیار هوشمند مدیریتی بپرسید</p>
                            <p class="max-w-sm text-xs text-gray-500 dark:text-gray-400">
                                دستیار به تمام واحدها (اقامتی، پروژه‌ها، فروش و CRM، حضور و غیاب) دسترسی دارد و می‌تواند گزارش، تحلیل و پیشنهاد بدهد.
                            </p>
                            <div class="mt-2 flex flex-wrap justify-center gap-2">
                                @foreach ($suggestions as $suggestion)
                                    <button
                                        type="button"
                                        wire:click="$set('body', {{ \Illuminate\Support\Js::from($suggestion) }})"
                                        class="rounded-full border border-gray-200 px-3 py-1.5 text-xs text-gray-600 transition hover:border-primary-300 hover:text-primary-600 dark:border-white/10 dark:text-gray-300"
                                    >
                                        {{ $suggestion }}
                                    </button>
                                @endforeach
                            </div>
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

                <div
                    x-show="listening || speechError"
                    x-cloak
                    class="flex items-center gap-1.5 border-t border-gray-200 px-4 pt-2 text-xs dark:border-white/10"
                >
                    <span x-show="listening" class="flex items-center gap-1.5 text-primary-600 dark:text-primary-400">
                        <span class="h-1.5 w-1.5 animate-pulse rounded-full bg-current"></span>
                        <span x-text="interimText || 'در حال شنیدن...'"></span>
                    </span>
                    <span x-show="speechError" x-text="speechError" class="text-danger-600 dark:text-danger-400"></span>
                </div>

                <form
                    wire:submit.prevent="send"
                    class="flex items-end gap-2 p-3 pb-[max(0.75rem,env(safe-area-inset-bottom))]"
                    :class="(listening || speechError) ? '' : 'border-t border-gray-200 dark:border-white/10'"
                >
                    <textarea
                        wire:model="body"
                        x-on:input="autoGrow($event.target)"
                        @keydown.enter="if (! $event.shiftKey) { $event.preventDefault(); $wire.send(); }"
                        rows="2"
                        placeholder="سؤال یا درخواست گزارش خود را بنویسید..."
                        wire:loading.attr="disabled"
                        wire:target="send"
                        class="fi-input max-h-32 min-h-[3.25rem] flex-1 resize-none rounded-xl border-gray-300 px-3.5 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500 dark:border-white/10 dark:bg-white/5"
                    ></textarea>
                    <button
                        type="button"
                        x-show="speechSupported"
                        x-on:click="toggleListening()"
                        :aria-label="listening ? 'توقف ضبط صدا' : 'ورودی صوتی'"
                        :title="listening ? 'توقف ضبط صدا' : 'ورودی صوتی (فارسی)'"
                        class="flex h-10 w-10 shrink-0 cursor-pointer items-center justify-center rounded-xl transition"
                        :class="listening
                            ? 'bg-danger-500 text-white'
                            : 'bg-gray-100 text-gray-600 hover:bg-gray-200 dark:bg-white/5 dark:text-gray-300 dark:hover:bg-white/10'"
                    >
                        <x-heroicon-o-stop-circle class="h-5 w-5" x-show="listening" x-cloak />
                        <x-heroicon-o-microphone class="h-5 w-5" x-show="!listening" />
                    </button>
                    <button
                        type="submit"
                        aria-label="ارسال"
                        title="ارسال"
                        wire:loading.attr="disabled"
                        wire:target="send"
                        class="flex h-10 w-10 shrink-0 cursor-pointer items-center justify-center rounded-xl bg-primary-600 text-white transition hover:bg-primary-500 disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        <x-heroicon-o-paper-airplane class="h-5 w-5 -scale-x-100" />
                    </button>
                </form>
            </div>
        </div>
    </div>

    @script
    <script>
        Alpine.data('aiAssistantThread', () => ({
            wasNearBottom: true,
            observer: null,
            // Below md:, the conversation list and the thread are two full-
            // width "screens" that swap in and out (an app-style master/
            // detail collapse) instead of the cramped side-by-side layout
            // that never fit a phone. 'thread' first: a conversation is
            // already auto-selected on mount (see AiAssistant::mount()), so
            // landing straight on it matches how chat apps open to your
            // last conversation, not an intermediate list screen.
            mobileView: 'thread',

            // --- Voice input (free, client-side only - see toggleListening()) ---
            speechSupported: false,
            listening: false,
            interimText: '',
            speechError: '',
            recognition: null,
            speechBaseText: '',

            init() {
                this.scrollToBottom(true);
                this.initSpeech();

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

            /**
             * Uses the browser's own built-in speech recognition (Web
             * Speech API) - free, no API key, no server round trip for the
             * transcription itself. Only supported in Chromium-based
             * browsers (Chrome/Edge); speechSupported hides the mic button
             * everywhere else instead of showing something broken.
             */
            initSpeech() {
                const SpeechRecognitionCtor = window.SpeechRecognition || window.webkitSpeechRecognition;
                this.speechSupported = !! SpeechRecognitionCtor;

                if (! this.speechSupported) {
                    return;
                }

                this.recognition = new SpeechRecognitionCtor();
                this.recognition.lang = 'fa-IR';
                this.recognition.continuous = true;
                this.recognition.interimResults = true;

                this.recognition.onresult = (event) => {
                    // Rebuilds the transcript from ALL results every time
                    // (not just from event.resultIndex onward) and always
                    // overwrites body with it, instead of appending each
                    // event's "new" chunk - some browsers (Chrome on
                    // Android especially) re-fire the same already-final
                    // result again in a later event, which turned into
                    // duplicated repeated phrases when we appended.
                    let finalText = '';
                    let interim = '';

                    for (let i = 0; i < event.results.length; i++) {
                        const result = event.results[i];

                        if (result.isFinal) {
                            finalText += result[0].transcript;
                        } else {
                            interim += result[0].transcript;
                        }
                    }

                    this.interimText = interim.trim();

                    const base = this.speechBaseText;
                    this.$wire.set('body', (base && finalText.trim() ? base + ' ' : base) + finalText.trim());
                };

                this.recognition.onerror = (event) => {
                    this.listening = false;
                    this.speechError = event.error === 'not-allowed'
                        ? 'اجازهٔ دسترسی به میکروفون داده نشد.'
                        : 'خطا در تشخیص گفتار. دوباره تلاش کنید.';
                };

                this.recognition.onend = () => {
                    this.listening = false;
                    this.interimText = '';
                };
            },

            toggleListening() {
                if (! this.speechSupported) {
                    return;
                }

                this.speechError = '';

                if (this.listening) {
                    this.recognition.stop();
                    this.listening = false;

                    return;
                }

                this.interimText = '';
                this.speechBaseText = (this.$wire.body || '').trim();
                this.recognition.start();
                this.listening = true;
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
