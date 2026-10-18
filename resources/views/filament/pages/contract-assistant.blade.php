<x-filament-panels::page>
    <style>
        .contract-markdown :where(h1, h2, h3) { font-weight: 700; margin-block: 1rem .4rem; }
        .contract-markdown h2 { font-size: 1rem; }
        .contract-markdown p { margin-block: .4rem; line-height: 1.9; }
        .contract-markdown ul, .contract-markdown ol { margin-inline-start: 1.25rem; margin-block: .4rem; }
        .contract-markdown strong { font-weight: 700; }
    </style>

    <div class="space-y-4">
        @if (! \App\Models\AiProvider::active())
            <div class="flex items-center gap-3 rounded-xl border border-warning-200 bg-warning-50 p-4 text-sm text-warning-800 dark:border-warning-500/20 dark:bg-warning-500/10 dark:text-warning-300">
                <x-heroicon-o-exclamation-triangle class="h-5 w-5 shrink-0" />
                <span>
                    هیچ ارائه‌دهندهٔ هوش مصنوعی فعالی تنظیم نشده است.
                    <a href="{{ \App\Filament\Resources\AiProviders\AiProviderResource::getUrl() }}" class="font-semibold underline">از اینجا یکی تنظیم کنید.</a>
                </span>
            </div>
        @endif

        <div class="flex justify-end">
            <div class="relative" x-data="{ open: false }">
                <button
                    type="button"
                    wire:click="loadModelOptions"
                    x-on:click="open = ! open"
                    class="flex max-w-[14rem] items-center gap-1.5 rounded-lg border border-gray-200 px-2.5 py-1.5 text-xs text-gray-600 hover:border-primary-300 hover:text-primary-600 dark:border-white/10 dark:text-gray-300"
                >
                    <x-heroicon-o-cpu-chip class="h-3.5 w-3.5 shrink-0" />
                    <span class="truncate">مدل: {{ $this->preferredModel() ?: 'پیش‌فرض ارائه‌دهنده' }}</span>
                </button>
                <div x-show="open" x-cloak x-on:click.outside="open = false" class="absolute left-0 top-full z-20 mt-1 max-h-64 w-56 max-w-[calc(100vw-2rem)] overflow-y-auto rounded-lg border border-gray-200 bg-white py-1 shadow-lg dark:border-white/10 dark:bg-gray-800">
                    <button type="button" wire:click="updatePreferredModel('')" x-on:click="open = false" @class(['flex w-full items-center px-3 py-2 text-start text-xs hover:bg-gray-50 dark:hover:bg-white/5', 'font-medium text-primary-600 dark:text-primary-400' => blank($this->preferredModel()), 'text-gray-700 dark:text-gray-200' => filled($this->preferredModel())])>
                        پیش‌فرض ارائه‌دهنده
                    </button>
                    @foreach ($this->modelOptions as $modelId)
                        <button type="button" wire:click="updatePreferredModel('{{ $modelId }}')" x-on:click="open = false" @class(['flex w-full items-center px-3 py-2 text-start text-xs hover:bg-gray-50 dark:hover:bg-white/5', 'font-medium text-primary-600 dark:text-primary-400' => $this->preferredModel() === $modelId, 'text-gray-700 dark:text-gray-200' => $this->preferredModel() !== $modelId])>
                            {{ $modelId }}
                        </button>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- ورودی‌ها --}}
        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-900">
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-950 dark:text-white">دستهٔ قرارداد</label>
                    <select wire:model="categoryId" class="fi-input block w-full rounded-lg border-gray-300 text-sm dark:border-white/10 dark:bg-white/5 dark:text-white">
                        <option value="">— انتخاب کنید —</option>
                        @foreach ($this->getCategories() as $category)
                            <option value="{{ $category->id }}">{{ $category->name }}</option>
                        @endforeach
                    </select>
                    @if ($this->getCategories()->isNotEmpty())
                        <div class="mt-2 flex flex-wrap gap-1.5">
                            @foreach ($this->getCategories()->take(8) as $category)
                                <button
                                    type="button"
                                    wire:click="quickPickCategory({{ $category->id }})"
                                    @class([
                                        'rounded-full px-3 py-1 text-xs font-medium transition',
                                        'bg-primary-600 text-white' => $categoryId === $category->id,
                                        'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-white/5 dark:text-gray-300' => $categoryId !== $category->id,
                                    ])
                                >
                                    {{ $category->name }}
                                </button>
                            @endforeach
                        </div>
                    @else
                        <p class="mt-1 text-xs text-gray-500">
                            هنوز دسته‌ای ثبت نشده؛
                            <a href="{{ \App\Filament\Resources\ContractCategories\ContractCategoryResource::getUrl() }}" class="underline">از اینجا اضافه کنید</a>.
                        </p>
                    @endif
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-950 dark:text-white">کارفرما (شرکت ما)</label>
                    <select wire:model="companyId" class="fi-input block w-full rounded-lg border-gray-300 text-sm dark:border-white/10 dark:bg-white/5 dark:text-white">
                        <option value="">— انتخاب کنید —</option>
                        @foreach ($this->getCompanies() as $company)
                            <option value="{{ $company->id }}">{{ $company->name }}</option>
                        @endforeach
                    </select>
                    @if ($this->getCompanies()->isEmpty())
                        <p class="mt-1 text-xs text-gray-500">
                            هنوز شرکتی ثبت نشده؛
                            <a href="{{ \App\Filament\Resources\Companies\CompanyResource::getUrl() }}" class="underline">از اینجا اضافه کنید</a>.
                        </p>
                    @endif
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-950 dark:text-white">طرف مقابل</label>
                    <input
                        type="text"
                        wire:model="counterpartyName"
                        placeholder="مثلاً «آقای برق» یا «شرکت پشتیبان وب»"
                        class="fi-input block w-full rounded-lg border-gray-300 text-sm dark:border-white/10 dark:bg-white/5 dark:text-white"
                    />
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-950 dark:text-white">عنوان قرارداد (اختیاری)</label>
                    <input
                        type="text"
                        wire:model="contractTitle"
                        placeholder="اگر خالی بماند، خودکار ساخته می‌شود"
                        class="fi-input block w-full rounded-lg border-gray-300 text-sm dark:border-white/10 dark:bg-white/5 dark:text-white"
                    />
                </div>
            </div>

            <div class="mt-4">
                <label class="mb-1 block text-sm font-medium text-gray-950 dark:text-white">بگویید چه قراردادی می‌خواهید</label>
                <textarea
                    wire:model="brief"
                    rows="3"
                    placeholder="مثلاً: قرارداد پیمانکاری برق برای تعمیر و تقویت روشنایی محوطهٔ ویلای ۷، مدت اجرا ۳ روز کاری، مبلغ ۸٬۵۰۰٬۰۰۰ تومان"
                    class="fi-input block w-full rounded-lg border-gray-300 text-sm dark:border-white/10 dark:bg-white/5 dark:text-white"
                ></textarea>
            </div>

            <div class="mt-4 flex flex-wrap items-center gap-2">
                <x-filament::button wire:click="generateDraft" :disabled="$isGenerating" icon="heroicon-o-sparkles">
                    تولید پیش‌نویس AI
                </x-filament::button>
                <x-filament::button wire:click="newDraft" color="gray" outlined>
                    پاک کردن
                </x-filament::button>
                <span wire:loading wire:target="generateDraft,insertSuggestedClauses" class="text-xs text-gray-500">
                    در حال پردازش…
                </span>
            </div>
        </div>

        @if ($body !== '')
            {{-- نتیجهٔ ممیزی --}}
            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-900">
                <div class="mb-2 flex items-center justify-between">
                    <h3 class="text-sm font-semibold text-gray-950 dark:text-white">نتیجهٔ ممیزی هوشمند</h3>
                    @if ($auditWarnings !== [])
                        <span class="rounded-full bg-warning-100 px-2.5 py-0.5 text-xs font-semibold text-warning-800 dark:bg-warning-500/10 dark:text-warning-300">
                            {{ count($auditWarnings) }} هشدار
                        </span>
                    @else
                        <span class="rounded-full bg-success-100 px-2.5 py-0.5 text-xs font-semibold text-success-800 dark:bg-success-500/10 dark:text-success-300">
                            بدون هشدار
                        </span>
                    @endif
                </div>

                @if ($auditWarnings !== [])
                    <ul class="space-y-1.5 text-sm">
                        @foreach ($auditWarnings as $warning)
                            <li class="flex items-start gap-2 text-gray-700 dark:text-gray-300">
                                <x-heroicon-o-exclamation-circle class="mt-0.5 h-4 w-4 shrink-0 text-warning-500" />
                                <span><strong>{{ $warning['label'] }}:</strong> {{ $warning['message'] }}</span>
                            </li>
                        @endforeach
                    </ul>

                    <div class="mt-3">
                        <x-filament::button wire:click="insertSuggestedClauses" :disabled="$isGenerating" size="sm" color="warning">
                            درج بندهای پیشنهادی
                        </x-filament::button>
                    </div>
                @else
                    <p class="text-sm text-gray-600 dark:text-gray-400">همهٔ بندهای استاندارد در پیش‌نویس یافت شد. پیش از چاپ نهایی، حتماً با یک متخصص حقوقی مرور شود.</p>
                @endif
            </div>

            {{-- پیش‌نویس قرارداد --}}
            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-900">
                <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                    <h3 class="text-sm font-semibold text-gray-950 dark:text-white">پیش‌نویس قرارداد</h3>
                    <div class="flex flex-wrap gap-2">
                        <x-filament::button wire:click="save" size="sm" icon="heroicon-o-archive-box-arrow-down">
                            ذخیره در آرشیو
                        </x-filament::button>
                        @if ($printUrl = $this->printUrl())
                            <x-filament::button tag="a" href="{{ $printUrl }}" target="_blank" size="sm" color="gray" outlined icon="heroicon-o-printer">
                                چاپ
                            </x-filament::button>
                        @endif
                    </div>
                </div>

                <div class="contract-markdown rounded-lg border border-gray-100 bg-gray-50 p-4 text-sm text-gray-900 dark:border-white/5 dark:bg-white/5 dark:text-gray-100">
                    {!! \App\Support\SafeMarkdown::toHtml($body) !!}
                </div>

                <details class="mt-3">
                    <summary class="cursor-pointer text-xs text-gray-500 hover:text-gray-700 dark:hover:text-gray-300">ویرایش مستقیم متن</summary>
                    <textarea wire:model.blur="body" rows="14" class="fi-input mt-2 block w-full rounded-lg border-gray-300 font-mono text-xs dark:border-white/10 dark:bg-white/5 dark:text-white"></textarea>
                </details>
            </div>
        @endif
    </div>
</x-filament-panels::page>
