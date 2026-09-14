@include('filament.components.leaflet-assets')

<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    {{--
        Same reasoning as signature-pad.blade.php: this field only exists
        after the user picks a form template, so a plain wire:model on a
        hidden input is used instead of $wire.$entangle().
    --}}
    <div
        x-data="{
            locating: false,
            error: null,
            parsed: null,
            map: null,
            marker: null,
            init() {
                this.syncParsed(this.$refs.input.value);
                this.$nextTick(() => this.renderMap());
            },
            syncParsed(raw) {
                try { this.parsed = raw ? JSON.parse(raw) : null; } catch (e) { this.parsed = null; }
            },
            renderMap() {
                if (! this.parsed || ! window.L) {
                    return;
                }

                const point = [this.parsed.lat, this.parsed.lng];

                if (! this.map) {
                    this.map = L.map(this.$refs.mapContainer, { attributionControl: true }).setView(point, 16);
                    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                        maxZoom: 19,
                        attribution: '&copy; OpenStreetMap',
                    }).addTo(this.map);
                    this.marker = L.marker(point).addTo(this.map);
                } else {
                    this.map.setView(point, 16);
                    this.marker.setLatLng(point);
                }

                // The map container is hidden (x-show, display:none) until a
                // point exists, and Leaflet sizes its canvas from the
                // container's dimensions at creation time - if that
                // happened while hidden, the tiles render into a 0x0 box.
                // invalidateSize() re-measures it now that it's visible.
                this.$nextTick(() => this.map?.invalidateSize());
            },
            locate() {
                this.error = null;

                if (! navigator.geolocation) {
                    this.error = 'مرورگر شما از موقعیت مکانی پشتیبانی نمی‌کند.';

                    return;
                }

                this.locating = true;

                navigator.geolocation.getCurrentPosition(
                    (position) => {
                        this.locating = false;
                        const raw = JSON.stringify({
                            lat: position.coords.latitude,
                            lng: position.coords.longitude,
                        });
                        this.$refs.input.value = raw;
                        this.$refs.input.dispatchEvent(new Event('input', { bubbles: true }));
                        this.syncParsed(raw);
                        this.renderMap();
                    },
                    () => {
                        this.locating = false;
                        this.error = 'دسترسی به موقعیت مکانی رد شد یا در دسترس نیست.';
                    },
                    { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 },
                );
            },
        }"
        class="space-y-2"
    >
        <input type="hidden" x-ref="input" wire:model="{{ $getStatePath() }}" />

        <button
            type="button"
            x-on:click="locate"
            x-bind:disabled="locating"
            class="fi-btn fi-btn-size-sm inline-flex items-center gap-1.5 rounded-lg bg-primary-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-primary-500 disabled:opacity-60"
        >
            <span x-show="! locating">دریافت موقعیت فعلی</span>
            <span x-show="locating">در حال دریافت…</span>
        </button>

        <div x-show="parsed" style="display: none;">
            <div
                x-ref="mapContainer"
                style="height: 12rem; width: 100%; border-radius: .5rem; border: 1px solid #e7e5e4; overflow: hidden;"
            ></div>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                عرض جغرافیایی: <span x-text="parsed?.lat"></span> — طول جغرافیایی: <span x-text="parsed?.lng"></span>
                <template x-if="parsed">
                    <a
                        :href="'https://www.google.com/maps?q=' + parsed.lat + ',' + parsed.lng"
                        target="_blank"
                        rel="noopener"
                        class="text-primary-600 hover:underline dark:text-primary-400"
                    >
                        (باز کردن در Google Maps)
                    </a>
                </template>
            </p>
        </div>
        <p x-show="error" x-text="error" class="text-xs text-danger-600 dark:text-danger-400"></p>
    </div>
</x-dynamic-component>
