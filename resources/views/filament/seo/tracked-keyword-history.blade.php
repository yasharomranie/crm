<div class="fi-section-content-ctn" dir="rtl">
    @if ($checks->isEmpty())
        <p class="text-sm text-gray-500 dark:text-gray-400">هنوز هیچ بررسی‌ای برای این کلمه ثبت نشده است.</p>
    @else
        <div class="max-h-96 overflow-y-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-200 text-gray-500 dark:border-white/10 dark:text-gray-400">
                        <th class="py-1.5 text-right font-medium">تاریخ</th>
                        <th class="py-1.5 text-right font-medium">رتبه</th>
                        <th class="py-1.5 text-right font-medium">آدرس پیداشده</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                    @foreach ($checks as $check)
                        <tr>
                            <td class="py-1.5 text-gray-700 dark:text-gray-300">{{ $check->date->translatedFormat('Y/m/d') }}</td>
                            <td class="py-1.5">
                                @if ($check->position !== null)
                                    <x-filament::badge :color="$check->position <= 10 ? 'success' : 'warning'">
                                        {{ $check->position }}
                                    </x-filament::badge>
                                @else
                                    <span class="text-gray-400 dark:text-gray-500">خارج از ۱۰ نتیجه‌ی اول</span>
                                @endif
                            </td>
                            <td class="py-1.5 max-w-xs truncate text-xs text-gray-500 dark:text-gray-400" dir="ltr">
                                {{ $check->found_url ?? '—' }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
