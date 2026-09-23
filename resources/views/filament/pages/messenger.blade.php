<x-filament-panels::page>
    @php
        $me = auth()->user();
        $conversations = $this->getConversations();
        $selected = $this->getSelectedConversation();
        $messages = $this->getConversationMessages();
        $lastReadId = $selected?->lastReadMessageIdExcluding($me);

        $initialsOf = fn (string $name) => mb_substr(trim($name), 0, 1);

        $formatDaySeparator = function (\Illuminate\Support\Carbon $date): string {
            if ($date->isToday()) {
                return 'امروز';
            }

            if ($date->isYesterday()) {
                return 'دیروز';
            }

            return $date->toJalali('Y/m/d');
        };

        $formatSize = function (?int $sizeKb): string {
            if (! $sizeKb) {
                return '';
            }

            return $sizeKb >= 1024
                ? number_format($sizeKb / 1024, 1).' مگابایت'
                : "{$sizeKb} کیلوبایت";
        };
    @endphp

    <div
        x-data="messengerThread()"
        x-on:message-sent.window="scrollToBottom(true)"
        x-on:conversation-selected.window="scrollToBottom(true)"
        wire:poll.3s="tick"
        class="flex h-[calc(100dvh-12rem)] min-h-[26rem] overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm md:h-[calc(100dvh-14rem)] md:min-h-[30rem] dark:border-white/10 dark:bg-gray-900"
    >
        {{-- Conversation list: full-width "screen" on mobile (hidden while
            a thread is open), a fixed-width sidebar from md: up, always
            visible alongside the thread - same app-style collapse pattern
            as the AI assistant's own conversation list. --}}
        <div
            class="w-full shrink-0 flex-col border-s border-gray-200 dark:border-white/10 md:flex md:w-auto md:max-w-xs sm:max-w-sm"
            :class="mobileView === 'thread' ? 'hidden md:flex' : 'flex'"
        >
            <div class="flex items-center justify-between gap-2 border-b border-gray-200 p-3 dark:border-white/10">
                <div class="relative flex-1">
                    <x-heroicon-o-magnifying-glass class="pointer-events-none absolute inset-y-0 end-3 my-auto h-4 w-4 text-gray-400" />
                    <input
                        type="search"
                        wire:model.live.debounce.300ms="search"
                        placeholder="جستجوی گفتگو..."
                        class="fi-input w-full rounded-lg border-none bg-gray-100 py-2 pe-9 ps-3 text-sm focus:ring-2 focus:ring-primary-500 dark:bg-white/5"
                    />
                </div>
                <button
                    type="button"
                    wire:click="openNewConversationModal"
                    aria-label="گفتگوی جدید"
                    title="گفتگوی جدید"
                    class="flex h-10 w-10 shrink-0 cursor-pointer items-center justify-center rounded-lg bg-primary-600 text-white transition hover:bg-primary-500"
                >
                    <x-heroicon-o-pencil-square class="h-4 w-4" />
                </button>
            </div>

            <div class="flex-1 space-y-1 overflow-y-auto p-2">
                @forelse ($conversations as $conversation)
                    @php
                        $isActive = $selected && $selected->id === $conversation->id;
                        $isGroup = $conversation->type === \App\Enums\ConversationType::Group;
                        $name = $conversation->displayNameFor($me);
                        $unread = $conversation->unreadCountFor($me);
                        $last = $conversation->latestMessage;
                    @endphp
                    <button
                        type="button"
                        wire:click="selectConversation({{ $conversation->id }})"
                        x-on:click="mobileView = 'thread'"
                        class="flex w-full items-center gap-3 rounded-lg px-2.5 py-3 text-start transition hover:bg-gray-50 md:py-2.5 dark:hover:bg-white/5 {{ $isActive ? 'bg-primary-50 dark:bg-primary-500/10' : '' }}"
                    >
                        <span class="relative shrink-0">
                            <span @class([
                                'flex h-11 w-11 items-center justify-center rounded-full text-sm font-bold',
                                'bg-info-100 text-info-700 dark:bg-info-500/20 dark:text-info-300' => $isGroup,
                                'bg-primary-100 text-primary-700 dark:bg-primary-500/20 dark:text-primary-300' => ! $isGroup,
                            ])>
                                @if ($isGroup)
                                    <x-heroicon-o-user-group class="h-5 w-5" />
                                @else
                                    {{ $initialsOf($name) }}
                                @endif
                            </span>
                            @if ($unread > 0)
                                <span class="absolute -top-1 -start-1 flex h-5 min-w-[1.25rem] items-center justify-center rounded-full bg-danger-600 px-1 text-[11px] font-bold leading-none text-white">
                                    {{ $unread > 99 ? '۹۹+' : \Illuminate\Support\Number::toPersian($unread) }}
                                </span>
                            @endif
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="flex items-center justify-between gap-2">
                                <span @class([
                                    'truncate text-sm',
                                    'font-semibold text-gray-950 dark:text-white' => $unread > 0,
                                    'font-medium text-gray-800 dark:text-gray-200' => $unread === 0,
                                ])>{{ $name }}</span>
                                @if ($last)
                                    <span class="shrink-0 text-[11px] text-gray-400">{{ $last->created_at->format('H:i') }}</span>
                                @endif
                            </span>
                            <span class="mt-0.5 flex items-center gap-1 truncate text-xs text-gray-500 dark:text-gray-400">
                                @if ($last)
                                    @if ($last->sender_id === $me->id)
                                        <span class="text-gray-400">شما:</span>
                                    @endif
                                    @if ($last->attachmentIsAudio())
                                        <x-heroicon-o-microphone class="h-3.5 w-3.5 shrink-0" /> پیام صوتی
                                    @elseif ($last->file_path)
                                        <x-heroicon-o-paper-clip class="h-3.5 w-3.5 shrink-0" /> {{ $last->file_name ?: 'فایل' }}
                                    @else
                                        {{ \Illuminate\Support\Str::limit($last->body, 34) }}
                                    @endif
                                @else
                                    گفتگو را شروع کنید
                                @endif
                            </span>
                        </span>
                    </button>
                @empty
                    <div class="flex h-full flex-col items-center justify-center gap-3 px-6 py-12 text-center">
                        <x-heroicon-o-chat-bubble-left-right class="h-10 w-10 text-gray-300 dark:text-gray-600" />
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            هنوز هیچ گفتگویی ندارید.
                        </p>
                        <button type="button" wire:click="openNewConversationModal" class="fi-link text-sm font-medium text-primary-600">
                            شروع گفتگوی جدید
                        </button>
                    </div>
                @endforelse
            </div>
        </div>

        {{-- Thread panel: full-width "screen" on mobile (hidden while the
            list is open), the remaining flexible column from md: up. --}}
        <div
            class="min-w-0 flex-1 flex-col md:flex"
            :class="mobileView === 'list' ? 'hidden' : 'flex'"
        >
            @if ($selected)
                @php
                    $isGroup = $selected->type === \App\Enums\ConversationType::Group;
                    $name = $selected->displayNameFor($me);
                @endphp
                <div class="flex items-center gap-2 border-b border-gray-200 px-2 py-2 md:gap-3 md:px-4 md:py-3 dark:border-white/10">
                    {{-- Mobile-only back button: the sidebar is off-screen
                        here, so this is the only way back to it. --}}
                    <button
                        type="button"
                        x-on:click="mobileView = 'list'"
                        aria-label="بازگشت به فهرست گفتگوها"
                        title="بازگشت به فهرست گفتگوها"
                        class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg text-gray-500 transition hover:bg-gray-100 md:hidden dark:text-gray-400 dark:hover:bg-white/5"
                    >
                        <x-heroicon-o-chevron-right class="h-5 w-5" />
                    </button>
                    <span @class([
                        'flex h-10 w-10 shrink-0 items-center justify-center rounded-full text-sm font-bold',
                        'bg-info-100 text-info-700 dark:bg-info-500/20 dark:text-info-300' => $isGroup,
                        'bg-primary-100 text-primary-700 dark:bg-primary-500/20 dark:text-primary-300' => ! $isGroup,
                    ])>
                        @if ($isGroup)
                            <x-heroicon-o-user-group class="h-5 w-5" />
                        @else
                            {{ $initialsOf($name) }}
                        @endif
                    </span>
                    <div class="min-w-0">
                        <p class="truncate font-semibold text-gray-950 dark:text-white">{{ $name }}</p>
                        <p class="truncate text-xs text-gray-500 dark:text-gray-400">
                            @if ($isGroup)
                                {{ \Illuminate\Support\Number::toPersian($selected->participants->count()) }} عضو ·
                                {{ $selected->participants->pluck('user.name')->filter()->implode('، ') }}
                            @else
                                همکار سازمانی
                            @endif
                        </p>
                    </div>
                </div>

                <div x-ref="messages" class="flex-1 space-y-3 overflow-y-auto px-4 py-4">
                    @php $previousDate = null; @endphp
                    @forelse ($messages as $message)
                        @php
                            $isMine = $message->sender_id === $me->id;
                            $messageDate = $message->created_at->copy()->startOfDay();
                            $showSeparator = $previousDate === null || ! $previousDate->equalTo($messageDate);
                            $previousDate = $messageDate;
                            $isRead = $isMine && $lastReadId !== null && $message->id <= $lastReadId;
                        @endphp
                        @if ($showSeparator)
                            <div class="flex justify-center py-1">
                                <span class="rounded-full bg-gray-100 px-3 py-1 text-[11px] font-medium text-gray-500 dark:bg-white/5 dark:text-gray-400">
                                    {{ $formatDaySeparator($message->created_at) }}
                                </span>
                            </div>
                        @endif
                        <div class="flex {{ $isMine ? 'justify-start' : 'justify-end' }}">
                            <div class="flex max-w-[85%] flex-col gap-1 md:max-w-[75%] {{ $isMine ? 'items-start' : 'items-end' }}">
                                @if ($isGroup && ! $isMine)
                                    <span class="px-1 text-xs font-semibold text-primary-600 dark:text-primary-400">
                                        {{ $message->sender->name }}
                                    </span>
                                @endif
                                <div @class([
                                    'max-w-full rounded-2xl px-4 py-2 text-sm leading-6',
                                    'rounded-bl-md bg-primary-600 text-white' => $isMine,
                                    'rounded-br-md bg-gray-100 text-gray-900 dark:bg-white/5 dark:text-gray-100' => ! $isMine,
                                ])>
                                    @if ($message->file_path)
                                        <div class="{{ $message->body ? 'mb-2' : '' }}">
                                            @if ($message->attachmentIsImage())
                                                <a href="{{ route('messages.attachment', $message) }}" target="_blank" rel="noopener">
                                                    <img
                                                        src="{{ route('messages.attachment', $message) }}"
                                                        alt="{{ $message->file_name }}"
                                                        loading="lazy"
                                                        class="max-h-64 w-full rounded-lg object-cover"
                                                    />
                                                </a>
                                            @elseif ($message->attachmentIsAudio())
                                                <audio controls preload="metadata" src="{{ route('messages.attachment', $message) }}" class="h-10 w-56 max-w-full"></audio>
                                            @else
                                                <a
                                                    href="{{ route('messages.attachment', $message) }}"
                                                    target="_blank"
                                                    rel="noopener"
                                                    @class([
                                                        'flex items-center gap-2 rounded-lg px-2 py-1.5',
                                                        'bg-white/10' => $isMine,
                                                        'bg-white dark:bg-white/5' => ! $isMine,
                                                    ])
                                                >
                                                    <x-heroicon-o-document class="h-6 w-6 shrink-0" />
                                                    <span class="min-w-0">
                                                        <span class="block truncate text-xs font-medium">{{ $message->file_name }}</span>
                                                        <span class="block text-[11px] opacity-75">{{ $formatSize($message->size_kb) }}</span>
                                                    </span>
                                                </a>
                                            @endif
                                        </div>
                                    @endif
                                    @if ($message->body)
                                        <span class="whitespace-pre-line break-words">{{ $message->body }}</span>
                                    @endif
                                </div>
                                <div class="flex items-center gap-1 px-1 text-[11px] text-gray-400">
                                    <span>{{ $message->created_at->format('H:i') }}</span>
                                    @if ($isMine)
                                        <span class="relative inline-block h-3.5 w-4" aria-hidden="true">
                                            <x-heroicon-o-check class="absolute inset-y-0 start-0 h-3.5 w-3.5 {{ $isRead ? 'text-primary-500' : 'text-gray-400' }}" />
                                            <x-heroicon-o-check class="absolute inset-y-0 start-1.5 h-3.5 w-3.5 {{ $isRead ? 'text-primary-500' : 'text-gray-400' }}" />
                                        </span>
                                        <span class="sr-only">{{ $isRead ? 'خوانده شد' : 'ارسال شد' }}</span>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="flex h-full flex-col items-center justify-center gap-2 text-center">
                            <x-heroicon-o-chat-bubble-left-right class="h-10 w-10 text-gray-300 dark:text-gray-600" />
                            <p class="text-sm text-gray-500 dark:text-gray-400">هنوز پیامی رد و بدل نشده. اولین پیام را بفرستید!</p>
                        </div>
                    @endforelse
                </div>

                {{-- Dictation "listening" indicator + voice-note recording
                    indicator - same slot ai-assistant.blade.php uses for its
                    own listening indicator. --}}
                <div
                    x-show="listening || speechError || recording || recordError"
                    x-cloak
                    class="flex items-center gap-1.5 border-t border-gray-200 px-4 pt-2 text-xs dark:border-white/10"
                >
                    <span x-show="listening" class="flex items-center gap-1.5 text-primary-600 dark:text-primary-400">
                        <span class="h-1.5 w-1.5 animate-pulse rounded-full bg-current"></span>
                        <span x-text="interimText || 'در حال شنیدن...'"></span>
                    </span>
                    <span x-show="speechError" x-text="speechError" class="text-danger-600 dark:text-danger-400"></span>
                    <span x-show="recording" class="flex items-center gap-1.5 text-danger-600 dark:text-danger-400">
                        <span class="h-1.5 w-1.5 animate-pulse rounded-full bg-current"></span>
                        <span>در حال ضبط پیام صوتی...</span>
                    </span>
                    <span x-show="recordError" x-text="recordError" class="text-danger-600 dark:text-danger-400"></span>
                </div>

                {{-- Pending attachment (picked, not yet sent) --}}
                @if ($attachment)
                    <div class="flex items-center gap-2 border-t border-gray-200 px-4 pt-2 text-xs dark:border-white/10">
                        <x-heroicon-o-paper-clip class="h-4 w-4 shrink-0 text-gray-400" />
                        <span class="min-w-0 flex-1 truncate text-gray-600 dark:text-gray-300">{{ $attachment->getClientOriginalName() }}</span>
                        <button type="button" wire:click="removeAttachment" aria-label="حذف پیوست" class="shrink-0 text-gray-400 hover:text-danger-600">
                            <x-heroicon-o-x-mark class="h-4 w-4" />
                        </button>
                    </div>
                @endif
                <div wire:loading wire:target="attachment" class="border-t border-gray-200 px-4 pt-2 text-xs text-gray-500 dark:border-white/10 dark:text-gray-400">
                    در حال آپلود فایل...
                </div>

                <form
                    wire:submit.prevent="sendMessage"
                    class="flex items-end gap-2 p-3 pb-[max(0.75rem,env(safe-area-inset-bottom))]"
                    :class="(listening || speechError || recording || recordError || {{ $attachment ? 'true' : 'false' }}) ? '' : 'border-t border-gray-200 dark:border-white/10'"
                >
                    <input type="file" x-ref="attachmentInput" wire:model="attachment" class="hidden" />
                    <button
                        type="button"
                        x-on:click="$refs.attachmentInput.click()"
                        aria-label="پیوست فایل"
                        title="پیوست فایل"
                        class="flex h-10 w-10 shrink-0 cursor-pointer items-center justify-center rounded-xl text-gray-500 transition hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-white/10"
                    >
                        <x-heroicon-o-paper-clip class="h-5 w-5" />
                    </button>
                    <textarea
                        wire:model="body"
                        x-on:input="autoGrow($event.target)"
                        @keydown.enter="if (! $event.shiftKey) { $event.preventDefault(); $wire.sendMessage(); }"
                        rows="1"
                        placeholder="پیام خود را بنویسید... (Enter برای ارسال، Shift+Enter برای خط جدید)"
                        wire:loading.attr="disabled"
                        wire:target="sendMessage"
                        class="fi-input max-h-32 min-h-[2.5rem] flex-1 resize-none rounded-xl border-gray-300 text-sm focus:ring-2 focus:ring-primary-500 dark:border-white/10 dark:bg-white/5"
                    ></textarea>
                    <button
                        type="button"
                        x-show="speechSupported"
                        x-on:click="toggleListening()"
                        :aria-label="listening ? 'توقف تایپ صوتی' : 'تایپ صوتی'"
                        :title="listening ? 'توقف تایپ صوتی' : 'تایپ صوتی (تبدیل گفتار به متن)'"
                        class="flex h-10 w-10 shrink-0 cursor-pointer items-center justify-center rounded-xl transition"
                        :class="listening
                            ? 'bg-danger-500 text-white'
                            : 'bg-gray-100 text-gray-600 hover:bg-gray-200 dark:bg-white/5 dark:text-gray-300 dark:hover:bg-white/10'"
                    >
                        <x-heroicon-o-stop-circle class="h-5 w-5" x-show="listening" x-cloak />
                        <x-heroicon-o-microphone class="h-5 w-5" x-show="!listening" />
                    </button>
                    {{-- Single swap slot, WhatsApp-style: a voice-note record
                        button when there's nothing typed/attached yet, the
                        send button the moment there is. --}}
                    <button
                        type="button"
                        x-show="! ($wire.body ?? '').trim() && ! {{ $attachment ? 'true' : 'false' }} && recordingSupported"
                        x-on:click="toggleRecording()"
                        :aria-label="recording ? 'توقف ضبط پیام صوتی' : 'ارسال پیام صوتی'"
                        :title="recording ? 'توقف ضبط پیام صوتی' : 'ارسال پیام صوتی'"
                        class="flex h-10 w-10 shrink-0 cursor-pointer items-center justify-center rounded-xl transition"
                        :class="recording
                            ? 'bg-danger-500 text-white'
                            : 'bg-primary-600 text-white hover:bg-primary-500'"
                    >
                        <x-heroicon-o-stop-circle class="h-5 w-5" x-show="recording" x-cloak />
                        <x-heroicon-o-microphone class="h-5 w-5" x-show="!recording" />
                    </button>
                    <button
                        type="submit"
                        x-show="{{ $attachment ? 'true' : 'false' }} || ($wire.body ?? '').trim() || ! recordingSupported"
                        aria-label="ارسال پیام"
                        title="ارسال پیام"
                        wire:loading.attr="disabled"
                        wire:target="sendMessage"
                        class="flex h-10 w-10 shrink-0 cursor-pointer items-center justify-center rounded-xl bg-primary-600 text-white transition hover:bg-primary-500 disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        <x-heroicon-o-paper-airplane class="h-5 w-5 -scale-x-100" />
                    </button>
                </form>
            @else
                <div class="flex flex-1 flex-col items-center justify-center gap-3 text-center">
                    <x-heroicon-o-chat-bubble-left-right class="h-12 w-12 text-gray-300 dark:text-gray-600" />
                    <p class="text-sm text-gray-500 dark:text-gray-400">یک گفتگو را انتخاب کنید یا یک گفتگوی جدید شروع کنید.</p>
                    <button type="button" wire:click="openNewConversationModal" class="fi-btn rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-500">
                        شروع گفتگوی جدید
                    </button>
                </div>
            @endif
        </div>
    </div>

    {{-- New conversation modal --}}
    <div
        x-show="$wire.showNewConversationModal"
        x-cloak
        class="fixed inset-0 z-50 flex items-center justify-center p-4"
        style="display: none;"
    >
        <div class="absolute inset-0 bg-gray-950/50" wire:click="closeNewConversationModal"></div>

        <div class="relative w-full max-w-md rounded-xl bg-white p-5 shadow-xl dark:bg-gray-900">
            <div class="mb-4 flex items-center justify-between">
                <h2 class="text-base font-semibold text-gray-950 dark:text-white">گفتگوی جدید</h2>
                <button type="button" wire:click="closeNewConversationModal" aria-label="بستن" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">
                    <x-heroicon-o-x-mark class="h-5 w-5" />
                </button>
            </div>

            <div class="mb-4 flex gap-2 rounded-lg bg-gray-100 p-1 dark:bg-white/5">
                <button
                    type="button"
                    wire:click="$set('newConversationType', 'direct')"
                    @class([
                        'flex-1 rounded-md py-1.5 text-sm font-medium transition',
                        'bg-white text-gray-950 shadow dark:bg-gray-800 dark:text-white' => $newConversationType === 'direct',
                        'text-gray-500 dark:text-gray-400' => $newConversationType !== 'direct',
                    ])
                >
                    گفتگوی دو نفره
                </button>
                <button
                    type="button"
                    wire:click="$set('newConversationType', 'group')"
                    @class([
                        'flex-1 rounded-md py-1.5 text-sm font-medium transition',
                        'bg-white text-gray-950 shadow dark:bg-gray-800 dark:text-white' => $newConversationType === 'group',
                        'text-gray-500 dark:text-gray-400' => $newConversationType !== 'group',
                    ])
                >
                    گروه
                </button>
            </div>

            @if ($newConversationType === 'direct')
                <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">همکار</label>
                <select wire:model="newConversationUserId" class="fi-input w-full rounded-lg text-sm">
                    <option value="">یک همکار را انتخاب کنید</option>
                    @foreach ($this->getSelectableUsers() as $user)
                        <option value="{{ $user->id }}">{{ $user->name }}</option>
                    @endforeach
                </select>
            @else
                <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">نام گروه</label>
                <input type="text" wire:model="newConversationName" placeholder="مثلاً «تیم پذیرش»" class="fi-input mb-3 w-full rounded-lg text-sm" />

                <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">اعضا</label>
                <div class="max-h-48 space-y-1 overflow-y-auto rounded-lg border border-gray-200 p-2 dark:border-white/10">
                    @foreach ($this->getSelectableUsers() as $user)
                        <label class="flex cursor-pointer items-center gap-2 rounded-md px-2 py-1.5 text-sm hover:bg-gray-50 dark:hover:bg-white/5">
                            <input type="checkbox" wire:model="newConversationParticipantIds" value="{{ $user->id }}" class="h-4 w-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500 dark:border-white/20" />
                            {{ $user->name }}
                        </label>
                    @endforeach
                </div>
            @endif

            <div class="mt-5 flex justify-end gap-2">
                <button type="button" wire:click="closeNewConversationModal" class="rounded-lg px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-white/5">
                    انصراف
                </button>
                <button type="button" wire:click="startConversation" class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-500">
                    شروع گفتگو
                </button>
            </div>
        </div>
    </div>

    @script
    <script>
        Alpine.data('messengerThread', () => ({
            wasNearBottom: true,
            observer: null,
            // Below md:, the conversation list and the thread are two
            // full-width "screens" that swap in and out (an app-style
            // master/detail collapse), the same pattern as the AI
            // assistant's own thread view - 'thread' first since a
            // conversation is already auto-selected on mount.
            mobileView: 'thread',

            // --- Voice typing / dictation (free, client-side only) - the
            // exact same Web Speech API approach as ai-assistant.blade.php,
            // just writing into `body` instead of that page's own state. ---
            speechSupported: false,
            listening: false,
            interimText: '',
            speechError: '',
            recognition: null,
            speechBaseText: '',
            speechCommitted: '',
            speechStoppedByUser: false,

            // --- Voice messages (real recorded audio clips, sent as a
            // file attachment) - a different feature from dictation above:
            // this records actual sound and uploads it, it never touches
            // the text `body`. ---
            recordingSupported: false,
            recording: false,
            recordError: '',
            mediaRecorder: null,
            audioChunks: [],

            init() {
                this.scrollToBottom(true);
                this.initSpeech();
                this.recordingSupported = !! (navigator.mediaDevices?.getUserMedia && window.MediaRecorder);

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
                // continuous dictation sessions. Independent short
                // sessions (one utterance each, auto-restarted onend)
                // never hit that bug - see ai-assistant.blade.php, where
                // this was first diagnosed.
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

            async toggleRecording() {
                if (! this.recordingSupported) {
                    return;
                }

                if (this.recording) {
                    this.mediaRecorder?.stop();

                    return;
                }

                this.recordError = '';

                let stream;
                try {
                    stream = await navigator.mediaDevices.getUserMedia({ audio: true });
                } catch (e) {
                    this.recordError = 'اجازهٔ دسترسی به میکروفون داده نشد.';

                    return;
                }

                this.audioChunks = [];
                // Let the browser pick its own supported mime type
                // (webm on Chrome/Firefox, mp4/aac on Safari) rather than
                // forcing one it might not actually support.
                this.mediaRecorder = new MediaRecorder(stream);

                this.mediaRecorder.ondataavailable = (event) => {
                    if (event.data.size > 0) {
                        this.audioChunks.push(event.data);
                    }
                };

                this.mediaRecorder.onstop = () => {
                    stream.getTracks().forEach((track) => track.stop());
                    this.recording = false;
                    this.uploadVoiceNote();
                };

                this.mediaRecorder.start();
                this.recording = true;
            },

            async uploadVoiceNote() {
                if (this.audioChunks.length === 0) {
                    return;
                }

                const blob = new Blob(this.audioChunks, { type: this.mediaRecorder.mimeType || 'audio/webm' });
                this.audioChunks = [];

                const extension = (blob.type.split('/')[1] || 'webm').split(';')[0];
                const formData = new FormData();
                formData.append('conversation_id', this.$wire.conversationId);
                formData.append('audio', blob, `voice-note.${extension}`);

                try {
                    const response = await fetch('{{ route('messages.voice-note.store') }}', {
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': this.csrfToken() },
                        body: formData,
                    });

                    if (! response.ok) {
                        throw new Error('upload failed');
                    }

                    await this.$wire.tick();
                    this.$dispatch('message-sent');
                } catch (e) {
                    this.recordError = 'ارسال پیام صوتی ناموفق بود. دوباره تلاش کنید.';
                }
            },

            csrfToken() {
                return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
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
