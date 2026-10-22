@include('filament.components.leaflet-assets')

<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    {{--
        Same reasoning as signature-pad.blade.php: this field only exists
        after the user picks a form template, so a plain wire:model on a
        hidden input is used instead of $wire.$entangle(). wire:key gives
        Livewire's morph a stable identity per field - needed for a form
        with two+ GPS fields, both inserted into the DOM in the same
        update. See renderMap()'s own guard below for the actual bug this
        setup can otherwise cause (reproduced first on the signature pad):
        a `this.map` created against one field's container silently
        rendering there even after the morph reassigns that physical
        container to a *different* field.
    --}}
    <div
        wire:key="{{ $getStatePath() }}-gps-picker"
        x-data="{
            locating: false,
            error: null,
            parsed: null,
            map: null,
            marker: null,
            init() {
                this.syncParsed(this.$refs.input.value);
                this.renderMap();
            },
            syncParsed(raw) {
                try { this.parsed = raw ? JSON.parse(raw) : null; } catch (e) { this.parsed = null; }
            },
            renderMap() {
                if (! this.parsed || ! window.L) {
                    return;
                }

                // The map container sits inside an x-show block that's
                // display:none until a point exists - Alpine only flips
                // that *after* this tick finishes, so if renderMap() runs
                // in direct response to setting `parsed` (as opposed to
                // after $nextTick), the container is still 0x0 the instant
                // L.map() reads its size, and Leaflet is left showing a
                // blank white box forever after (invalidateSize() later
                // does not reliably recover from that). Every caller of
                // renderMap() must go through $nextTick() first - never
                // call this directly.
                this.$nextTick(() => {
                    const point = [this.parsed.lat, this.parsed.lng];

                    // Guards the same class of bug fixed on the signature
                    // pad (a form with two+ GPS fields both inserted in one
                    // Livewire update): if the morph ever reassigns which
                    // physical container element ends up at this field's
                    // position, a `this.map` created against the old
                    // container would silently keep rendering there
                    // instead of the one now actually shown. $refs always
                    // re-resolves to the current element, so comparing
                    // against it catches the mismatch and rebuilds the map
                    // against the right one.
                    if (this.map && this.map.getContainer() !== this.$refs.mapContainer) {
                        this.map.remove();
                        this.map = null;
                        this.marker = null;
                    }

                    if (! this.map) {
                        this.map = L.map(this.$refs.mapContainer, { attributionControl: true }).setView(point, 16);
                        window.crmTileLayer(this.map, { maxZoom: 19 });
                        this.marker = L.marker(point).addTo(this.map);
                    } else {
                        this.map.setView(point, 16);
                        this.marker.setLatLng(point);
                    }

                    // Belt-and-suspenders on top of the $nextTick() above:
                    // requestAnimationFrame waits for the browser's next
                    // layout/paint pass too, and the extra invalidateSize()
                    // after a short delay covers slower devices where even
                    // that isn't quite enough time for the container's
                    // final size to settle.
                    requestAnimationFrame(() => this.map?.invalidateSize());
                    setTimeout(() => this.map?.invalidateSize(), 300);
                });
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
