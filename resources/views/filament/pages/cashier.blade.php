<x-filament-panels::page>
    @php
        $products = $this->getProducts();
        $variants = $this->getVariants();
        $lastOrder = $this->getLastOrder();
        $quickVariants = $this->getQuickVariants();
        $shift = $this->currentShift();
        $shiftSummary = $this->shiftSummary();
    @endphp

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-gray-900">
                <h3 class="mb-4 text-sm font-semibold text-gray-950 dark:text-white">افزودن بلیت</h3>

                @if ($quickVariants->isNotEmpty())
                    <div class="mb-4 flex flex-wrap gap-2">
                        @foreach ($quickVariants as $quick)
                            <button
                                type="button"
                                wire:click="quickAdd({{ $quick->id }})"
                                class="rounded-lg border border-primary-200 bg-primary-50 px-3 py-2 text-sm font-medium text-primary-700 hover:bg-primary-100 dark:border-primary-500/30 dark:bg-primary-500/10 dark:text-primary-300"
                            >
                                {{ $quick->product->name }} - {{ $quick->name }}
                                <span class="block text-xs font-normal text-gray-500 dark:text-gray-400">{{ number_format($quick->currentPrice()->amount) }} تومان</span>
                            </button>
                        @endforeach
                    </div>
                @endif

                <input
                    type="text"
                    wire:model="barcode"
                    wire:keydown.enter="addByCode"
                    placeholder="اسکن بارکد / کد نوع بلیت و Enter"
                    class="fi-input mb-3 w-full rounded-lg text-sm"
                />

                <div class="grid grid-cols-1 gap-3 sm:grid-cols-4">
                    <select wire:model.live="productId" class="fi-input rounded-lg text-sm sm:col-span-2">
                        <option value="">محصول را انتخاب کنید</option>
                        @foreach ($products as $product)
                            <option value="{{ $product->id }}">{{ $product->name }}</option>
                        @endforeach
                    </select>

                    <select wire:model="ticketVariantId" class="fi-input rounded-lg text-sm sm:col-span-1">
                        <option value="">نوع بلیت</option>
                        @foreach ($variants as $variant)
                            <option value="{{ $variant->id }}">
                                {{ $variant->name }} - {{ number_format($variant->currentPrice()?->amount ?? 0) }} تومان{{ $variant->remainingToday() !== null ? ' (باقی‌مانده امروز: '.$variant->remainingToday().')' : '' }}
                            </option>
                        @endforeach
                    </select>

                    <input type="number" min="1" wire:model="quantity" placeholder="تعداد" class="fi-input rounded-lg text-sm" />
                </div>

                <button
                    type="button"
                    wire:click="addToCart"
                    class="fi-btn mt-3 rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-500"
                >
                    افزودن به سبد
                </button>
            </div>

            <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-gray-900">
                <h3 class="mb-4 text-sm font-semibold text-gray-950 dark:text-white">سبد فروش</h3>

                @forelse ($cart as $index => $line)
                    <div class="flex items-center justify-between border-b border-gray-100 py-2 text-sm last:border-0 dark:border-white/5">
                        <span>{{ $line['label'] }} × {{ \Illuminate\Support\Number::toPersian($line['quantity']) }}</span>
                        <span class="flex items-center gap-3">
                            <span class="font-medium">{{ number_format($line['unit_price'] * $line['quantity']) }} تومان</span>
                            <button type="button" wire:click="removeFromCart({{ $index }})" class="text-danger-600 hover:underline">حذف</button>
                        </span>
                    </div>
                @empty
                    <p class="py-6 text-center text-sm text-gray-500 dark:text-gray-400">سبد فروش خالی است.</p>
                @endforelse

                @if (! empty($cart))
                    <div class="mt-3 flex items-center justify-between border-t border-gray-200 pt-3 text-sm font-semibold dark:border-white/10">
                        <span>جمع کل</span>
                        <span>{{ number_format($this->cartTotal()) }} تومان</span>
                    </div>
                @endif
            </div>
        </div>

        <div class="space-y-6">
            <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-gray-900">
                <h3 class="mb-3 text-sm font-semibold text-gray-950 dark:text-white">شیفت صندوق</h3>

                @if ($shift)
                    <div class="space-y-1 text-sm">
                        <div class="flex justify-between"><span>شروع</span><span>{{ $shift->opened_at->toJalali('Y/m/d H:i') }}</span></div>
                        <div class="flex justify-between"><span>تعداد فروش</span><span>{{ \Illuminate\Support\Number::toPersian($shiftSummary['orders']) }}</span></div>
                        <div class="flex justify-between"><span>جمع فروش</span><span>{{ number_format($shiftSummary['total_sales']) }} تومان</span></div>
                        @foreach ($shiftSummary['by_method'] as $method => $amount)
                            <div class="flex justify-between text-xs text-gray-500 dark:text-gray-400"><span>{{ \App\Enums\PaymentMethod::tryFrom($method)?->getLabel() ?? $method }}</span><span>{{ number_format($amount) }}</span></div>
                        @endforeach
                        <div class="flex justify-between font-semibold"><span>نقد مورد انتظار در صندوق</span><span>{{ number_format($shiftSummary['expected_cash']) }} تومان</span></div>
                    </div>
                    <input type="number" min="0" wire:model="countedCash" placeholder="نقد شمرده‌شده در صندوق" class="fi-input mt-3 w-full rounded-lg text-sm" />
                    <input type="text" wire:model="shiftNotes" placeholder="توضیح (اختیاری)" class="fi-input mt-2 w-full rounded-lg text-sm" />
                    <button type="button" wire:click="closeShift" wire:confirm="شیفت بسته شود؟" class="fi-btn mt-3 w-full rounded-lg bg-warning-600 px-4 py-2 text-sm font-medium text-white hover:bg-warning-500">بستن شیفت</button>
                @else
                    <p class="mb-2 text-xs text-gray-500 dark:text-gray-400">شیفت باز نیست؛ فروش‌ها به هیچ شیفتی نسبت داده نمی‌شوند.</p>
                    <input type="number" min="0" wire:model="shiftFloat" placeholder="نقد اول شیفت در صندوق (تومان)" class="fi-input w-full rounded-lg text-sm" />
                    <button type="button" wire:click="openShift" class="fi-btn mt-3 w-full rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-500">شروع شیفت</button>
                @endif

                @php($closedShifts = $this->recentClosedShifts())
                @if ($closedShifts->isNotEmpty())
                    <div class="mt-4 space-y-1 border-t border-gray-100 pt-3 text-xs dark:border-white/5">
                        <p class="font-semibold">شیفت‌های اخیر</p>
                        @foreach ($closedShifts as $closed)
                            <div class="flex justify-between {{ $closed->difference !== 0 ? 'text-danger-600' : 'text-gray-500' }}">
                                <span>{{ $closed->user?->name }} · {{ $closed->closed_at->toJalali('m/d H:i') }}</span>
                                <span>{{ $closed->difference === 0 ? 'بدون اختلاف' : 'اختلاف '.number_format($closed->difference) }}</span>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-gray-900">
                <h3 class="mb-4 text-sm font-semibold text-gray-950 dark:text-white">مشتری و پرداخت</h3>

                <div class="space-y-3">
                    <input type="text" wire:model="customerName" placeholder="نام مشتری (اختیاری)" class="fi-input w-full rounded-lg text-sm" />
                    <input type="text" wire:model="customerPhone" placeholder="شماره تماس (اختیاری)" class="fi-input w-full rounded-lg text-sm" />

                    @if ($this->canIssueComplimentary())
                        <label class="flex items-center gap-2 text-sm">
                            <input type="checkbox" wire:model.live="isComplimentary" class="rounded border-gray-300" />
                            صدور به‌عنوان بلیت رایگان/اهدایی
                        </label>
                    @endif

                    @if ($isComplimentary)
                        <input type="text" wire:model="complimentaryReason" placeholder="دلیل صدور (الزامی)" class="fi-input w-full rounded-lg text-sm" />
                    @else
                        <input type="text" wire:model="discountCode" placeholder="کد تخفیف (اختیاری)" class="fi-input w-full rounded-lg text-sm" />

                        <select wire:model="paymentMethod" class="fi-input w-full rounded-lg text-sm">
                            <option value="cash">نقدی</option>
                            <option value="card">کارت‌خوان</option>
                            <option value="split">ترکیبی (بخشی نقد، بقیه کارت‌خوان)</option>
                            <option value="online">آنلاین</option>
                            <option value="wallet">کیف پول مشتری (با شماره موبایل)</option>
                            <option value="other">سایر</option>
                        </select>

                        @if ($paymentMethod === 'cash')
                            <input type="number" min="0" wire:model.live.debounce.300ms="cashReceived" placeholder="مبلغ دریافتی از مشتری (برای محاسبه‌ی باقی‌مانده)" class="fi-input w-full rounded-lg text-sm" />
                            @if ($this->changeDue() !== null)
                                <p class="rounded-lg bg-success-50 px-3 py-2 text-sm font-semibold text-success-700 dark:bg-success-500/10 dark:text-success-300">
                                    باقی‌مانده برای مشتری: {{ number_format($this->changeDue()) }} تومان
                                </p>
                            @endif
                        @elseif ($paymentMethod === 'split')
                            <input type="number" min="1" wire:model="splitCash" placeholder="مبلغ پرداخت‌شده نقدی (بقیه با کارت‌خوان)" class="fi-input w-full rounded-lg text-sm" />
                        @endif
                    @endif
                </div>

                <button
                    type="button"
                    wire:click="checkout"
                    class="fi-btn mt-4 w-full rounded-lg bg-success-600 px-4 py-2 text-sm font-medium text-white hover:bg-success-500"
                >
                    ثبت فروش و صدور بلیت
                </button>
            </div>

            @if ($lastOrder)
                <div class="rounded-xl border border-success-200 bg-success-50 p-5 dark:border-success-500/20 dark:bg-success-500/10">
                    <h3 class="mb-3 text-sm font-semibold text-success-800 dark:text-success-300">آخرین فروش: {{ $lastOrder->order_number }}</h3>
                    <div class="mb-3 flex flex-wrap gap-2">
                        <a href="{{ route('admission-orders.print', [$lastOrder, 'auto' => 1]) }}" target="_blank" class="rounded-lg bg-success-600 px-3 py-2 text-sm font-medium text-white hover:bg-success-500">چاپ همه‌ی بلیت‌ها</a>
                        <a href="{{ route('admission-orders.print', [$lastOrder, 'auto' => 1, 'paper' => 'thermal']) }}" target="_blank" class="rounded-lg border border-success-300 px-3 py-2 text-sm font-medium text-success-700 dark:text-success-300">چاپ حرارتی ۸۰mm</a>
                    </div>
                    <div class="space-y-2">
                        @foreach ($lastOrder->items as $item)
                            @foreach ($item->tickets as $ticket)
                                <div class="flex items-center justify-between rounded-lg bg-white px-3 py-2 text-sm dark:bg-gray-900">
                                    <span>{{ $ticket->ticket_number }}</span>
                                    <a
                                        href="{{ route('admission-tickets.print', $ticket) }}"
                                        target="_blank"
                                        class="text-primary-600 hover:underline dark:text-primary-400"
                                    >
                                        چاپ بلیت
                                    </a>
                                </div>
                            @endforeach
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    </div>
</x-filament-panels::page>
