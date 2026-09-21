<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    {{--
        This field is injected into the form dynamically (after the user
        picks a form template), so $wire.$entangle() can't bind to it -
        entangle only works for state paths that already existed when the
        Livewire component first mounted. A plain wire:model on a hidden
        input works instead, since Livewire re-scans the DOM for it on
        every update rather than binding once at init.

        wire:key gives Livewire's morph a stable identity per field when a
        form has two+ signature fields, both inserted into the DOM in the
        same update (right after the template is picked) as structurally
        identical <div x-data> blocks.

        canvasContext() re-resolves this.$refs.canvas.getContext('2d')
        every time rather than caching it once in init() - a real,
        reproduced bug: with a cached this.ctx, drawing on one signature
        pad visibly landed on the *other* pad's canvas whenever Livewire's
        morph reassigned which physical <canvas> element ended up at which
        position while inserting the two new fields together. $refs.canvas
        itself always stays correct (Alpine re-resolves it fresh off the
        current DOM), so routing every draw operation through it - instead
        of a value captured once at init() - can't go stale the same way.
        getContext('2d') is cheap to call repeatedly (the browser returns
        the same context object for a given canvas), so this costs nothing.
    --}}
    <div
        wire:key="{{ $getStatePath() }}-signature-pad"
        x-data="{
            drawing: false,
            hasDrawing: false,
            canvasContext() {
                const ctx = this.$refs.canvas.getContext('2d');
                ctx.lineWidth = 2.4;
                ctx.lineCap = 'round';
                ctx.strokeStyle = '#111827';

                return ctx;
            },
            init() {
                const existing = this.$refs.input.value;

                if (existing) {
                    const img = new Image();
                    img.onload = () => this.canvasContext().drawImage(img, 0, 0);
                    img.src = existing;
                    this.hasDrawing = true;
                }
            },
            pos(event) {
                const rect = this.$refs.canvas.getBoundingClientRect();
                const point = event.touches ? event.touches[0] : event;

                return { x: point.clientX - rect.left, y: point.clientY - rect.top };
            },
            start(event) {
                event.preventDefault();
                this.drawing = true;
                this.hasDrawing = true;
                const { x, y } = this.pos(event);
                this.canvasContext().beginPath();
                this.canvasContext().moveTo(x, y);
            },
            move(event) {
                if (! this.drawing) return;
                event.preventDefault();
                const { x, y } = this.pos(event);
                this.canvasContext().lineTo(x, y);
                this.canvasContext().stroke();
            },
            end() {
                if (! this.drawing) return;
                this.drawing = false;
                this.$refs.input.value = this.$refs.canvas.toDataURL('image/png');
                this.$refs.input.dispatchEvent(new Event('input', { bubbles: true }));
            },
            clear() {
                this.canvasContext().clearRect(0, 0, this.$refs.canvas.width, this.$refs.canvas.height);
                this.hasDrawing = false;
                this.$refs.input.value = '';
                this.$refs.input.dispatchEvent(new Event('input', { bubbles: true }));
            },
        }"
        class="space-y-2"
    >
        <input type="hidden" x-ref="input" wire:model="{{ $getStatePath() }}" />

        <canvas
            x-ref="canvas"
            width="500"
            height="160"
            class="w-full max-w-md touch-none rounded-lg border border-gray-300 bg-white dark:border-white/10"
            x-on:mousedown="start"
            x-on:mousemove="move"
            x-on:mouseup="end"
            x-on:mouseleave="end"
            x-on:touchstart="start"
            x-on:touchmove="move"
            x-on:touchend="end"
        ></canvas>

        <div class="flex items-center gap-3">
            <button
                type="button"
                x-on:click="clear"
                class="fi-btn fi-btn-size-sm fi-color-gray fi-btn-color-gray inline-flex items-center gap-1 rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50 dark:border-white/10 dark:text-gray-300 dark:hover:bg-white/5"
            >
                پاک کردن امضا
            </button>
            <span x-show="! hasDrawing" class="text-xs text-gray-500">با انگشت یا موس اینجا امضا کنید</span>
        </div>
    </div>
</x-dynamic-component>
