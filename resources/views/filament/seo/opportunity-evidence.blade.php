<div class="fi-section-content-ctn space-y-4" dir="rtl">
    @if ($opportunity->subject_page_url || $opportunity->subject_query)
        <div class="rounded-md bg-gray-50 px-3 py-2 text-xs dark:bg-white/5">
            <span class="text-gray-500 dark:text-gray-400">موضوع:</span>
            <span class="font-medium text-gray-950 dark:text-white" dir="ltr">
                {{ $opportunity->subject_page_url ? urldecode($opportunity->subject_page_url) : $opportunity->subject_query }}
            </span>
        </div>
    @endif

    <div>
        <h3 class="text-sm font-semibold text-gray-950 dark:text-white">شواهد</h3>
        <dl class="mt-2 divide-y divide-gray-100 dark:divide-white/10 text-sm">
            @foreach ($opportunity->evidence as $key => $value)
                <div class="flex justify-between gap-4 py-1.5">
                    <dt class="text-gray-500 dark:text-gray-400">{{ $key }}</dt>
                    <dd class="text-gray-950 dark:text-white font-medium text-left" dir="ltr">
                        @if (is_array($value))
                            <pre class="whitespace-pre-wrap text-xs">{{ json_encode($value, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) }}</pre>
                        @else
                            {{ $value }}
                        @endif
                    </dd>
                </div>
            @endforeach
        </dl>
    </div>

    <div>
        <h3 class="text-sm font-semibold text-gray-950 dark:text-white">اقدامات پیشنهادی</h3>
        <ul class="mt-2 list-disc pr-5 text-sm text-gray-700 dark:text-gray-300 space-y-1">
            @foreach ($opportunity->recommended_actions as $action)
                <li>{{ $action }}</li>
            @endforeach
        </ul>
    </div>
</div>
