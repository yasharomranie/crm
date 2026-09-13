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
            init() {
                this.syncParsed(this.$refs.input.value);
            },
            syncParsed(raw) {
                try { this.parsed = raw ? JSON.parse(raw) : null; } catch (e) { this.parsed = null; }
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

        <p x-show="parsed" class="text-xs text-gray-600 dark:text-gray-400">
            عرض جغرافیایی: <span x-text="parsed?.lat"></span> — طول جغرافیایی: <span x-text="parsed?.lng"></span>
        </p>
        <p x-show="error" x-text="error" class="text-xs text-danger-600 dark:text-danger-400"></p>
    </div>
</x-dynamic-component>
