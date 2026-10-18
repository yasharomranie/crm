<x-filament-panels::page>
    <div dir="rtl" class="space-y-6">
        <div class="max-w-sm">
            <label class="fi-fo-field-wrp-label text-sm font-medium text-gray-950 dark:text-white">پروژه</label>
            <select
                wire:model.live="projectId"
                class="fi-select-input mt-1 block w-full rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white"
            >
                <option value="">— یک پروژه انتخاب کن —</option>
                @foreach ($this->projects() as $project)
                    <option value="{{ $project->id }}" @selected($projectId === $project->id)>{{ $project->name }}</option>
                @endforeach
            </select>
        </div>

        <div class="max-w-sm">
            <label for="minImpressions" class="fi-fo-field-wrp-label text-sm font-medium text-gray-950 dark:text-white">حداقل ایمپرشن در ۲۸ روز</label>
            <select
                id="minImpressions"
                wire:model.live="minImpressions"
                class="fi-select-input mt-1 block w-full rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white"
            >
                @foreach (\App\Domain\Seo\WpConnector\WpCoverageGaps::MIN_IMPRESSIONS_CHOICES as $choice)
                    <option value="{{ $choice }}" @selected($minImpressions === $choice)>{{ $choice }}</option>
                @endforeach
            </select>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">برای سایت کوچک عدد کمتر بگذارید؛ عبارت‌هایی که از این کمترند نادیده گرفته می‌شوند.</p>
        </div>

        @php($gaps = $this->gaps())

        @if (! $projectId)
            <p class="text-sm text-gray-500 dark:text-gray-400">برای دیدن خلأهای محتوا، ابتدا یک پروژه انتخاب کن.</p>
        @elseif (! $gaps['has_pages'])
            <p class="text-sm text-gray-500 dark:text-gray-400">هنوز محتوایی از سایت وردپرسی این پروژه نرسیده است. پلاگین اتصال را نصب و وصل کنید (دکمه‌ی «اتصال وردپرس» در صفحه‌ی پروژه).</p>
        @elseif (! $gaps['has_searches'])
            <p class="text-sm text-gray-500 dark:text-gray-400">هنوز فهرست کلمات کلیدی این پروژه ساخته نشده. اگر Search Console را همگام کرده‌اید، در صفحه‌ی ویرایش پروژه «تحلیل الان» را بزنید و چند دقیقه صبر کنید (ساخت فهرست از صف کرون انجام می‌شود)؛ اگر همگام نکرده‌اید، اول «همگام‌سازی الان» را بزنید.</p>
        @elseif ($gaps['searches_considered'] === 0)
            <p class="text-sm text-gray-500 dark:text-gray-400">{{ number_format($gaps['searches_total']) }} عبارت از Search Console دارید، ولی هیچ‌کدام در ۲۸ روز اخیر {{ $gaps['min_impressions'] }} ایمپرشن یا بیشتر نداشته است. «حداقل ایمپرشن» را پایین‌تر بیاورید.</p>
        @else
            <div class="grid gap-6 xl:grid-cols-2">
                <section class="rounded-lg border border-gray-200 p-4 dark:border-white/10">
                    <h3 class="text-base font-semibold text-gray-950 dark:text-white">مقاله‌ی تازه بنویسید</h3>
                    <p class="mb-3 mt-1 text-xs text-gray-500 dark:text-gray-400">گوگل این عبارت‌ها را به سایت شما نشان می‌دهد (حداقل {{ $gaps['min_impressions'] }} ایمپرشن در ۲۸ روز؛ {{ number_format($gaps['searches_considered']) }} عبارت از {{ number_format($gaps['searches_total']) }} بررسی شد) ولی بیرون ده نتیجه‌ی اول است و هیچ صفحه‌ای هدفشان نیست.</p>

                    @if ($gaps['uncovered']->isEmpty())
                        <p class="text-sm text-gray-400">موردی پیدا نشد.</p>
                    @else
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="text-xs text-gray-500 dark:text-gray-400">
                                        <th class="py-1 text-right font-medium">عبارت</th>
                                        <th class="py-1 text-right font-medium">ایمپرشن</th>
                                        <th class="py-1 text-right font-medium">رتبه</th>
                                        <th class="py-1"></th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                                    @foreach ($gaps['uncovered'] as $row)
                                        <tr wire:key="uncovered-{{ $loop->index }}">
                                            <td class="py-1.5 pe-3 text-gray-800 dark:text-gray-200">{{ $row['query'] }}</td>
                                            <td class="py-1.5 pe-3 tabular-nums text-gray-600 dark:text-gray-400">{{ number_format($row['impressions']) }}</td>
                                            <td class="py-1.5 pe-3 tabular-nums text-gray-600 dark:text-gray-400">{{ $row['position'] }}</td>
                                            <td class="py-1.5 text-left">
                                                <x-filament::button size="xs" color="gray" wire:click="addToCalendar('new', @js($row['query']))">افزودن به تقویم</x-filament::button>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </section>

                <section class="rounded-lg border border-gray-200 p-4 dark:border-white/10">
                    <h3 class="text-base font-semibold text-gray-950 dark:text-white">این مقاله‌ها را بازنویسی کنید</h3>
                    <p class="mb-3 mt-1 text-xs text-gray-500 dark:text-gray-400">یک صفحه‌ی سایت برای این عبارت‌ها در رتبه‌ی ۱۱ تا ۳۰ است؛ بهبود همان صفحه سریع‌تر از نوشتن مقاله‌ی تازه جواب می‌دهد.</p>

                    @if ($gaps['improve']->isEmpty())
                        <p class="text-sm text-gray-400">موردی پیدا نشد.</p>
                    @else
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="text-xs text-gray-500 dark:text-gray-400">
                                        <th class="py-1 text-right font-medium">عبارت</th>
                                        <th class="py-1 text-right font-medium">مقاله</th>
                                        <th class="py-1 text-right font-medium">رتبه</th>
                                        <th class="py-1"></th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                                    @foreach ($gaps['improve'] as $row)
                                        <tr wire:key="improve-{{ $loop->index }}">
                                            <td class="py-1.5 pe-3 text-gray-800 dark:text-gray-200">{{ $row['query'] }}</td>
                                            <td class="py-1.5 pe-3"><a href="{{ $row['post_url'] }}" target="_blank" rel="noopener" class="text-primary-600 hover:underline dark:text-primary-400">{{ \Illuminate\Support\Str::limit($row['post_title'], 40) }}</a></td>
                                            <td class="py-1.5 pe-3 tabular-nums text-gray-600 dark:text-gray-400">{{ $row['position'] }}</td>
                                            <td class="py-1.5 text-left">
                                                <x-filament::button size="xs" color="gray" wire:click="addToCalendar('improve', @js($row['query']), {{ $row['post_id'] }})">افزودن به تقویم</x-filament::button>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </section>
            </div>
        @endif
    </div>
</x-filament-panels::page>
