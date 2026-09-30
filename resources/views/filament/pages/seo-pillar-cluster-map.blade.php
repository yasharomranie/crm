<x-filament-panels::page>
    <div dir="rtl" class="space-y-6" x-data="{ tab: 'tree' }">
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

        @if (! $projectId)
            <p class="text-sm text-gray-500 dark:text-gray-400">برای دیدن نقشه‌ی موضوعی، ابتدا یک پروژه انتخاب کن.</p>
        @elseif ($this->map()?->isEmpty())
            <p class="text-sm text-gray-500 dark:text-gray-400">این پروژه هنوز هیچ خوشه‌ی موضوعی یا کلمه‌ی کلیدی‌ای ندارد.</p>
        @else
            <div class="flex gap-1 border-b border-gray-200 dark:border-white/10">
                <button
                    type="button"
                    @click="tab = 'tree'"
                    :class="tab === 'tree' ? 'border-primary-600 text-primary-600 dark:text-primary-400' : 'border-transparent text-gray-500 dark:text-gray-400'"
                    class="border-b-2 px-3 py-2 text-sm font-medium"
                >
                    نمای درختی
                </button>
                <button
                    type="button"
                    @click="tab = 'list'"
                    :class="tab === 'list' ? 'border-primary-600 text-primary-600 dark:text-primary-400' : 'border-transparent text-gray-500 dark:text-gray-400'"
                    class="border-b-2 px-3 py-2 text-sm font-medium"
                >
                    لیست خوشه‌ها
                </button>
            </div>

            {{-- نمای درختی: یک نمودار درختی شعاعی (radial tree) با D3.js که
                 تمام خوشه‌های موضوعی و کلمات زیرشان را یک‌جا نشان می‌دهد -
                 نقطه‌ی قرمزتر یعنی آن کلمه در هشدار هم‌خواری هم هست. --}}
            <div x-show="tab === 'tree'" x-cloak>
                @include('filament.components.d3-assets')

                @php $treeContainerId = 'seo-pillar-tree-'.\Illuminate\Support\Str::random(8); @endphp

                <p class="mb-2 text-xs text-gray-500 dark:text-gray-400">با اسکرول ماوس یا پینچ زوم کن، با گرفتن و کشیدن (درگ) جابه‌جا شو - یا از دکمه‌های کنار نمودار استفاده کن.</p>

                <div wire:key="pillar-tree-{{ $projectId }}" style="position: relative; border: 1px solid #e5e7eb; border-radius: .5rem; overflow: hidden;" class="dark:border-white/10">
                    <div class="absolute left-2 top-2 z-10 flex flex-col gap-1" style="position: absolute;">
                        <button type="button" id="{{ $treeContainerId }}-zoom-in" title="زوم این" class="flex h-8 w-8 items-center justify-center rounded-md border border-gray-200 bg-white text-lg font-bold text-gray-700 shadow-sm hover:bg-gray-50 dark:border-white/10 dark:bg-gray-800 dark:text-white dark:hover:bg-gray-700">+</button>
                        <button type="button" id="{{ $treeContainerId }}-zoom-out" title="زوم اوت" class="flex h-8 w-8 items-center justify-center rounded-md border border-gray-200 bg-white text-lg font-bold text-gray-700 shadow-sm hover:bg-gray-50 dark:border-white/10 dark:bg-gray-800 dark:text-white dark:hover:bg-gray-700">−</button>
                        <button type="button" id="{{ $treeContainerId }}-zoom-reset" title="بازنشانی نما" class="flex h-8 w-8 items-center justify-center rounded-md border border-gray-200 bg-white text-xs font-bold text-gray-700 shadow-sm hover:bg-gray-50 dark:border-white/10 dark:bg-gray-800 dark:text-white dark:hover:bg-gray-700">⟲</button>
                    </div>

                    <div id="{{ $treeContainerId }}" style="width: 100%; height: 75vh;"></div>

                    <script>
                        (function () {
                            var data = @json($this->treeData());
                            var container = document.getElementById('{{ $treeContainerId }}');

                            if (! data || ! container || ! window.d3) {
                                return;
                            }

                            var root = d3.hierarchy(data);
                            var leafCount = root.leaves().length;

                            // The layout radius still grows with leaf count so
                            // labels keep enough arc length between them - but
                            // it no longer dictates the SVG's own pixel size.
                            // The viewport below stays a fixed size and the
                            // user zooms/pans into the (possibly much larger)
                            // drawing instead of the whole thing being
                            // shrunk-to-fit or requiring a scrollbar.
                            var radius = Math.max(420, leafCount * 4.5);

                            var tree = d3.tree()
                                .size([2 * Math.PI, radius - 110])
                                .separation(function (a, b) {
                                    return (a.parent === b.parent ? 1 : 2) / a.depth;
                                });
                            tree(root);

                            var viewport = Math.max(320, Math.min(container.clientWidth || 700, 700));

                            var svg = d3.select(container).append('svg')
                                .attr('width', '100%')
                                .attr('height', '100%')
                                .attr('viewBox', [-viewport / 2, -viewport / 2, viewport, viewport])
                                .attr('style', 'display: block; font: 11px "Vazirmatn", sans-serif; cursor: grab;');

                            var g = svg.append('g');

                            g.append('g')
                                .attr('fill', 'none')
                                .attr('stroke', '#d1d5db')
                                .attr('stroke-width', 1.2)
                                .selectAll('path')
                                .data(root.links())
                                .join('path')
                                .attr('d', d3.linkRadial().angle(function (d) { return d.x; }).radius(function (d) { return d.y; }));

                            var node = g.append('g')
                                .selectAll('g')
                                .data(root.descendants())
                                .join('g')
                                .attr('transform', function (d) {
                                    return 'rotate(' + (d.x * 180 / Math.PI - 90) + ') translate(' + d.y + ',0)';
                                });

                            node.append('circle')
                                .attr('r', function (d) { return d.depth === 0 ? 6 : (d.children ? 5 : 4); })
                                .attr('fill', function (d) {
                                    if (d.depth === 0) {
                                        return '#57534e';
                                    }

                                    if (d.children) {
                                        return '#f97316';
                                    }

                                    return d.data.hasWarning ? '#dc2626' : '#fecaca';
                                });

                            node.append('text')
                                .attr('dy', '0.31em')
                                .attr('x', function (d) { return d.x < Math.PI ? 6 : -6; })
                                .attr('text-anchor', function (d) { return d.x < Math.PI ? 'start' : 'end'; })
                                .attr('transform', function (d) { return d.x >= Math.PI ? 'rotate(180)' : null; })
                                .text(function (d) { return d.data.name; })
                                .attr('fill', '#111827')
                                .clone(true).lower()
                                .attr('stroke', 'white')
                                .attr('stroke-width', 3);

                            // Whole tree (data-space diameter ~2*radius) scaled
                            // down to just fit the fixed viewport on first
                            // paint, then the user zooms in from there.
                            var initialScale = Math.min(1, (viewport * 0.92) / (radius * 2));

                            var zoom = d3.zoom()
                                .scaleExtent([initialScale * 0.4, 6])
                                .on('zoom', function (event) {
                                    g.attr('transform', event.transform);
                                });

                            svg.call(zoom);
                            svg.call(zoom.transform, d3.zoomIdentity.scale(initialScale));

                            var zoomInBtn = document.getElementById('{{ $treeContainerId }}-zoom-in');
                            var zoomOutBtn = document.getElementById('{{ $treeContainerId }}-zoom-out');
                            var zoomResetBtn = document.getElementById('{{ $treeContainerId }}-zoom-reset');

                            if (zoomInBtn) {
                                zoomInBtn.addEventListener('click', function () {
                                    svg.transition().duration(200).call(zoom.scaleBy, 1.4);
                                });
                            }

                            if (zoomOutBtn) {
                                zoomOutBtn.addEventListener('click', function () {
                                    svg.transition().duration(200).call(zoom.scaleBy, 1 / 1.4);
                                });
                            }

                            if (zoomResetBtn) {
                                zoomResetBtn.addEventListener('click', function () {
                                    svg.transition().duration(200).call(zoom.transform, d3.zoomIdentity.scale(initialScale));
                                });
                            }
                        })();
                    </script>
                </div>

                <div class="mt-4 flex flex-wrap items-center gap-4 text-xs text-gray-500 dark:text-gray-400">
                    <span class="flex items-center gap-1.5"><span class="inline-block h-2.5 w-2.5 rounded-full" style="background:#f97316"></span> خوشه‌ی موضوعی</span>
                    <span class="flex items-center gap-1.5"><span class="inline-block h-2.5 w-2.5 rounded-full" style="background:#dc2626"></span> کلمه با هشدار هم‌خواری</span>
                    <span class="flex items-center gap-1.5"><span class="inline-block h-2.5 w-2.5 rounded-full" style="background:#fecaca"></span> کلمه بدون هشدار</span>
                </div>
            </div>

            {{-- لیست خوشه‌ها: همان نمای آکاردئونی قبلی، بدون تغییر. --}}
            <div x-show="tab === 'list'" x-cloak>
                <div class="space-y-3">
                    @foreach ($this->map() as $group)
                        <details class="group rounded-lg border border-gray-200 dark:border-white/10">
                            <summary class="flex cursor-pointer list-none items-center justify-between gap-3 p-4 [&::-webkit-details-marker]:hidden">
                                <div class="flex items-center gap-2">
                                    <svg class="h-4 w-4 shrink-0 text-gray-400 transition-transform group-open:-rotate-90" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                                    </svg>
                                    <h3 class="text-base font-bold text-gray-950 dark:text-white">{{ $group['name'] }}</h3>
                                    @if (! empty($group['warnings']))
                                        <span class="inline-flex items-center gap-1 rounded-full bg-warning-50 px-2 py-0.5 text-xs text-warning-700 dark:bg-warning-500/10 dark:text-warning-400">
                                            ⚠ {{ count($group['warnings']) }} هشدار
                                        </span>
                                    @endif
                                    @if ($group['suggestions']->isNotEmpty())
                                        <span class="inline-flex items-center gap-1 rounded-full bg-info-50 px-2 py-0.5 text-xs text-info-700 dark:bg-info-500/10 dark:text-info-400">
                                            ✨ {{ $group['suggestions']->count() }} پیشنهاد کلمه‌ی جدید
                                        </span>
                                    @endif
                                </div>
                                @php
                                    $livePagesCount = $group['profiles']->pluck('landing_page')->filter()->unique()->count();
                                @endphp
                                <span class="text-xs text-gray-500 dark:text-gray-400">
                                    {{ $group['profiles']->count() + $group['suggestions']->count() }} کلمه ·
                                    {{ $livePagesCount }} صفحه‌ی زنده (سرچ کنسول) ·
                                    {{ $group['contentItems']->count() }} محتوای برنامه‌ریزی‌شده
                                </span>
                            </summary>

                            <div class="border-t border-gray-200 p-4 dark:border-white/10">
                                @if (! empty($group['warnings']))
                                    <div class="space-y-1.5">
                                        @foreach ($group['warnings'] as $warning)
                                            <div class="flex items-start gap-2 rounded-md bg-warning-50 px-3 py-2 text-xs text-warning-700 dark:bg-warning-500/10 dark:text-warning-400">
                                                <span>⚠</span>
                                                <span>احتمال هم‌خواری بین «{{ $warning['a'] }}» و «{{ $warning['b'] }}» (شباهت {{ $warning['similarity'] * 100 }}٪) - فقط راهنماست، تصمیم نهایی با شماست.</span>
                                            </div>
                                        @endforeach
                                    </div>
                                @endif

                                @if ($group['profiles']->isNotEmpty())
                                    <div class="mt-3">
                                        <p class="text-xs font-medium text-gray-500 dark:text-gray-400">کلمات واقعی (سرچ کنسول)</p>
                                        <div class="mt-1.5 flex flex-wrap items-center gap-1.5">
                                            @foreach ($group['profiles'] as $profile)
                                                <span class="inline-flex items-center gap-1">
                                                    <x-filament::badge :color="$profile->cannibalization_risk?->getColor() ?? 'gray'">
                                                        {{ $profile->query }}
                                                        @if ($profile->current_position !== null)
                                                            <span class="opacity-75">· رتبه {{ number_format((float) $profile->current_position, 1) }}</span>
                                                        @endif
                                                        @if ($profile->cannibalization_risk && $profile->cannibalization_risk->value !== 'none')
                                                            ({{ $profile->cannibalization_risk->getLabel() }})
                                                        @endif
                                                        @if ($profile->landing_page)
                                                            <a href="{{ $profile->landing_page }}" target="_blank" rel="noopener" title="صفحه‌ی زنده‌ای که الان برای این کلمه رتبه دارد" class="mr-1 underline decoration-dotted">
                                                                🔗 صفحه
                                                            </a>
                                                        @endif
                                                    </x-filament::badge>

                                                    @if ($profile->opportunity_score !== null && (float) $profile->opportunity_score > 0)
                                                        <span
                                                            class="inline-flex cursor-help items-center gap-1 rounded-full bg-warning-50 px-2 py-0.5 text-xs text-warning-700 dark:bg-warning-500/10 dark:text-warning-400"
                                                            title="این کلمه در رتبه {{ number_format((float) $profile->current_position, 1) }} است اما نرخ کلیک (CTR) آن پایین‌تر از حد انتظار برای همین رتبه است - یعنی صفحه دیده می‌شود ولی کلیک لازم را نمی‌گیرد. تخمین رشد در صورت بهبود عنوان/متادیتا/توضیح جستجو: حدود {{ number_format((float) $profile->opportunity_score, 0) }} کلیک بیشتر در ماه."
                                                        >
                                                            ⚠ فرصت رشد و کلیک بیشتر
                                                        </span>
                                                    @endif
                                                </span>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif

                                @if ($group['suggestions']->isNotEmpty())
                                    <div class="mt-3">
                                        <p class="text-xs font-medium text-gray-500 dark:text-gray-400">پیشنهادهای هوش مصنوعی</p>
                                        <div class="mt-1.5 flex flex-wrap items-center gap-1.5">
                                            @foreach ($group['suggestions'] as $suggestion)
                                                <span class="inline-flex items-center gap-1">
                                                    <x-filament::badge color="gray">
                                                        {{ $suggestion->keyword }} · {{ $suggestion->status->getLabel() }}
                                                    </x-filament::badge>
                                                    {{ ($this->addSuggestionToContentCalendarAction)(arguments: ['suggestionId' => $suggestion->id]) }}
                                                </span>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif

                                @if ($group['contentItems']->isNotEmpty())
                                    <div class="mt-3">
                                        <p class="text-xs font-medium text-gray-500 dark:text-gray-400">محتوا</p>
                                        <div class="mt-1.5 flex flex-wrap gap-1.5">
                                            @foreach ($group['contentItems'] as $item)
                                                <x-filament::badge :color="$item->status->getColor()">
                                                    {{ $item->title }} · {{ $item->status->getLabel() }}
                                                </x-filament::badge>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif
                            </div>
                        </details>
                    @endforeach
                </div>
            </div>
        @endif
    </div>
</x-filament-panels::page>
