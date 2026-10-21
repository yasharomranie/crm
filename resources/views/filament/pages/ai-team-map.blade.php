<x-filament-panels::page>
    @assets
        <script src="{{ asset('vendor/war-room/war-room.js') }}?v={{ filemtime(public_path('vendor/war-room/war-room.js')) }}"></script>
        <script>
            window.aiTeamMap = function (initial, initialMission) {
                // The 3D scene lives outside the reactive object so Alpine never proxies it.
                let scene = null;

                return {
                    data: initial,
                    selected: null,
                    clock: '',
                    day: '',
                    shown: { assistants: 0, consultations: 0, handoffs: 0, live: 0 },
                    timers: [],
                    fullscreen: false,
                    fullscreenOk: !!document.fullscreenEnabled,
                    missionPick: initialMission,
                    bubbles: (() => { try { return localStorage.getItem('aiTeamMapBubbles') !== 'off'; } catch (e) { return true; } })(),
                    style: (() => { try { return localStorage.getItem('aiTeamMapStyle') === 'robot' ? 'robot' : 'cartoon'; } catch (e) { return 'cartoon'; } })(),
                    init() {
                        this.tick();
                        this.timers.push(setInterval(() => this.tick(), 1000));
                        this.timers.push(setInterval(() => this.refresh(), 10000));
                        this.countTo(this.data.totals);
                        const boot = () => {
                            if (!window.WarRoom) { return setTimeout(boot, 80); }
                            scene = window.WarRoom.mount(this.$refs.scene, { onSelect: (id) => this.select(id, false), initialStyle: this.style, initialBubbles: this.bubbles });
                            scene.update(this.data);
                        };
                        this.$nextTick(boot);
                        document.addEventListener('fullscreenchange', () => { this.fullscreen = !!document.fullscreenElement; });
                    },
                    destroy() {
                        this.timers.forEach(clearInterval);
                        if (scene) { scene.destroy(); }
                    },
                    tick() {
                        const now = new Date();
                        this.clock = now.toLocaleTimeString('fa-IR', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
                        this.day = now.toLocaleDateString('fa-IR', { weekday: 'long', day: 'numeric', month: 'long' });
                    },
                    async refresh(force = false) {
                        if (document.hidden && !force) { return; }
                        this.data = await this.$wire.mapData();
                        if (scene) { scene.update(this.data); }
                        this.countTo(this.data.totals);
                        if (this.selected) { this.selected = this.data.nodes.find((n) => n.id === this.selected.id) || null; }
                    },
                    countTo(target) {
                        const from = { ...this.shown };
                        const start = performance.now();
                        const step = (now) => {
                            const t = Math.min((now - start) / 700, 1);
                            const ease = 1 - Math.pow(1 - t, 3);
                            Object.keys(target).forEach((key) => { this.shown[key] = Math.round(from[key] + (target[key] - from[key]) * ease); });
                            if (t < 1) { requestAnimationFrame(step); }
                        };
                        requestAnimationFrame(step);
                    },
                    select(id, fly = true) {
                        this.selected = id ? this.data.nodes.find((n) => n.id === id) || null : null;
                        if (id && fly && scene) { scene.focus(id); }
                    },
                    setStyle(next) {
                        this.style = next;
                        try { localStorage.setItem('aiTeamMapStyle', next); } catch (e) { /* storage unavailable */ }
                        if (scene) { scene.setStyle(next); }
                    },
                    async pickMission(value) {
                        this.missionPick = value ? Number(value) : null;
                        this.selected = null;
                        await this.$wire.selectMission(this.missionPick);
                        await this.refresh(true);
                    },
                    toggleBubbles() {
                        this.bubbles = !this.bubbles;
                        try { localStorage.setItem('aiTeamMapBubbles', this.bubbles ? 'on' : 'off'); } catch (e) { /* storage unavailable */ }
                        if (scene) { scene.setBubbles(this.bubbles); }
                    },
                    reset() { this.selected = null; if (scene) { scene.reset(); } },
                    toggleFullscreen() {
                        if (document.fullscreenElement) { document.exitFullscreen(); } else { this.$refs.stage.requestFullscreen(); }
                    },
                    maxMessages() { return Math.max(1, ...this.data.nodes.map((n) => n.messages)); },
                    num(value) { return new Intl.NumberFormat('fa-IR').format(value); },
                };
            };
        </script>
    @endassets

    <style>
        .wr-stage, .wr-below { --wr-bg: #0b1220; --wr-panel: rgb(7 16 32 / 0.72); --wr-line: rgb(56 189 248 / 0.28); --wr-cyan: #38bdf8; --wr-amber: #fbbf24; --wr-text: #e2e8f0; --wr-dim: #8193ad; position: relative; height: 44rem; overflow: hidden; border-radius: 1rem; border: 1px solid var(--wr-line); background: var(--wr-bg); color: var(--wr-text); isolation: isolate; }
        .wr-stage:fullscreen { height: 100vh; border-radius: 0; }
        .wr-scene { position: absolute; inset: 0; }
        .wr-panel { position: absolute; background: var(--wr-panel); border: 1px solid var(--wr-line); border-radius: 0.5rem; }
        .wr-panel::before, .wr-panel::after { content: ''; position: absolute; width: 10px; height: 10px; border: 2px solid var(--wr-cyan); pointer-events: none; }
        .wr-panel::before { top: -1px; inset-inline-start: -1px; border-inline-end: 0; border-bottom: 0; }
        .wr-panel::after { bottom: -1px; inset-inline-end: -1px; border-inline-start: 0; border-top: 0; }
        .wr-label { font-size: 0.65rem; letter-spacing: 0.08em; color: var(--wr-dim); }
        .wr-num { font-variant-numeric: tabular-nums; font-weight: 700; }
        .wr-btn { height: 2rem; padding-inline: 0.75rem; font-size: 0.75rem; border-radius: 0.375rem; border: 1px solid var(--wr-line); background: rgb(7 16 32 / 0.8); color: var(--wr-text); cursor: pointer; }
        .wr-btn:hover { background: rgb(14 32 60 / 0.9); }
        .wr-btn:focus-visible { outline: 2px solid var(--wr-cyan); outline-offset: 2px; }
        .wr-seg { display: inline-flex; border: 1px solid var(--wr-line); border-radius: 0.375rem; overflow: hidden; }
        .wr-seg button { height: 2rem; padding-inline: 0.7rem; font-size: 0.75rem; background: rgb(7 16 32 / 0.8); color: var(--wr-dim); border: 0; cursor: pointer; }
        .wr-seg button.on { background: rgb(56 189 248 / 0.22); color: var(--wr-text); }
        .wr-seg button:focus-visible { outline: 2px solid var(--wr-cyan); outline-offset: -2px; }
        .wr-led { width: 0.5rem; height: 0.5rem; border-radius: 9999px; background: #475569; flex: none; }
        .wr-led.on { background: #34d399; }
        .wr-row { display: flex; width: 100%; align-items: center; gap: 0.5rem; padding: 0.4rem 0.5rem; border-radius: 0.375rem; text-align: start; cursor: pointer; background: transparent; border: 0; color: inherit; }
        .wr-row:hover, .wr-row.sel { background: rgb(56 189 248 / 0.12); }
        .wr-row:focus-visible { outline: 2px solid var(--wr-cyan); }
        .wr-main { position: absolute; inset: 0; z-index: 1; pointer-events: none; }
        .wr-main > * { pointer-events: auto; }
        .wr-top, .wr-bottom { z-index: 2; }
        .wr-top { top: 0.75rem; inset-inline: 0.75rem; display: flex; align-items: center; justify-content: space-between; gap: 0.75rem; padding: 0.5rem 0.9rem; }
        .wr-title { display: flex; align-items: center; gap: 0.75rem; min-width: 0; }
        .wr-controls { display: flex; align-items: center; gap: 0.75rem; }
        .wr-roster { top: 5rem; bottom: 6.5rem; inset-inline-start: 0.75rem; width: 15rem; display: flex; flex-direction: column; padding: 0.6rem; }
        .wr-feed { top: 5rem; bottom: 6.5rem; inset-inline-end: 0.75rem; width: 17rem; display: flex; flex-direction: column; padding: 0.6rem; }
        .wr-selected { bottom: 6.5rem; inset-inline: 0; margin-inline: auto; width: min(24rem, calc(100% - 1.5rem)); padding: 0.7rem 0.9rem; }
        .wr-bottom { bottom: 0.75rem; inset-inline: 0.75rem; padding: 0.55rem 0.9rem; display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 0.6rem 1.5rem; }
        .wr-counters { display: flex; flex-wrap: wrap; gap: 0.4rem 1.6rem; }
        .wr-legend { display: flex; flex-wrap: wrap; gap: 0.3rem 1.1rem; }
        .wr-chips, .wr-below { display: none; }
        .wr-chip { display: inline-flex; align-items: center; gap: 0.4rem; flex: none; height: 2.25rem; padding-inline: 0.7rem; border-radius: 9999px; border: 1px solid var(--wr-line); background: rgb(7 16 32 / 0.8); color: var(--wr-text); font-size: 0.75rem; cursor: pointer; white-space: nowrap; }
        .wr-chip.sel { background: rgb(56 189 248 / 0.22); }
        .wr-chip:focus-visible { outline: 2px solid var(--wr-cyan); outline-offset: 2px; }
        @media (max-width: 1023px) {
            .wr-side, .wr-hint { display: none; }
            .wr-stage { height: 40rem; height: min(88svh, 46rem); display: flex; flex-direction: column; gap: 0.5rem; padding: 0.5rem; }
            .wr-stage:fullscreen { height: 100svh; }
            .wr-panel { position: relative; }
            .wr-main { position: relative; inset: auto; flex: 1 1 auto; min-height: 14rem; border-radius: 0.5rem; overflow: hidden; }
            .wr-top { inset-inline: auto; top: auto; flex-direction: column; align-items: stretch; gap: 0.5rem; padding: 0.5rem 0.7rem; }
            .wr-controls { flex-wrap: wrap; gap: 0.35rem; }
            .wr-controls select { flex: 1 1 100%; max-width: none !important; height: 2.5rem; }
            .wr-controls .wr-btn, .wr-seg button { height: 2.25rem; }
            .wr-chips { display: flex; gap: 0.4rem; overflow-x: auto; padding-bottom: 0.15rem; flex: none; scrollbar-width: none; }
            .wr-chips::-webkit-scrollbar { display: none; }
            .wr-selected { position: absolute; top: auto; bottom: 0.5rem; inset-inline: 0.5rem; margin-inline: 0; width: auto; max-height: 60%; overflow-y: auto; }
            .wr-bottom { inset-inline: auto; bottom: auto; padding: 0.5rem 0.7rem; flex: none; }
            .wr-counters { display: grid; grid-template-columns: repeat(4, 1fr); width: 100%; gap: 0.3rem; text-align: center; }
            .wr-counters .wr-label { display: block; }
            .wr-legend { width: 100%; justify-content: center; }
            .wr-below { display: block; margin-top: 0.75rem; padding: 0.75rem 0.9rem; border-radius: 0.75rem; border: 1px solid var(--wr-line); background: var(--wr-bg); color: var(--wr-text); }
        }
    </style>

    <div x-data="aiTeamMap(@js($this->mapData()), @js($this->missionId))">
        <div x-ref="stage" class="wr-stage" dir="rtl">
            {{-- top bar --}}
            <div class="wr-panel wr-top">
                <div class="wr-title">
                    <span class="wr-led on"></span>
                    <div style="min-width: 0;">
                        <div style="font-size: 0.95rem; font-weight: 700; white-space: nowrap;">اتاق فرماندهی دستیاران</div>
                        <div class="wr-label" x-show="!data.mission">نظارت زنده · فقط خواندنی</div>
                        <div class="wr-label" x-show="data.mission" x-text="data.mission ? 'مأموریت: ' + data.mission.title + ' · ' + data.mission.status : ''"></div>
                    </div>
                </div>
                <div class="wr-controls">
                    <div style="text-align: end;" class="wr-side">
                        <div class="wr-num" style="font-size: 1rem; color: var(--wr-cyan);" x-text="clock"></div>
                        <div class="wr-label" x-text="day"></div>
                    </div>
                    <select id="wr-mission" class="wr-btn" style="max-width: 11rem;" aria-label="نمایش تیم" x-on:change="pickMission($event.target.value)">
                        <option value="" :selected="!missionPick">همهٔ دستیارها</option>
                        <template x-for="item in data.missions" :key="item.id">
                            <option :value="item.id" :selected="missionPick === item.id" x-text="'مأموریت: ' + item.title"></option>
                        </template>
                    </select>
                    <div class="wr-seg" role="group" aria-label="سبک آواتار">
                        <button type="button" :class="style === 'robot' ? 'on' : ''" :aria-pressed="style === 'robot'" x-on:click="setStyle('robot')">ربات</button>
                        <button type="button" :class="style === 'cartoon' ? 'on' : ''" :aria-pressed="style === 'cartoon'" x-on:click="setStyle('cartoon')">کارتونی</button>
                    </div>
                    <button type="button" class="wr-btn" :aria-pressed="bubbles" :style="bubbles ? 'background: rgb(56 189 248 / 0.22);' : ''" x-on:click="toggleBubbles()">نظرها</button>
                    <button type="button" class="wr-btn" x-on:click="reset()">نمای کلی</button>
                    <button type="button" class="wr-btn" x-show="fullscreenOk" x-on:click="toggleFullscreen()" x-text="fullscreen ? 'خروج از تمام‌صفحه' : 'تمام‌صفحه'"></button>
                </div>
            </div>

            {{-- team chips (small screens) --}}
            <div class="wr-chips" role="group" aria-label="اعضای تیم">
                <template x-for="node in data.nodes" :key="node.id">
                    <button type="button" class="wr-chip" :class="selected && selected.id === node.id ? 'sel' : ''" :aria-pressed="!!selected && selected.id === node.id" x-on:click="select(node.id)">
                        <span class="wr-led" :class="node.live ? 'on' : ''"></span>
                        <span x-text="node.label"></span>
                    </button>
                </template>
            </div>

            <div class="wr-main">
            <div wire:ignore x-ref="scene" class="wr-scene"></div>

            {{-- roster --}}
            <div class="wr-panel wr-side wr-roster">
                <div class="wr-label" style="margin-bottom: 0.4rem;">اعضای تیم</div>
                <div style="overflow-y: auto; flex: 1; display: flex; flex-direction: column; gap: 0.15rem;">
                    <template x-for="node in data.nodes" :key="node.id">
                        <button type="button" class="wr-row" :class="selected && selected.id === node.id ? 'sel' : ''" x-on:click="select(node.id)">
                            <span class="wr-led" :class="node.live ? 'on' : ''"></span>
                            <span style="min-width: 0; flex: 1;">
                                <span style="display: block; font-size: 0.78rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" x-text="node.label"></span>
                                <span class="wr-label" style="display: block;" x-text="(node.role_label ? node.role_label + ' · ' : '') + (node.last_seen ? node.last_seen : 'بی‌فعالیت')"></span>
                                <span style="display: block; height: 3px; margin-top: 3px; border-radius: 2px; background: rgb(255 255 255 / 0.08);">
                                    <span style="display: block; height: 100%; border-radius: 2px;" :style="'width:' + Math.round((node.messages / maxMessages()) * 100) + '%; background:' + node.color"></span>
                                </span>
                            </span>
                        </button>
                    </template>
                </div>
            </div>

            {{-- transmissions --}}
            <div class="wr-panel wr-side wr-feed">
                <div class="wr-label" style="margin-bottom: 0.4rem;">مخابرات اخیر</div>
                <ul style="overflow-y: auto; flex: 1; display: flex; flex-direction: column; gap: 0.55rem; list-style: none; margin: 0; padding: 0;">
                    <template x-for="(item, index) in data.feed" :key="index">
                        <li style="display: flex; gap: 0.45rem; font-size: 0.72rem; line-height: 1.5;">
                            <span style="margin-top: 0.4rem; width: 0.4rem; height: 0.4rem; border-radius: 9999px; flex: none;" :style="'background:' + (item.kind === 'handoff' ? 'var(--wr-amber)' : 'var(--wr-cyan)')"></span>
                            <span style="min-width: 0;">
                                <span x-text="item.text"></span>
                                <span class="wr-label" x-text="' · ' + item.at"></span>
                                <span x-show="item.live" style="margin-inline-start: 0.25rem; font-size: 0.6rem; color: #34d399;">● همین الان</span>
                            </span>
                        </li>
                    </template>
                    <li x-show="data.feed.length === 0" class="wr-label" style="line-height: 1.8;">هنوز چیزی ثبت نشده.</li>
                </ul>
            </div>

            {{-- selected --}}
            <template x-if="selected">
                <div class="wr-panel wr-selected">
                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                        <span style="width: 0.65rem; height: 0.65rem; border-radius: 9999px;" :style="'background:' + selected.color"></span>
                        <strong style="font-size: 0.85rem;" x-text="selected.label"></strong>
                        <span x-show="selected.role_label" style="font-size: 0.65rem; color: var(--wr-dim);" x-text="selected.role_label"></span>
                        <span x-show="selected.live" style="font-size: 0.65rem; color: #34d399;">● فعال</span>
                        <button type="button" class="wr-btn" style="margin-inline-start: auto; height: 1.6rem;" x-on:click="reset()">بستن</button>
                    </div>
                    <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 0.5rem; margin-top: 0.6rem; text-align: center;">
                        <div><div class="wr-num" style="font-size: 1.15rem;" x-text="num(selected.messages)"></div><div class="wr-label" x-text="data.mission ? 'کارت' : 'پاسخ'"></div></div>
                        <div><div class="wr-num" style="font-size: 1.15rem;" x-text="num(selected.consulted)"></div><div class="wr-label" x-text="data.mission ? 'یافته' : 'مشورت داده'"></div></div>
                        <div><div class="wr-num" style="font-size: 1.15rem;" x-text="num(selected.asked)"></div><div class="wr-label" x-text="data.mission ? 'نقد' : 'مشورت گرفته'"></div></div>
                    </div>
                    <div x-show="selected.reply" style="margin-top: 0.6rem; padding: 0.5rem 0.6rem; border-radius: 0.5rem; background: rgb(255 255 255 / 0.07); font-size: 0.75rem; line-height: 1.7;">
                        <div class="wr-label"><span x-text="data.mission ? 'آخرین ثبت روی تخته' : 'آخرین پاسخ به شما'"></span> · <span x-text="selected.reply ? selected.reply.at : ''"></span></div>
                        <div x-text="selected.reply ? selected.reply.full : ''"></div>
                    </div>
                    <div class="wr-label" style="margin-top: 0.5rem;" x-show="selected.id !== 'manager' && !data.mission" x-text="selected.consultable ? 'قابل‌مشورت است.' : 'قابل‌مشورت نیست؛ دیگران از او نمی‌پرسند.'"></div>
                </div>
            </template>

            </div>

            {{-- bottom strip --}}
            <div class="wr-panel wr-bottom">
                <div class="wr-counters">
                    <template x-for="tile in [
                        { label: data.labels[0], key: 'assistants' },
                        { label: data.labels[1], key: 'consultations' },
                        { label: data.labels[2], key: 'handoffs' },
                        { label: data.labels[3], key: 'live' },
                    ]" :key="tile.key">
                        <div><span class="wr-num" style="font-size: 1.3rem; color: var(--wr-cyan);" x-text="num(shown[tile.key])"></span> <span class="wr-label" x-text="tile.label"></span></div>
                    </template>
                </div>
                <div class="wr-label wr-legend">
                    <template x-for="item in data.legend" :key="item.label">
                        <span><span style="display: inline-block; width: 1.1rem; height: 2px; vertical-align: middle;" :style="'background:' + item.color"></span> <span x-text="item.label"></span></span>
                    </template>
                    <span class="wr-hint">ذرهٔ نوری = پیام در جریان · حلقه دور دستیار = فعال در ۵ دقیقهٔ اخیر</span>
                </div>
            </div>
        </div>
        <div class="wr-below" dir="rtl">
            <div class="wr-label" style="margin-bottom: 0.5rem;">مخابرات اخیر</div>
            <ul style="display: flex; flex-direction: column; gap: 0.6rem; list-style: none; margin: 0; padding: 0;">
                <template x-for="(item, index) in data.feed" :key="index">
                    <li style="display: flex; gap: 0.5rem; font-size: 0.8rem; line-height: 1.7;">
                        <span style="margin-top: 0.5rem; width: 0.45rem; height: 0.45rem; border-radius: 9999px; flex: none;" :style="'background:' + (item.kind === 'handoff' ? 'var(--wr-amber)' : 'var(--wr-cyan)')"></span>
                        <span style="min-width: 0;">
                            <span x-text="item.text"></span>
                            <span class="wr-label" x-text="' · ' + item.at"></span>
                        </span>
                    </li>
                </template>
                <li x-show="data.feed.length === 0" class="wr-label">هنوز چیزی ثبت نشده.</li>
            </ul>
        </div>
        <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">بکشید تا بچرخد، اسکرول کنید تا زوم شود، روی یک دستیار بزنید تا دوربین به سمتش برود. این نما فقط دستیارهای خودتان و اشتراکی‌ها را نشان می‌دهد.</p>
    </div>
</x-filament-panels::page>
