<x-filament-panels::page>
    @assets
        <script src="{{ asset('vendor/cytoscape/cytoscape.min.js') }}"></script>
        <script>
            window.aiTeamMap = function (initial) {
                // Kept outside the reactive object so Alpine never proxies the graph.
                let cy = null;

                return {
                    data: initial,
                    selected: null,
                    timer: null,
                    frame: null,
                    shapes: { circle: 'ellipse', 'rounded-square': 'round-rectangle', hexagon: 'hexagon', triangle: 'triangle', teardrop: 'round-tag', cloud: 'ellipse', flower: 'star', diamond: 'diamond', pentagon: 'pentagon', manager: 'octagon' },
                    init() {
                        this.$nextTick(() => this.draw());
                        this.timer = setInterval(() => this.refresh(), 10000);
                    },
                    destroy() {
                        clearInterval(this.timer);
                        cancelAnimationFrame(this.frame);
                        if (cy) { cy.destroy(); }
                    },
                    async refresh() {
                        if (document.hidden) { return; }
                        this.data = await this.$wire.mapData();
                        this.sync();
                    },
                    elements() {
                        const nodes = this.data.nodes.map((n) => ({ group: 'nodes', data: { id: n.id, label: n.label, color: n.color, shape: this.shapes[n.shape] || 'ellipse', live: n.live ? 1 : 0, size: n.id === 'manager' ? 74 : 54 } }));
                        const edges = this.data.edges.map((e) => ({ group: 'edges', data: { id: e.id, source: e.source, target: e.target, kind: e.kind, live: e.live ? 1 : 0, width: Math.min(2 + e.count * 0.8, 9) } }));
                        return nodes.concat(edges);
                    },
                    layout() {
                        // Everyone on one ring (the manager at the top) so a line between two
                        // assistants never runs through a third one.
                        const ordered = cy.nodes().sort((a, b) => (a.id() === 'manager' ? -1 : b.id() === 'manager' ? 1 : 0));
                        const radius = Math.max(150, ordered.length * 34);
                        ordered.forEach((n, i) => {
                            const angle = (2 * Math.PI * i) / Math.max(ordered.length, 1) - Math.PI / 2;
                            n.position({ x: radius * Math.cos(angle), y: radius * Math.sin(angle) });
                        });
                        cy.fit(undefined, 60);
                    },
                    draw() {
                        cy = cytoscape({
                            container: this.$refs.canvas,
                            elements: this.elements(),
                            minZoom: 0.4,
                            maxZoom: 2.5,
                            wheelSensitivity: 0.25,
                            style: [
                                { selector: 'node', style: { shape: 'data(shape)', width: 'data(size)', height: 'data(size)', 'background-color': '#0f172a', 'border-width': 3, 'border-color': 'data(color)', label: 'data(label)', color: '#e2e8f0', 'font-family': 'Vazirmatn, sans-serif', 'font-size': 12, 'text-valign': 'bottom', 'text-margin-y': 16, 'text-wrap': 'wrap', 'text-max-width': 110, 'overlay-opacity': 0, 'underlay-color': 'data(color)', 'underlay-opacity': 0.12, 'underlay-padding': 8, 'underlay-shape': 'ellipse' } },
                                { selector: 'node[live = 1]', style: { 'underlay-opacity': 0.4, 'underlay-padding': 14 } },
                                { selector: 'node:selected', style: { 'border-width': 5, 'underlay-opacity': 0.5 } },
                                { selector: 'edge', style: { width: 'data(width)', 'curve-style': 'bezier', 'line-color': '#475569', 'target-arrow-shape': 'triangle', 'target-arrow-color': '#475569', 'arrow-scale': 1.1, opacity: 0.75 } },
                                { selector: 'edge[kind = "handoff"]', style: { 'line-color': '#f59e0b', 'target-arrow-color': '#f59e0b', 'line-style': 'solid' } },
                                { selector: 'edge[kind = "consult"]', style: { 'line-color': '#38bdf8', 'target-arrow-color': '#38bdf8' } },
                                { selector: 'edge[live = 1]', style: { 'line-style': 'dashed', 'line-dash-pattern': [10, 6], opacity: 1 } },
                            ],
                            layout: { name: 'preset' },
                        });
                        this.layout();
                        cy.on('tap', 'node', (event) => { this.selected = this.data.nodes.find((n) => n.id === event.target.id()) || null; });
                        cy.on('tap', (event) => { if (event.target === cy) { this.selected = null; } });
                        this.animate();
                    },
                    sync() {
                        if (!cy) { return; }
                        const known = new Set(cy.elements().map((el) => el.id()));
                        const fresh = this.elements();
                        const ids = new Set(fresh.map((el) => el.data.id));
                        const added = fresh.some((el) => !known.has(el.data.id));
                        cy.elements().filter((el) => !ids.has(el.id())).remove();
                        fresh.forEach((el) => {
                            const existing = cy.getElementById(el.data.id);
                            if (existing.length) { existing.data(el.data); } else { cy.add(el); }
                        });
                        if (added) { this.layout(); }
                        if (this.selected) { this.selected = this.data.nodes.find((n) => n.id === this.selected.id) || null; }
                    },
                    animate() {
                        let offset = 0;
                        let pulse = 0;
                        const step = () => {
                            offset -= 0.6;
                            pulse += 0.05;
                            if (cy) {
                                cy.batch(() => {
                                    cy.edges('[live = 1]').style('line-dash-offset', offset);
                                    cy.nodes('[live = 1]').style('underlay-opacity', 0.28 + 0.16 * Math.sin(pulse));
                                });
                            }
                            this.frame = requestAnimationFrame(step);
                        };
                        if (!window.matchMedia('(prefers-reduced-motion: reduce)').matches) { step(); }
                    },
                    zoom(factor) {
                        if (!cy) { return; }
                        cy.zoom({ level: cy.zoom() * factor, renderedPosition: { x: cy.width() / 2, y: cy.height() / 2 } });
                    },
                    fit() { if (cy) { cy.fit(undefined, 60); } },
                    num(value) { return new Intl.NumberFormat('fa-IR').format(value); },
                };
            };
        </script>
    @endassets

    <div x-data="aiTeamMap(@js($this->mapData()))" class="space-y-4">
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
            <template x-for="tile in [
                { label: 'دستیار در تیم', value: data.totals.assistants },
                { label: 'مشورت در ۳۰ روز', value: data.totals.consultations },
                { label: 'تحویل گفتگو', value: data.totals.handoffs },
                { label: 'فعال در ۵ دقیقهٔ اخیر', value: data.totals.live },
            ]" :key="tile.label">
                <div class="rounded-xl border border-gray-200 bg-white px-4 py-3 dark:border-white/10 dark:bg-gray-900">
                    <div class="text-2xl font-bold text-gray-950 dark:text-white" x-text="num(tile.value)"></div>
                    <div class="text-xs text-gray-500 dark:text-gray-400" x-text="tile.label"></div>
                </div>
            </template>
        </div>

        <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_20rem]">
            <div class="relative overflow-hidden rounded-2xl border border-slate-700 bg-slate-950 shadow-lg">
                <div wire:ignore x-ref="canvas" class="h-[28rem] w-full sm:h-[34rem]" style="background-image: radial-gradient(circle at 50% 50%, rgb(30 41 59 / 0.9), rgb(2 6 23) 70%);"></div>

                <div class="absolute start-3 top-3 flex gap-1.5">
                    <button type="button" x-on:click="zoom(1.25)" class="h-8 w-8 rounded-lg border border-slate-600 bg-slate-900/80 text-slate-200 hover:bg-slate-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-sky-400" aria-label="بزرگ‌نمایی">+</button>
                    <button type="button" x-on:click="zoom(0.8)" class="h-8 w-8 rounded-lg border border-slate-600 bg-slate-900/80 text-slate-200 hover:bg-slate-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-sky-400" aria-label="کوچک‌نمایی">−</button>
                    <button type="button" x-on:click="fit()" class="h-8 rounded-lg border border-slate-600 bg-slate-900/80 px-3 text-xs text-slate-200 hover:bg-slate-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-sky-400">همه</button>
                </div>

                <div class="absolute bottom-3 start-3 flex flex-wrap gap-3 rounded-lg bg-slate-900/80 px-3 py-2 text-[11px] text-slate-300">
                    <span class="inline-flex items-center gap-1.5"><span class="h-0.5 w-5 bg-sky-400"></span>مشورت</span>
                    <span class="inline-flex items-center gap-1.5"><span class="h-0.5 w-5 bg-amber-500"></span>تحویل گفتگو</span>
                    <span class="inline-flex items-center gap-1.5"><span class="h-0.5 w-5 border-t-2 border-dashed border-slate-200"></span>در حال کار (۵ دقیقهٔ اخیر)</span>
                </div>

                <template x-if="data.edges.length === 0">
                    <div class="pointer-events-none absolute inset-x-0 top-3 mx-auto w-fit max-w-[80%] rounded-lg bg-slate-900/90 px-4 py-2 text-center text-xs text-slate-300">
                        هنوز همکاری‌ای ثبت نشده. یک دستیار را «قابل‌مشورت» کنید و از دستیار دیگری سؤالی در تخصص او بپرسید تا خط‌ها اینجا ظاهر شوند.
                    </div>
                </template>
            </div>

            <div class="space-y-4">
                <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-white/10 dark:bg-gray-900">
                    <template x-if="selected">
                        <div class="space-y-3">
                            <div class="flex items-center gap-2">
                                <span class="h-3 w-3 rounded-full" :style="'background:' + selected.color"></span>
                                <h3 class="text-sm font-semibold text-gray-950 dark:text-white" x-text="selected.label"></h3>
                                <span x-show="selected.live" class="rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-300">فعال</span>
                            </div>
                            <dl class="grid grid-cols-3 gap-2 text-center">
                                <div class="rounded-lg bg-gray-50 px-2 py-2 dark:bg-white/5"><dd class="text-lg font-bold text-gray-950 dark:text-white" x-text="num(selected.messages)"></dd><dt class="text-[10px] text-gray-500 dark:text-gray-400">پاسخ</dt></div>
                                <div class="rounded-lg bg-gray-50 px-2 py-2 dark:bg-white/5"><dd class="text-lg font-bold text-gray-950 dark:text-white" x-text="num(selected.consulted)"></dd><dt class="text-[10px] text-gray-500 dark:text-gray-400">مشورت داده</dt></div>
                                <div class="rounded-lg bg-gray-50 px-2 py-2 dark:bg-white/5"><dd class="text-lg font-bold text-gray-950 dark:text-white" x-text="num(selected.asked)"></dd><dt class="text-[10px] text-gray-500 dark:text-gray-400">مشورت گرفته</dt></div>
                            </dl>
                            <p x-show="selected.id !== 'manager'" class="text-xs text-gray-500 dark:text-gray-400" x-text="selected.consultable ? 'قابل‌مشورت است.' : 'قابل‌مشورت نیست؛ دیگران از او نمی‌پرسند.'"></p>
                        </div>
                    </template>
                    <p x-show="!selected" class="text-xs text-gray-500 dark:text-gray-400">روی یک دستیار در نقشه بزنید تا آمارش را ببینید. با اسکرول زوم کنید و با کشیدن، نقشه را جابه‌جا کنید.</p>
                </div>

                <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-white/10 dark:bg-gray-900">
                    <h3 class="mb-3 text-sm font-semibold text-gray-950 dark:text-white">آخرین همکاری‌ها</h3>
                    <ul class="space-y-2.5">
                        <template x-for="(item, index) in data.feed" :key="index">
                            <li class="flex gap-2 text-xs">
                                <span class="mt-1 h-2 w-2 shrink-0 rounded-full" :class="item.kind === 'handoff' ? 'bg-amber-500' : 'bg-sky-400'"></span>
                                <span class="min-w-0 flex-1 text-gray-700 dark:text-gray-200"><span x-text="item.text"></span> <span class="text-gray-400" x-text="'· ' + item.at"></span> <span x-show="item.live" class="rounded-full bg-emerald-100 px-1.5 text-[10px] text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-300">همین الان</span></span>
                            </li>
                        </template>
                    </ul>
                    <p x-show="data.feed.length === 0" class="text-xs text-gray-500 dark:text-gray-400">هنوز چیزی ثبت نشده.</p>
                </div>
            </div>
        </div>
    </div>
</x-filament-panels::page>
