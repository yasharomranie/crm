@php
    $hasProvider = (bool) \App\Models\AiProvider::active();
@endphp

@include('filament.components.chartjs-assets')
@include('filament.components.ai-assistant-chart-renderer')

<div
    x-data="aiAssistantWidget()"
    x-cloak
    class="ai-widget-root"
    x-on:message-sent.window="onMessageSent()"
>
    <style>
        .ai-widget-root { position: fixed; inset: 0; z-index: 2147483000; pointer-events: none; }

        /* --- The mascot bubble ------------------------------------------- */
        .ai-widget-bubble {
            position: fixed;
            width: 64px;
            height: 64px;
            pointer-events: auto;
            touch-action: none;
            cursor: grab;
            -webkit-tap-highlight-color: transparent;
            filter: drop-shadow(0 6px 16px rgb(180 83 9 / 0.35));
            transition: filter .15s ease;
        }
        .ai-widget-bubble:active { cursor: grabbing; }
        .ai-widget-bubble.is-dragging { filter: drop-shadow(0 10px 24px rgb(180 83 9 / 0.45)); }

        .ai-widget-glow {
            position: absolute;
            inset: -10px;
            border-radius: 9999px;
            background: radial-gradient(circle, rgb(251 191 36 / 0.55) 0%, transparent 70%);
            animation: ai-widget-pulse 2.6s ease-in-out infinite;
        }
        @keyframes ai-widget-pulse {
            0%, 100% { transform: scale(.85); opacity: .55; }
            50% { transform: scale(1.15); opacity: .9; }
        }

        .ai-widget-creature {
            display: block;
            position: relative;
            width: 100%;
            height: 100%;
            animation: ai-widget-bob 3.2s ease-in-out infinite;
        }
        .ai-widget-bubble.is-dragging .ai-widget-creature,
        .ai-widget-bubble.is-dragging .ai-widget-glow { animation-play-state: paused; }
        @keyframes ai-widget-bob {
            0%, 100% { transform: translateY(0) rotate(-3deg); }
            50% { transform: translateY(-7px) rotate(3deg); }
        }

        .ai-widget-body {
            position: absolute;
            inset: 4px;
            border-radius: 46% 46% 42% 42% / 56% 56% 40% 40%;
            background: linear-gradient(155deg, #fde68a 0%, #f59e0b 55%, #d97706 100%);
            border: 2.5px solid #92400e;
            box-shadow: inset 0 -6px 10px rgb(146 64 14 / 0.35), inset 0 4px 6px rgb(255 255 255 / 0.45);
        }
        .ai-widget-ear {
            position: absolute;
            top: -3px;
            width: 12px;
            height: 16px;
            background: #f59e0b;
            border: 2.5px solid #92400e;
            border-radius: 60% 60% 50% 50%;
        }
        .ai-widget-ear.is-start { inset-inline-start: 10px; rotate: -18deg; }
        .ai-widget-ear.is-end { inset-inline-end: 10px; rotate: 18deg; }

        .ai-widget-cheek {
            position: absolute;
            top: 34px;
            width: 9px;
            height: 6px;
            border-radius: 9999px;
            background: rgb(217 119 6 / 0.55);
        }
        .ai-widget-cheek.is-start { inset-inline-start: 8px; }
        .ai-widget-cheek.is-end { inset-inline-end: 8px; }

        .ai-widget-eyes { position: absolute; top: 24px; inset-inline: 0; display: flex; justify-content: center; gap: 12px; }
        .ai-widget-eye {
            position: relative;
            width: 7px;
            height: 9px;
            border-radius: 50%;
            background: #451a03;
            animation: ai-widget-blink 5s infinite;
        }
        .ai-widget-eye::after {
            content: '';
            position: absolute;
            top: 1px; left: 1.5px;
            width: 2px; height: 2px;
            border-radius: 50%;
            background: #fef3c7;
        }
        @keyframes ai-widget-blink {
            0%, 92%, 100% { transform: scaleY(1); }
            96% { transform: scaleY(0.12); }
        }
        .ai-widget-mouth {
            position: absolute;
            top: 37px;
            inset-inline: 0;
            margin-inline: auto;
            width: 10px;
            height: 5px;
            border-radius: 0 0 10px 10px;
            background: #451a03;
        }
        .ai-widget-sparkle {
            position: absolute;
            top: -2px;
            inset-inline-end: 4px;
            width: 9px;
            height: 9px;
            color: #fef9c3;
            animation: ai-widget-sparkle 2.6s ease-in-out infinite;
        }
        @keyframes ai-widget-sparkle {
            0%, 100% { opacity: .3; transform: scale(.7) rotate(0deg); }
            50% { opacity: 1; transform: scale(1.1) rotate(25deg); }
        }

        /* --- The chat popup ------------------------------------------------ */
        .ai-widget-panel {
            position: fixed;
            pointer-events: auto;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            background: #fff;
            border: 1px solid rgb(0 0 0 / 0.08);
            box-shadow: 0 24px 48px -12px rgb(0 0 0 / 0.25), 0 0 0 1px rgb(0 0 0 / 0.02);
        }
        .dark .ai-widget-panel { background: #111827; border-color: rgb(255 255 255 / 0.08); }

        .ai-widget-panel.is-desktop {
            width: 380px;
            max-width: calc(100vw - 24px);
            height: min(600px, 78vh);
            border-radius: 1.25rem;
        }
        .ai-widget-panel.is-mobile {
            inset: 0;
            top: max(12px, env(safe-area-inset-top));
            border-radius: 1.25rem 1.25rem 0 0;
        }

        .ai-widget-header {
            display: flex; align-items: center; gap: .5rem;
            padding: .625rem .75rem;
            border-bottom: 1px solid rgb(0 0 0 / 0.08);
            background: linear-gradient(135deg, #fef3c7, #fde68a);
        }
        .dark .ai-widget-header { border-color: rgb(255 255 255 / 0.08); background: linear-gradient(135deg, rgb(146 64 14 / 0.4), rgb(120 53 15 / 0.25)); }

        .ai-markdown :where(h1, h2, h3) { font-weight: 700; margin-block: .5rem; }
        .ai-markdown h1 { font-size: 1rem; }
        .ai-markdown h2 { font-size: .95rem; }
        .ai-markdown h3 { font-size: .9rem; }
        .ai-markdown p { margin-block: .35rem; line-height: 1.7; }
        .ai-markdown ul, .ai-markdown ol { margin-inline-start: 1.1rem; margin-block: .35rem; }
        .ai-markdown ul { list-style-type: disc; }
        .ai-markdown ol { list-style-type: decimal; }
        .ai-markdown strong { font-weight: 700; }
        .ai-markdown a { text-decoration: underline; }
        .ai-markdown table { width: 100%; border-collapse: collapse; margin-block: .5rem; font-size: .8rem; }
        .ai-markdown th, .ai-markdown td { border: 1px solid rgb(0 0 0 / 0.08); padding: .3rem .4rem; text-align: start; }
        .dark .ai-markdown th, .dark .ai-markdown td { border-color: rgb(255 255 255 / 0.1); }
        .ai-markdown code { background: rgb(0 0 0 / 0.06); border-radius: .25rem; padding: .1rem .3rem; font-size: .85em; }
        .dark .ai-markdown code { background: rgb(255 255 255 / 0.08); }
    </style>

    {{-- The draggable mascot. wire:ignore.self: this element's own style/
         class are 100% Alpine-owned (drag position, is-dragging) - every
         Livewire re-render (triggered by literally any wire: action
         elsewhere on the page, e.g. send()) would otherwise morph this
         node's attributes back to Blade's static server-rendered defaults,
         snapping it back to the corner mid-drag or mid-conversation.
         .self only skips morphing THIS element's own tag, never its
         children, so nothing inside loses reactivity. --}}
    <button
        type="button"
        aria-label="باز کردن دستیار هوشمند"
        title="دستیار هوشمند"
        class="ai-widget-bubble"
        :class="dragging && 'is-dragging'"
        x-ref="bubble"
        wire:ignore.self
        x-on:pointerdown="onPointerDown($event)"
        x-on:pointermove="onPointerMove($event)"
        x-on:pointerup="onPointerUp($event)"
        x-on:pointercancel="onPointerUp($event)"
        x-bind:style="bubbleStyle()"
    >
        <span class="ai-widget-glow"></span>
        <span class="ai-widget-creature">
            <span class="ai-widget-ear is-start"></span>
            <span class="ai-widget-ear is-end"></span>
            <span class="ai-widget-body"></span>
            <span class="ai-widget-cheek is-start"></span>
            <span class="ai-widget-cheek is-end"></span>
            <span class="ai-widget-eyes">
                <span class="ai-widget-eye"></span>
                <span class="ai-widget-eye"></span>
            </span>
            <span class="ai-widget-mouth"></span>
            <svg viewBox="0 0 24 24" fill="currentColor" class="ai-widget-sparkle">
                <path d="M12 0l1.8 7.2L21 9l-7.2 1.8L12 18l-1.8-7.2L3 9l7.2-1.8L12 0z" />
            </svg>
        </span>
    </button>

    {{-- The chat popup. wire:ignore.self for the same reason as the mascot
         button above: whether this panel is open/closed (x-show, i.e. this
         element's own inline `display`) and where it's positioned
         (x-bind:style) are both 100% Alpine-owned, never server state -
         the actual bug this fixes: Blade always renders the static
         `style="display: none;"` you see below (the server has no idea
         whether the panel is currently open), so on EVERY Livewire
         request (e.g. every single send()), morphing this element's own
         attributes back toward that server-rendered markup could re-hide
         an open panel or otherwise desync it from `open`, even though
         nothing about open/closed actually changed. Its children (the
         message list, the thinking indicator) are unaffected - .self never
         skips morphing descendants. --}}
    <div
        x-show="open"
        x-transition:enter="transition ease-out duration-150"
        x-transition:enter-start="opacity-0 scale-95"
        x-transition:enter-end="opacity-100 scale-100"
        x-transition:leave="transition ease-in duration-100"
        x-transition:leave-start="opacity-100 scale-100"
        x-transition:leave-end="opacity-0 scale-95"
        x-on:keydown.escape.window="close()"
        x-on:click.outside="close()"
        class="ai-widget-panel"
        :class="isMobile ? 'is-mobile' : 'is-desktop'"
        wire:ignore.self
        x-bind:style="isMobile ? {} : panelStyle()"
        style="display: none;"
    >
        <div class="ai-widget-header">
            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-white/60 text-base dark:bg-black/20">🧡</span>
            <div class="min-w-0 flex-1">
                <p class="truncate text-sm font-bold text-amber-900 dark:text-amber-200">دستیار هوشمند</p>
                <p class="truncate text-[11px] text-amber-800/70 dark:text-amber-200/60">{{ $selected?->title ?: 'گفتگوی جدید' }}</p>
            </div>
            <button
                type="button"
                wire:click="newConversation"
                aria-label="گفتگوی جدید"
                title="گفتگوی جدید"
                class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-amber-900/70 transition hover:bg-black/5 dark:text-amber-200/70 dark:hover:bg-white/10"
            >
                <x-heroicon-o-plus-circle class="h-5 w-5" />
            </button>
            <button
                type="button"
                x-on:click="close()"
                aria-label="بستن"
                title="بستن"
                class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-amber-900/70 transition hover:bg-black/5 dark:text-amber-200/70 dark:hover:bg-white/10"
            >
                <x-heroicon-o-x-mark class="h-5 w-5" />
            </button>
        </div>

        @if (! $hasProvider)
            <div class="mx-3 mt-3 flex items-start gap-2 rounded-lg border border-warning-200 bg-warning-50 p-2.5 text-xs text-warning-800 dark:border-warning-500/20 dark:bg-warning-500/10 dark:text-warning-300">
                <x-heroicon-o-exclamation-triangle class="mt-0.5 h-4 w-4 shrink-0" />
                <span>هیچ ارائه‌دهندهٔ هوش مصنوعی فعالی تنظیم نشده است.</span>
            </div>
        @endif

        <div x-ref="messages" class="flex-1 space-y-3 overflow-y-auto px-3 py-3">
            @forelse ($visibleMessages as $message)
                @php $isUser = $message->role === \App\Enums\AiMessageRole::User; @endphp
                <div class="flex {{ $isUser ? 'justify-start' : 'justify-end' }}">
                    <div class="flex max-w-[88%] flex-col gap-1 {{ $isUser ? 'items-start' : 'items-end' }}">
                        <div @class([
                            'rounded-2xl px-3.5 py-2 text-[13px]',
                            'rounded-bl-md bg-amber-500 text-white' => $isUser,
                            'ai-markdown rounded-br-md bg-gray-100 text-gray-900 dark:bg-white/5 dark:text-gray-100' => ! $isUser,
                        ])>
                            @if ($isUser)
                                <span class="whitespace-pre-line">{{ $message->content }}</span>
                            @else
                                {!! \Illuminate\Support\Str::markdown($message->content ?? '') !!}
                            @endif
                        </div>

                        @if (! $isUser && filled($message->chart))
                            <div class="w-full max-w-[16rem] rounded-xl border border-gray-200 bg-white p-2.5 dark:border-white/10 dark:bg-white/5">
                                <p class="mb-1.5 text-[11px] font-medium text-gray-500 dark:text-gray-400">{{ $message->chart['title'] }}</p>
                                <canvas
                                    x-data
                                    x-init="window.renderAiAssistantChart($el, @js($message->chart))"
                                    height="140"
                                ></canvas>
                            </div>
                        @endif
                    </div>
                </div>
            @empty
                <div class="flex h-full flex-col items-center justify-center gap-2 px-4 text-center">
                    <span class="text-3xl">🧡</span>
                    <p class="text-sm font-medium text-gray-700 dark:text-gray-300">سلام! چه کمکی از دستم برمیاد؟</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">می‌تونی هر سؤالی دربارهٔ وضعیت سیستم بپرسی.</p>
                </div>
            @endforelse

            @if ($isThinking)
                <div class="flex justify-end">
                    <div class="flex items-center gap-1 rounded-2xl rounded-br-md bg-gray-100 px-3.5 py-2.5 dark:bg-white/5">
                        <span class="h-1.5 w-1.5 animate-bounce rounded-full bg-gray-400" style="animation-delay: 0ms"></span>
                        <span class="h-1.5 w-1.5 animate-bounce rounded-full bg-gray-400" style="animation-delay: 150ms"></span>
                        <span class="h-1.5 w-1.5 animate-bounce rounded-full bg-gray-400" style="animation-delay: 300ms"></span>
                    </div>
                </div>
            @endif
        </div>

        <div x-show="listening || speechError" x-cloak class="flex items-center gap-1.5 border-t border-gray-200 px-3 pt-2 text-[11px] dark:border-white/10">
            <span x-show="listening" class="flex items-center gap-1.5 text-amber-600 dark:text-amber-400">
                <span class="h-1.5 w-1.5 animate-pulse rounded-full bg-current"></span>
                <span x-text="interimText || 'در حال شنیدن...'"></span>
            </span>
            <span x-show="speechError" x-text="speechError" class="text-danger-600 dark:text-danger-400"></span>
        </div>

        <form
            wire:submit.prevent="send"
            class="flex items-end gap-2 p-2.5 pb-[max(0.625rem,env(safe-area-inset-bottom))]"
            :class="(listening || speechError) ? '' : 'border-t border-gray-200 dark:border-white/10'"
        >
            <textarea
                wire:model="body"
                x-on:input="autoGrow($event.target)"
                x-on:keydown.enter="if (! $event.shiftKey) { $event.preventDefault(); $wire.send(); }"
                rows="2"
                placeholder="پیام خود را بنویسید..."
                wire:loading.attr="disabled"
                wire:target="send"
                class="fi-input max-h-28 min-h-[2.875rem] flex-1 resize-none rounded-xl border-gray-300 px-3 py-2.5 text-[13px] focus:outline-none focus:ring-2 focus:ring-amber-500 dark:border-white/10 dark:bg-white/5"
            ></textarea>
            <button
                type="button"
                x-show="speechSupported"
                x-on:click="toggleListening()"
                :aria-label="listening ? 'توقف ضبط صدا' : 'ورودی صوتی'"
                :title="listening ? 'توقف ضبط صدا' : 'ورودی صوتی (فارسی)'"
                class="flex h-9 w-9 shrink-0 cursor-pointer items-center justify-center rounded-xl transition"
                :class="listening
                    ? 'bg-danger-500 text-white'
                    : 'bg-gray-100 text-gray-600 hover:bg-gray-200 dark:bg-white/5 dark:text-gray-300 dark:hover:bg-white/10'"
            >
                <x-heroicon-o-stop-circle class="h-4 w-4" x-show="listening" x-cloak />
                <x-heroicon-o-microphone class="h-4 w-4" x-show="!listening" />
            </button>
            <button
                type="submit"
                aria-label="ارسال"
                title="ارسال"
                wire:loading.attr="disabled"
                wire:target="send"
                class="flex h-9 w-9 shrink-0 cursor-pointer items-center justify-center rounded-xl bg-amber-500 text-white transition hover:bg-amber-400 disabled:cursor-not-allowed disabled:opacity-50"
            >
                <x-heroicon-o-paper-airplane class="h-4 w-4 -scale-x-100" />
            </button>
        </form>
    </div>

    @script
    <script>
        Alpine.data('aiAssistantWidget', () => ({
            bubbleSize: 64,
            margin: 14,
            open: false,
            dragging: false,
            moved: false,
            pointerId: null,
            startPointer: { x: 0, y: 0 },
            startPosition: { x: 0, y: 0 },
            position: { x: 0, y: 0 },
            isMobile: window.matchMedia('(max-width: 767px)').matches,

            // --- Voice input (free, client-side only - see toggleListening()) ---
            speechSupported: false,
            listening: false,
            interimText: '',
            speechError: '',
            recognition: null,
            speechBaseText: '',
            speechCommitted: '',
            speechStoppedByUser: false,

            init() {
                this.position = this.loadPosition();
                window.addEventListener('resize', () => {
                    this.isMobile = window.matchMedia('(max-width: 767px)').matches;
                    this.position = this.clamp(this.position);
                });
                this.initSpeech();
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
                // continuous:true is where the real bug lives: Chrome on
                // Android is well known to garble/duplicate long
                // continuous dictation sessions (repeating whole phrases
                // baked right into the transcript, not just re-sent
                // events). Instead, each session covers ONE utterance and
                // onend restarts a fresh one automatically for as long as
                // the mic is toggled on - independent short sessions never
                // hit that bug, and chaining them gives the same
                // "keep talking" UX as continuous mode would.
                this.recognition.continuous = false;
                this.recognition.interimResults = true;

                this.recognition.onresult = (event) => {
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

                    if (finalText.trim()) {
                        this.speechCommitted = (this.speechCommitted ? this.speechCommitted + ' ' : '') + finalText.trim();
                    }

                    const parts = [this.speechBaseText, this.speechCommitted].filter(Boolean);
                    this.$wire.set('body', parts.join(' '));
                };

                this.recognition.onerror = (event) => {
                    // "no-speech" (silence timeout) and "aborted" (we
                    // called stop() ourselves) aren't real failures - just
                    // let onend decide whether to keep the session going.
                    if (event.error === 'no-speech' || event.error === 'aborted') {
                        return;
                    }

                    this.speechStoppedByUser = true;
                    this.listening = false;
                    this.speechError = event.error === 'not-allowed'
                        ? 'اجازهٔ دسترسی به میکروفون داده نشد.'
                        : 'خطا در تشخیص گفتار. دوباره تلاش کنید.';
                };

                this.recognition.onend = () => {
                    this.interimText = '';

                    if (this.listening && ! this.speechStoppedByUser) {
                        try {
                            this.recognition.start();
                        } catch (e) {
                            this.listening = false;
                        }

                        return;
                    }

                    this.listening = false;
                    this.speechStoppedByUser = false;
                };
            },

            toggleListening() {
                if (! this.speechSupported) {
                    return;
                }

                this.speechError = '';

                if (this.listening) {
                    this.speechStoppedByUser = true;
                    this.recognition.stop();
                    this.listening = false;

                    return;
                }

                this.interimText = '';
                this.speechCommitted = '';
                this.speechBaseText = (this.$wire.body || '').trim();
                this.speechStoppedByUser = false;
                this.recognition.start();
                this.listening = true;
            },

            bounds() {
                return {
                    maxX: window.innerWidth - this.bubbleSize - this.margin,
                    maxY: window.innerHeight - this.bubbleSize - this.margin,
                };
            },

            clamp(pos) {
                const { maxX, maxY } = this.bounds();
                return {
                    x: Math.min(Math.max(pos.x, this.margin), Math.max(this.margin, maxX)),
                    y: Math.min(Math.max(pos.y, this.margin), Math.max(this.margin, maxY)),
                };
            },

            // Stored as a fraction of the draggable area so a position
            // picked on one screen still makes sense after a resize or on
            // a differently-sized device, instead of a raw pixel offset
            // that could land off-screen entirely.
            loadPosition() {
                const { maxX, maxY } = this.bounds();
                try {
                    const raw = localStorage.getItem('ai_widget_position');
                    if (raw) {
                        const { xPct, yPct } = JSON.parse(raw);
                        return this.clamp({ x: xPct * maxX, y: yPct * maxY });
                    }
                } catch (e) {}

                // Default: bottom-inline-end corner, respecting RTL.
                const isRtl = document.documentElement.dir === 'rtl';
                return this.clamp({ x: isRtl ? this.margin : maxX, y: maxY });
            },

            savePosition() {
                const { maxX, maxY } = this.bounds();
                try {
                    localStorage.setItem('ai_widget_position', JSON.stringify({
                        xPct: maxX > 0 ? this.position.x / maxX : 0,
                        yPct: maxY > 0 ? this.position.y / maxY : 0,
                    }));
                } catch (e) {}
            },

            // An object, not a CSS string: Alpine merges an object's keys
            // into the element's existing inline style property-by-property,
            // but a string value instead REPLACES the whole style attribute
            // wholesale - which would wipe out whatever x-show had just set
            // on `display` the moment this re-evaluates (position changes,
            // a window resize, ...).
            bubbleStyle() {
                return { left: `${this.position.x}px`, top: `${this.position.y}px` };
            },

            onPointerDown(event) {
                this.dragging = true;
                this.moved = false;
                this.pointerId = event.pointerId;
                this.startPointer = { x: event.clientX, y: event.clientY };
                this.startPosition = { ...this.position };
                this.$refs.bubble.setPointerCapture(event.pointerId);
            },

            onPointerMove(event) {
                if (! this.dragging) return;

                const dx = event.clientX - this.startPointer.x;
                const dy = event.clientY - this.startPointer.y;

                if (! this.moved && Math.hypot(dx, dy) > 6) {
                    this.moved = true;
                }

                if (this.moved) {
                    this.position = this.clamp({
                        x: this.startPosition.x + dx,
                        y: this.startPosition.y + dy,
                    });
                }
            },

            onPointerUp(event) {
                if (! this.dragging) return;
                this.dragging = false;

                try { this.$refs.bubble.releasePointerCapture(event.pointerId); } catch (e) {}

                if (this.moved) {
                    this.savePosition();
                } else {
                    this.toggle();
                }
            },

            toggle() {
                this.open ? this.close() : this.openPanel();
            },

            openPanel() {
                this.open = true;
                this.$nextTick(() => this.scrollToBottom());
            },

            close() {
                this.open = false;
            },

            // Desktop only - anchors the popup near whichever corner the
            // bubble currently occupies, so it always opens toward free
            // screen space instead of off the edge. An object, not a CSS
            // string - see bubbleStyle()'s own comment for why.
            panelStyle() {
                const centerX = this.position.x + this.bubbleSize / 2;
                const centerY = this.position.y + this.bubbleSize / 2;
                const openLeft = centerX > window.innerWidth / 2;
                const openUp = centerY > window.innerHeight / 2;
                const gap = 12;

                const style = {};

                if (openLeft) {
                    style.right = `${window.innerWidth - this.position.x - this.bubbleSize}px`;
                } else {
                    style.left = `${this.position.x}px`;
                }

                if (openUp) {
                    style.bottom = `${window.innerHeight - this.position.y + gap}px`;
                } else {
                    style.top = `${this.position.y + this.bubbleSize + gap}px`;
                }

                return style;
            },

            autoGrow(el) {
                el.style.height = 'auto';
                el.style.height = Math.min(el.scrollHeight, 112) + 'px';
            },

            onMessageSent() {
                this.$nextTick(() => this.scrollToBottom());
            },

            scrollToBottom() {
                const el = this.$refs.messages;
                if (el) {
                    el.scrollTop = el.scrollHeight;
                }
            },
        }));
    </script>
    @endscript
</div>
