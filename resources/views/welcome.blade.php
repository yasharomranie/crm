<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="rtl">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>{{ config('app.name') }}</title>

        @fonts

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="bg-gray-50 text-gray-800 antialiased">
        <div class="flex min-h-screen flex-col items-center justify-center gap-6 p-6 text-center">
            <h1 class="text-3xl font-semibold">{{ config('app.name') }}</h1>
            <p class="max-w-xl text-gray-600">
                این پروژه در حال ساخته شدن است. صفحه‌ی عمومی سایت در فازهای بعدی تکمیل خواهد شد.
            </p>
            <a
                href="{{ url('/admin') }}"
                class="rounded-md bg-gray-900 px-5 py-2.5 text-sm font-medium text-white hover:bg-gray-700"
            >
                ورود به پنل مدیریت
            </a>
        </div>
    </body>
</html>
