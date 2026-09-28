<div class="fi-section-content-ctn" dir="rtl">
    @if ($issues->isEmpty())
        <p class="text-sm text-gray-500 dark:text-gray-400">هیچ مشکلی در این بررسی یافت نشد.</p>
    @else
        <div class="max-h-96 overflow-y-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-200 text-gray-500 dark:border-white/10 dark:text-gray-400">
                        <th class="py-1.5 text-right font-medium">شدت</th>
                        <th class="py-1.5 text-right font-medium">نوع مشکل</th>
                        <th class="py-1.5 text-right font-medium">آدرس</th>
                        <th class="py-1.5 text-right font-medium">جزئیات</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                    @foreach ($issues as $issue)
                        <tr>
                            <td class="py-1.5">
                                <x-filament::badge :color="$issue->severity->getColor()">
                                    {{ $issue->severity->getLabel() }}
                                </x-filament::badge>
                            </td>
                            <td class="py-1.5 text-gray-700 dark:text-gray-300">{{ $issue->type->getLabel() }}</td>
                            <td class="py-1.5 max-w-xs truncate text-xs text-gray-500 dark:text-gray-400" dir="ltr">
                                {{ $issue->url ?? '—' }}
                            </td>
                            <td class="py-1.5 text-xs text-gray-500 dark:text-gray-400">{{ $issue->details ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
