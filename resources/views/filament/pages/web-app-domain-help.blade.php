<div class="fi-section-content-ctn space-y-5 text-sm leading-7" dir="rtl">
    <p class="text-gray-600 dark:text-gray-400">
        اتصال یک دامنهٔ اختصاصی سه بخش دارد که هرکدام دست یک نفر است. این پنل فقط بخش «۱» را خودش انجام می‌دهد؛ بخش‌های «۲» و «۳» همیشه باید توسط خودت (یا مدیر هاست) انجام شوند - هیچ نرم‌افزاری، حتی این پنل، نمی‌تواند بدون دسترسی به اکانت رجیستراردامنه و پنل هاست تو، این دو بخش را جایت انجام بدهد.
    </p>

    <div class="rounded-lg border border-gray-200 p-4 dark:border-white/10">
        <p class="font-semibold text-gray-950 dark:text-white">۱. سمت اپلیکیشن (خودکار، همین پنل)</p>
        <p class="mt-1 text-gray-600 dark:text-gray-400">
            نام دامنه را در فیلد بالا وارد کن، «فعال‌سازی دامنه اختصاصی» را روشن کن و ذخیره کن. بعد از اینکه در مراحل ۲ و ۳ دامنه واقعاً وصل شد و روی دکمهٔ «بررسی اتصال DNS» زدی، از آن به بعد باز کردن آدرس دامنه‌ات مستقیماً همان اپلیکیشن مشتری را نشان می‌دهد - بدون نیاز به دیپلوی دوباره یا هیچ کار دیگری از سمت ما.
        </p>
    </div>

    <div class="rounded-lg border border-gray-200 p-4 dark:border-white/10">
        <p class="font-semibold text-gray-950 dark:text-white">۲. سمت دامنه - تنظیم DNS (کار خودت، در پنل رجیستراردامنه)</p>
        <p class="mt-1 text-gray-600 dark:text-gray-400">
            وارد پنل همان‌جایی شو که دامنه را از آن خریدی (مثل پنل ثبت دامنه) و یکی از این دو رکورد را - بسته به نوع دامنه‌ات - اضافه کن:
        </p>
        <ul class="mt-2 list-inside list-disc space-y-2 text-gray-600 dark:text-gray-400">
            <li>
                <span class="font-medium text-gray-950 dark:text-white">اگر یک ساب‌دامین است</span> (مثل <span dir="ltr" class="font-mono">app.کسب‌وکار-شما.com</span>):
                یک رکورد <span class="font-mono">CNAME</span> بساز که مقدارش این باشد:
                <div dir="ltr" class="mt-1 rounded bg-gray-50 px-2 py-1 font-mono text-xs dark:bg-white/5">{{ $cnameTarget }}</div>
            </li>
            @if ($ip)
                <li>
                    <span class="font-medium text-gray-950 dark:text-white">اگر خود ریشهٔ دامنه است</span> (بدون ساب‌دامین، مثل خود <span dir="ltr" class="font-mono">کسب‌وکار-شما.com</span>) و رجیستراردامنه‌ات اجازهٔ CNAME روی ریشه را نمی‌دهد:
                    یک رکورد <span class="font-mono">A</span> بساز که مقدارش این آی‌پی باشد:
                    <div dir="ltr" class="mt-1 rounded bg-gray-50 px-2 py-1 font-mono text-xs dark:bg-white/5">{{ $ip }}</div>
                </li>
            @endif
        </ul>
        <p class="mt-2 text-xs text-gray-500 dark:text-gray-500">
            بعد از ثبت رکورد، روی دکمهٔ «بررسی اتصال DNS» بزن. انتشار تغییرات DNS در دنیا گاهی چند دقیقه و گاهی تا ۲۴-۴۸ ساعت طول می‌کشد؛ اگر اول بار «هنوز شناسایی نشد» گرفتی، نگران نباش و کمی بعد دوباره امتحان کن.
        </p>
    </div>

    <div class="rounded-lg border border-gray-200 p-4 dark:border-white/10">
        <p class="font-semibold text-gray-950 dark:text-white">۳. سمت هاست - معرفی دامنه به سرور (کار خودت یا مدیر هاست)</p>
        <p class="mt-1 text-gray-600 dark:text-gray-400">
            درست کردن DNS به‌تنهایی کافی نیست - خودِ سروری که این سایت روی آن است هم باید بداند درخواست‌های آن دامنه را قبول کند. روی هاست اشتراکی معمولاً این‌طور انجام می‌شود:
        </p>
        <ol class="mt-2 list-inside list-decimal space-y-1 text-gray-600 dark:text-gray-400">
            <li>وارد پنل هاست شو (مثلاً cPanel).</li>
            <li>دامنهٔ جدید را به‌عنوان «Addon Domain» یا «Parked Domain» اضافه کن.</li>
            <li>مسیر (Document Root) آن را دقیقاً همان پوشهٔ <span class="font-mono">public</span> همین سایت انتخاب کن - نه یک پوشهٔ جدید و خالی.</li>
        </ol>
        <p class="mt-2 text-xs text-gray-500 dark:text-gray-500">
            گواهی SSL (قفل سبز/https) روی اکثر هاست‌های اشتراکی بعد از این مرحله خودش به‌صورت خودکار صادر می‌شود (مثل AutoSSL در cPanel)، به شرطی که DNS مرحلهٔ ۲ درست تنظیم شده باشد.
        </p>
    </div>

    <p class="text-gray-600 dark:text-gray-400">
        بعد از انجام درست هر سه مرحله: باز کردن <span class="font-mono" dir="ltr">https://دامنه‌ات</span> مستقیماً همان اپلیکیشن مشتری را نشان می‌دهد (بدون پیشوند <span class="font-mono">/app</span>) - و آدرس <span class="font-mono">/app</span> روی دامنهٔ اصلی همین سامانه هم همیشه، حتی بدون هیچ دامنهٔ اختصاصی، کار می‌کند.
    </p>
</div>
