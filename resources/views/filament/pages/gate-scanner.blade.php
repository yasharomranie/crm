<x-filament-panels::page>
    <div x-data="gateScanner()" class="mx-auto max-w-lg space-y-4">
        <select wire:model="gateId" class="fi-input w-full rounded-lg text-base">
            <option value="">گیت را انتخاب کنید</option>
            @foreach ($this->getGates() as $gate)
                <option value="{{ $gate->id }}">{{ $gate->name }}</option>
            @endforeach
        </select>

        <input
            type="text"
            x-ref="input"
            wire:model="qrToken"
            x-on:keydown.enter.prevent="$wire.scan()"
            placeholder="QR بلیت را اسکن کنید..."
            autofocus
            class="fi-input w-full rounded-lg py-4 text-center text-base"
        />

        @if ($lastResult === 'success')
            <div class="rounded-2xl bg-success-50 p-8 text-center dark:bg-success-500/10">
                <div class="text-5xl">✓</div>
                <p class="mt-3 text-xl font-bold text-success-800 dark:text-success-300">معتبر است</p>
                <p class="mt-1 text-sm text-success-700 dark:text-success-400">بلیت: {{ $lastTicketNumber }}</p>
                @if ($lastUsageSummary)
                    <p class="text-sm text-success-700 dark:text-success-400">{{ $lastUsageSummary }}</p>
                @endif
            </div>
        @elseif ($lastResult === 'rejected')
            <div class="rounded-2xl bg-danger-50 p-8 text-center dark:bg-danger-500/10">
                <div class="text-5xl">✕</div>
                <p class="mt-3 text-xl font-bold text-danger-800 dark:text-danger-300">نامعتبر</p>
                <p class="mt-1 text-sm text-danger-700 dark:text-danger-400">{{ $lastReason }}</p>
                @if ($lastTicketNumber)
                    <p class="text-xs text-danger-600 dark:text-danger-500">بلیت: {{ $lastTicketNumber }}</p>
                @endif
            </div>
        @endif
    </div>

    @script
    <script>
        Alpine.data('gateScanner', () => ({
            init() {
                // Keeps the input focused after every Livewire update too
                // (a scanner "typing" fast right after a result renders
                // must not need a manual click back into the field).
                this.$wire.$on('scan-handled', () => this.$nextTick(() => this.$refs.input?.focus()));
                this.$refs.input?.focus();
            },
        }));
    </script>
    @endscript
</x-filament-panels::page>
