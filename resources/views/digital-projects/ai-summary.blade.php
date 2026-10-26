<div class="space-y-3">
    @isset($error)
        <p class="text-sm text-danger-600 dark:text-danger-400">{{ $error }}</p>
    @else
        <p class="whitespace-pre-line text-sm leading-7 text-gray-700 dark:text-gray-200">{{ $text !== '' ? $text : 'مدل پاسخی نداد؛ دوباره امتحان کنید.' }}</p>
        <p class="text-xs text-gray-400">این متن را هوش مصنوعی از داده‌های ثبت‌شده نوشته؛ قبل از استفاده بخوانید.</p>
    @endisset
</div>
