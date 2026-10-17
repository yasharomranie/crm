<x-filament-panels::page>
    <style>
        .inbox-layout { display: grid; gap: 1rem; grid-template-columns: minmax(0, 1fr); }
        @media (min-width: 1024px) { .inbox-layout { grid-template-columns: minmax(0, 1fr) minmax(0, 2fr); } }
        .inbox-scroll { max-height: 28rem; overflow-y: auto; }
    </style>

    <div dir="rtl" class="space-y-4">
        <p class="text-sm text-gray-500 dark:text-gray-400">
            گفتگوهای ایمیل، پیامک و واتس‌اپ با مشتریان، به ترتیب آخرین فعالیت. از اینجا می‌توانید با ایمیل یا پیامک پاسخ بدهید.
            پیام‌های دریافتی فقط واتس‌اپ است؛ ایمیل‌های ورودی به این صندوق نمی‌رسند.
        </p>

        <div class="inbox-layout">
            <div class="rounded-lg border border-gray-200 p-3 dark:border-white/10">
                <p class="mb-2 text-xs font-medium text-gray-500 dark:text-gray-400">گفتگوهای اخیر</p>
                <div class="inbox-scroll">
                    @forelse ($this->conversations() as $last)
                        <button
                            type="button"
                            wire:key="conversation-{{ $last->guest_id }}"
                            wire:click="selectGuest({{ $last->guest_id }})"
                            @class([
                                'block w-full rounded-lg border-b border-gray-100 p-2 text-right text-sm dark:border-white/5',
                                'bg-gray-50 dark:bg-white/5' => $guestId === $last->guest_id,
                            ])
                        >
                            <span class="font-medium text-gray-950 dark:text-white">{{ $last->guest?->name ?? 'مشتری حذف‌شده' }}</span>
                            <span class="block truncate text-xs text-gray-500 dark:text-gray-400">
                                {{ $last->channel->getLabel() }} - {{ \Illuminate\Support\Str::limit($last->subject ?: $last->body, 60) }}
                            </span>
                        </button>
                    @empty
                        <p class="text-sm text-gray-400">هنوز پیامی ثبت نشده است.</p>
                    @endforelse
                </div>
            </div>

            <div class="rounded-lg border border-gray-200 p-3 dark:border-white/10">
                @if ($guest = $this->selectedGuest())
                    <p class="mb-2 text-sm font-medium text-gray-950 dark:text-white">{{ $guest->name }}</p>

                    <div class="inbox-scroll space-y-2">
                        @forelse ($this->thread() as $message)
                            <div wire:key="message-{{ $message->id }}" class="rounded-lg border border-gray-200 p-2 text-sm dark:border-white/10">
                                <div class="mb-1 flex flex-wrap items-center gap-2 text-xs text-gray-500 dark:text-gray-400">
                                    <x-filament::badge :color="$message->channel->getColor()" size="xs">{{ $message->channel->getLabel() }}</x-filament::badge>
                                    <span>{{ $message->direction->getLabel() }}</span>
                                    @if ($message->status === \App\Enums\MessageDeliveryStatus::Failed)
                                        <x-filament::badge color="danger" size="xs">{{ $message->status->getLabel() }}</x-filament::badge>
                                    @endif
                                    <span>{{ $message->created_at->toJalali('Y/m/d H:i') }}</span>
                                </div>
                                @if ($message->subject)
                                    <p class="font-medium text-gray-950 dark:text-white">{{ $message->subject }}</p>
                                @endif
                                <p class="whitespace-pre-line text-gray-700 dark:text-gray-300">{{ $message->body }}</p>
                                @if ($message->error)
                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $message->error }}</p>
                                @endif
                            </div>
                        @empty
                            <p class="text-sm text-gray-400">با این مشتری هنوز پیامی رد و بدل نشده است.</p>
                        @endforelse
                    </div>

                    <form wire:submit="send" class="mt-3 space-y-2">
                        <select wire:model.live="channel" class="fi-select-input block w-full rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                            <option value="email">ایمیل{{ filled($guest->email) ? '' : ' (ایمیل ثبت نشده)' }}</option>
                            <option value="sms">پیامک{{ filled($guest->mobile) ? '' : ' (موبایل ثبت نشده)' }}</option>
                        </select>

                        @if ($channel === 'email')
                            <input type="text" wire:model="subject" placeholder="موضوع" class="fi-select-input block w-full rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                            @error('subject') <p class="text-xs text-danger-600">{{ $message }}</p> @enderror
                        @endif

                        <textarea wire:model="body" rows="3" placeholder="متن پیام" class="fi-select-input block w-full rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white"></textarea>
                        @error('body') <p class="text-xs text-danger-600">{{ $message }}</p> @enderror

                        <x-filament::button type="submit" size="sm">ارسال</x-filament::button>
                    </form>
                @else
                    <p class="text-sm text-gray-400">یک گفتگو را از فهرست انتخاب کنید.</p>
                @endif
            </div>
        </div>
    </div>
</x-filament-panels::page>
