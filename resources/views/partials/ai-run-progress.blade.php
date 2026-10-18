{{-- What the running question is doing, with a stop button. The question
     itself is one long Livewire request, so progress comes from a separate
     endpoint polled in parallel (see AssistantRunController). wire:ignore keeps
     Livewire's morphing from resetting the Alpine state mid-run. --}}
<div wire:ignore>
    <div
        x-data="aiRunProgress(@js(route('ai-assistant.run-status')), @js(route('ai-assistant.stop')))"
        x-show="running"
        x-cloak
        role="status"
        aria-live="polite"
        class="flex items-center justify-between gap-3 rounded-xl border border-primary-200 bg-primary-50 px-3 py-2 text-xs text-primary-800 dark:border-primary-500/30 dark:bg-primary-500/10 dark:text-primary-200"
    >
        <span class="flex items-center gap-2">
            <span class="h-2 w-2 animate-pulse rounded-full bg-primary-500"></span>
            <span x-text="label"></span>
        </span>
        <button
            type="button"
            x-on:click="stop()"
            x-bind:disabled="stopping"
            class="rounded-lg border border-primary-300 px-2 py-1 font-medium hover:bg-primary-100 disabled:opacity-50 dark:border-primary-500/40 dark:hover:bg-primary-500/20"
        >
            متوقف کن
        </button>
    </div>
</div>

<script>
    window.aiRunProgress = window.aiRunProgress || function (statusUrl, stopUrl) {
        return {
            running: false,
            stopping: false,
            label: 'در حال بررسی درخواست…',
            timer: null,

            init() {
                try {
                    if (window.__aiRunProgressHook) {
                        window.__aiRunProgressHook()
                    }

                    window.__aiRunProgressHook = Livewire.hook('commit', ({ commit, succeed, fail }) => {
                        if (! (commit.calls || []).some((call) => call.method === 'send')) {
                            return
                        }

                        this.begin()
                        succeed(() => this.end())
                        fail(() => this.end())
                    })
                } catch (error) {
                    // Progress is a nicety; the question still runs without it.
                }

                window.addEventListener('message-sent', () => this.end())
            },

            begin() {
                this.running = true
                this.stopping = false
                this.label = 'در حال بررسی درخواست…'
                clearInterval(this.timer)
                this.timer = setInterval(() => this.poll(), 1500)
            },

            end() {
                this.running = false
                clearInterval(this.timer)
            },

            poll() {
                fetch(statusUrl, { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
                    .then((response) => response.json())
                    .then((data) => {
                        if (data.running && data.label) {
                            this.label = data.stopping ? 'در حال توقف…' : data.label
                        }
                    })
                    .catch(() => {})
            },

            stop() {
                this.stopping = true
                this.label = 'در حال توقف…'

                const token = decodeURIComponent((document.cookie.match(/XSRF-TOKEN=([^;]+)/) || [])[1] || '')

                fetch(stopUrl, {
                    method: 'POST',
                    headers: { Accept: 'application/json', 'X-XSRF-TOKEN': token },
                    credentials: 'same-origin',
                }).catch(() => {})
            },
        }
    }
</script>
