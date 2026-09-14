<x-filament-panels::page>
    @php
        $me = auth()->user();
        $conversations = $this->getConversations();
        $selected = $this->getSelectedConversation();
        $messages = $this->getMessages();
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
    @endphp

    <div
        x-data="messengerThread()"
        x-on:message-sent.window="scrollToBottom(true)"
        x-on:conversation-selected.window="scrollToBottom(true)"
        wire:poll.3s="tick"
        class="flex h-[calc(100vh-11rem)] min-h-[32rem] overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-gray-900"
    >
        {{-- Conversation list (right column in RTL) --}}
        <div class="flex w-full max-w-xs shrink-0 flex-col border-s border-gray-200 dark:border-white/10 sm:max-w-sm">
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
                    class="flex h-9 w-9 shrink-0 cursor-pointer items-center justify-center rounded-lg bg-primary-600 text-white transition hover:bg-primary-500"
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
                        class="flex w-full items-center gap-3 rounded-lg px-2.5 py-2.5 text-start transition hover:bg-gray-50 dark:hover:bg-white/5 {{ $isActive ? 'bg-primary-50 dark:bg-primary-500/10' : '' }}"
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
                                    {{ $unread > 99 ? '۹۹+' : $unread }}
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
                                    {{ \Illuminate\Support\Str::limit($last->body, 34) }}
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

        {{-- Thread panel --}}
        <div class="flex min-w-0 flex-1 flex-col">
            @if ($selected)
                @php
                    $isGroup = $selected->type === \App\Enums\ConversationType::Group;
                    $name = $selected->displayNameFor($me);
                @endphp
                <div class="flex items-center gap-3 border-b border-gray-200 px-4 py-3 dark:border-white/10">
                    <span @class([
                        'flex h-10 w-10 items-center justify-center rounded-full text-sm font-bold',
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
                                {{ $selected->participants->count() }} عضو ·
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
                            <div class="flex max-w-[75%] flex-col gap-1 {{ $isMine ? 'items-start' : 'items-end' }}">
                                @if ($isGroup && ! $isMine)
                                    <span class="px-1 text-xs font-semibold text-primary-600 dark:text-primary-400">
                                        {{ $message->sender->name }}
                                    </span>
                                @endif
                                <div @class([
                                    'whitespace-pre-line break-words rounded-2xl px-4 py-2 text-sm leading-6',
                                    'rounded-bl-md bg-primary-600 text-white' => $isMine,
                                    'rounded-br-md bg-gray-100 text-gray-900 dark:bg-white/5 dark:text-gray-100' => ! $isMine,
                                ])>{{ $message->body }}</div>
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

                <form wire:submit.prevent="sendMessage" class="flex items-end gap-2 border-t border-gray-200 p-3 dark:border-white/10">
                    <textarea
                        wire:model="body"
                        x-on:input="autoGrow($event.target)"
                        @keydown.enter="if (! $event.shiftKey) { $event.preventDefault(); $wire.sendMessage(); }"
                        rows="1"
                        placeholder="پیام خود را بنویسید... (Enter برای ارسال، Shift+Enter برای خط جدید)"
                        class="fi-input max-h-32 flex-1 resize-none rounded-xl border-gray-300 text-sm focus:ring-2 focus:ring-primary-500 dark:border-white/10 dark:bg-white/5"
                    ></textarea>
                    <button
                        type="submit"
                        aria-label="ارسال پیام"
                        title="ارسال پیام"
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
