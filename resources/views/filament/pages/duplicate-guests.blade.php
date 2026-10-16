<x-filament-panels::page>
    <div dir="rtl" class="space-y-6">
        <p class="text-sm text-gray-500 dark:text-gray-400">
            این پرونده‌ها به احتمال زیاد مربوط به یک نفرند (شماره‌ی موبایل یا ایمیل یکسان با نگارش متفاوت، یا نام یکسان).
            در هر گروه، پرونده‌ای را که می‌خواهید نگه داشته شود انتخاب کنید؛ بقیه با همه‌ی سابقه‌شان (رزرو، معامله، تیکت،
            کیف پول، تاریخچه) در آن ادغام و حذف می‌شوند. این کار قابل بازگشت نیست.
        </p>

        @forelse ($this->groups() as $group)
            <div wire:key="dup-group-{{ $group['key'] }}" class="rounded-lg border border-gray-200 p-3 dark:border-white/10">
                <div class="mb-2 flex flex-wrap gap-2">
                    @foreach ($group['reasons'] as $reason)
                        <x-filament::badge color="warning">{{ $reason }}</x-filament::badge>
                    @endforeach
                </div>

                <table class="w-full text-sm">
                    <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                        @foreach ($group['guests'] as $guest)
                            <tr wire:key="dup-guest-{{ $guest->id }}">
                                <td class="py-1.5 text-gray-700 dark:text-gray-300">{{ $guest->name }}</td>
                                <td class="py-1.5 text-xs text-gray-500 dark:text-gray-400" dir="ltr">{{ $guest->mobile ?: '—' }}</td>
                                <td class="py-1.5 text-xs text-gray-500 dark:text-gray-400" dir="ltr">{{ $guest->email ?: '—' }}</td>
                                <td class="py-1.5 text-xs text-gray-500 dark:text-gray-400">
                                    {{ \Illuminate\Support\Number::toPersian($guest->bookings_count) }} رزرو،
                                    {{ \Illuminate\Support\Number::toPersian($guest->deals_count) }} معامله،
                                    {{ \Illuminate\Support\Number::toPersian($guest->tickets_count) }} تیکت
                                </td>
                                <td class="py-1.5 text-left">
                                    <x-filament::button
                                        size="xs"
                                        color="gray"
                                        wire:click="mergeGroup({{ $guest->id }}, @js($group['guests']->pluck('id')->reject(fn ($id) => $id === $guest->id)->values()->all()))"
                                        wire:confirm="پرونده‌ی «{{ $guest->name }}» نگه داشته شود و بقیه‌ی این گروه در آن ادغام و حذف شوند؟ این کار قابل بازگشت نیست."
                                    >
                                        نگه‌داشتن این پرونده
                                    </x-filament::button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @empty
            <p class="text-sm text-gray-500 dark:text-gray-400">هیچ پرونده‌ی تکراری‌ای پیدا نشد.</p>
        @endforelse
    </div>
</x-filament-panels::page>
