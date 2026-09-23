<x-filament-panels::page>
    <div x-data="gateScanner()" class="mx-auto max-w-lg space-y-4">
        {{-- Online/offline status + pending-sync count. The scanner never
            blocks on this - it's purely informational, see handleScan(). --}}
        <div
            class="flex items-center justify-between gap-2 rounded-lg px-3 py-2 text-sm"
            :class="online ? 'bg-success-50 text-success-700 dark:bg-success-500/10 dark:text-success-400' : 'bg-warning-50 text-warning-700 dark:bg-warning-500/10 dark:text-warning-400'"
        >
            <span class="flex items-center gap-1.5">
                <span class="h-2 w-2 shrink-0 rounded-full bg-current"></span>
                <span x-text="online ? 'آنلاین' : 'آفلاین - اسکن‌ها محلی ذخیره و بعداً ارسال می‌شن'"></span>
            </span>
            <span x-show="pendingCount > 0" class="flex items-center gap-2">
                <span x-text="pendingCount + ' اسکن در صف ارسال'"></span>
                <button
                    type="button"
                    x-show="online"
                    x-on:click="pushPendingScans()"
                    :disabled="syncing"
                    class="rounded-md bg-white/60 px-2 py-1 text-xs font-medium hover:bg-white disabled:cursor-not-allowed disabled:opacity-50 dark:bg-black/20 dark:hover:bg-black/30"
                >
                    ارسال الان
                </button>
            </span>
        </div>

        <select
            wire:model="gateId"
            x-on:change="onGateChange($event.target.value)"
            class="fi-input w-full rounded-lg text-base"
        >
            <option value="">گیت را انتخاب کنید</option>
            @foreach ($this->getGates() as $gate)
                <option value="{{ $gate->id }}">{{ $gate->name }}</option>
            @endforeach
        </select>

        <input
            type="text"
            x-ref="input"
            wire:model="qrToken"
            x-on:keydown.enter.prevent="handleScan($event.target.value)"
            placeholder="QR بلیت را اسکن کنید..."
            autofocus
            class="fi-input w-full rounded-lg py-4 text-center text-base"
        />

        {{-- Offline-computed result (bypasses Livewire entirely - see
            handleScan()). The existing $lastResult block below still
            handles the online path unchanged. --}}
        <template x-if="offlineResult">
            <div>
                <template x-if="offlineResult.result === 'success'">
                    <div class="rounded-2xl bg-success-50 p-8 text-center dark:bg-success-500/10">
                        <div class="text-5xl">✓</div>
                        <p class="mt-3 text-xl font-bold text-success-800 dark:text-success-300">معتبر است (آفلاین)</p>
                        <p class="mt-1 text-sm text-success-700 dark:text-success-400" x-text="'بلیت: ' + offlineResult.ticketNumber"></p>
                        <p x-show="offlineResult.usageSummary" class="text-sm text-success-700 dark:text-success-400" x-text="offlineResult.usageSummary"></p>
                    </div>
                </template>
                <template x-if="offlineResult.result === 'rejected'">
                    <div class="rounded-2xl bg-danger-50 p-8 text-center dark:bg-danger-500/10">
                        <div class="text-5xl">✕</div>
                        <p class="mt-3 text-xl font-bold text-danger-800 dark:text-danger-300">نامعتبر (آفلاین)</p>
                        <p class="mt-1 text-sm text-danger-700 dark:text-danger-400" x-text="offlineResult.reasonLabel"></p>
                        <p x-show="offlineResult.ticketNumber" class="text-xs text-danger-600 dark:text-danger-500" x-text="'بلیت: ' + offlineResult.ticketNumber"></p>
                    </div>
                </template>
            </div>
        </template>

        @if ($lastResult === 'success')
            <div class="rounded-2xl bg-success-50 p-8 text-center dark:bg-success-500/10" x-show="! offlineResult">
                <div class="text-5xl">✓</div>
                <p class="mt-3 text-xl font-bold text-success-800 dark:text-success-300">معتبر است</p>
                <p class="mt-1 text-sm text-success-700 dark:text-success-400">بلیت: {{ $lastTicketNumber }}</p>
                @if ($lastUsageSummary)
                    <p class="text-sm text-success-700 dark:text-success-400">{{ $lastUsageSummary }}</p>
                @endif
            </div>
        @elseif ($lastResult === 'rejected')
            <div class="rounded-2xl bg-danger-50 p-8 text-center dark:bg-danger-500/10" x-show="! offlineResult">
                <div class="text-5xl">✕</div>
                <p class="mt-3 text-xl font-bold text-danger-800 dark:text-danger-300">نامعتبر</p>
                <p class="mt-1 text-sm text-danger-700 dark:text-danger-400">{{ $lastReason }}</p>
                @if ($lastTicketNumber)
                    <p class="text-xs text-danger-600 dark:text-danger-500">بلیت: {{ $lastTicketNumber }}</p>
                @endif
            </div>
        @endif

        {{-- A ticket a gate accepted offline that the server, as the real
            source of truth, later disagreed with once synced (e.g. the
            same one-time ticket was also scanned at another gate while
            this one was offline) - never silently dropped. --}}
        <div x-show="reconciliationWarnings.length > 0" x-cloak class="space-y-2 rounded-lg border border-warning-300 bg-warning-50 p-3 text-sm dark:border-warning-500/30 dark:bg-warning-500/10">
            <p class="font-semibold text-warning-800 dark:text-warning-300">این بلیت‌ها بعد از همگام‌سازی نتیجه‌ی متفاوتی داشتن، بررسی کنید:</p>
            <template x-for="warning in reconciliationWarnings" :key="warning.client_scan_id">
                <p class="text-warning-700 dark:text-warning-400">
                    <span x-text="warning.ticket_number || warning.qr_token"></span> -
                    <span x-text="'تصمیم محلی: ' + (warning.local_result === 'success' ? 'قبول' : 'رد')"></span>،
                    <span x-text="'نتیجه‌ی واقعی: ' + (warning.result === 'success' ? 'قبول' : 'رد')"></span>
                </p>
            </template>
            <button type="button" x-on:click="reconciliationWarnings = []" class="text-xs font-medium text-warning-800 underline dark:text-warning-300">
                متوجه شدم
            </button>
        </div>
    </div>

    @script
    <script>
        Alpine.data('gateScanner', () => ({
            online: navigator.onLine,
            syncing: false,
            pendingCount: 0,
            lastSyncedAt: null,
            selectedGateId: @js($gateId),
            gate: null,
            rules: [],
            offlineResult: null,
            reconciliationWarnings: [],
            db: null,

            init() {
                // Keeps the input focused after every Livewire update too
                // (a scanner "typing" fast right after a result renders
                // must not need a manual click back into the field).
                this.$wire.$on('scan-handled', () => this.$nextTick(() => this.$refs.input?.focus()));
                this.$refs.input?.focus();

                window.addEventListener('online', () => {
                    this.online = true;
                    this.syncPackage().then(() => this.pushPendingScans());
                });
                window.addEventListener('offline', () => {
                    this.online = false;
                });

                this.openDb().then(() => {
                    this.refreshPendingCount();

                    if (this.selectedGateId) {
                        this.loadCachedPackage();
                    }

                    if (this.online && this.selectedGateId) {
                        this.syncPackage().then(() => this.pushPendingScans());
                    }
                });
            },

            onGateChange(gateId) {
                this.selectedGateId = gateId ? parseInt(gateId, 10) : null;
                this.offlineResult = null;

                if (this.selectedGateId && this.online) {
                    this.syncPackage();
                } else if (this.selectedGateId) {
                    this.loadCachedPackage();
                }
            },

            // --- IndexedDB -----------------------------------------------

            openDb() {
                return new Promise((resolve, reject) => {
                    const request = indexedDB.open('gate-scanner-offline', 1);

                    request.onupgradeneeded = () => {
                        const db = request.result;

                        if (! db.objectStoreNames.contains('tickets')) {
                            db.createObjectStore('tickets', { keyPath: 'token' });
                        }
                        if (! db.objectStoreNames.contains('pendingScans')) {
                            db.createObjectStore('pendingScans', { keyPath: 'client_scan_id' });
                        }
                        if (! db.objectStoreNames.contains('meta')) {
                            db.createObjectStore('meta', { keyPath: 'key' });
                        }
                    };

                    request.onsuccess = () => {
                        this.db = request.result;
                        resolve(this.db);
                    };
                    request.onerror = () => reject(request.error);
                });
            },

            idbGetAll(storeName) {
                return new Promise((resolve, reject) => {
                    const request = this.db.transaction(storeName, 'readonly').objectStore(storeName).getAll();
                    request.onsuccess = () => resolve(request.result);
                    request.onerror = () => reject(request.error);
                });
            },

            idbGet(storeName, key) {
                return new Promise((resolve, reject) => {
                    const request = this.db.transaction(storeName, 'readonly').objectStore(storeName).get(key);
                    request.onsuccess = () => resolve(request.result ?? null);
                    request.onerror = () => reject(request.error);
                });
            },

            idbPut(storeName, value) {
                return new Promise((resolve, reject) => {
                    const request = this.db.transaction(storeName, 'readwrite').objectStore(storeName).put(value);
                    request.onsuccess = () => resolve();
                    request.onerror = () => reject(request.error);
                });
            },

            idbDelete(storeName, key) {
                return new Promise((resolve, reject) => {
                    const request = this.db.transaction(storeName, 'readwrite').objectStore(storeName).delete(key);
                    request.onsuccess = () => resolve();
                    request.onerror = () => reject(request.error);
                });
            },

            idbClear(storeName) {
                return new Promise((resolve, reject) => {
                    const request = this.db.transaction(storeName, 'readwrite').objectStore(storeName).clear();
                    request.onsuccess = () => resolve();
                    request.onerror = () => reject(request.error);
                });
            },

            async refreshPendingCount() {
                const all = await this.idbGetAll('pendingScans');
                this.pendingCount = all.length;
            },

            async loadCachedPackage() {
                const gateMeta = await this.idbGet('meta', 'gate:' + this.selectedGateId);
                const rulesMeta = await this.idbGet('meta', 'rules:' + this.selectedGateId);
                this.gate = gateMeta?.value ?? null;
                this.rules = rulesMeta?.value ?? [];
            },

            // --- Sync (online only) ---------------------------------------

            async syncPackage() {
                if (! this.selectedGateId || this.syncing) {
                    return;
                }

                this.syncing = true;

                try {
                    const response = await fetch(`/gate-scanner/${this.selectedGateId}/sync-package`, {
                        headers: { 'Accept': 'application/json' },
                    });

                    if (! response.ok) {
                        return;
                    }

                    const data = await response.json();

                    await this.idbClear('tickets');
                    for (const ticket of data.tickets) {
                        await this.idbPut('tickets', ticket);
                    }
                    await this.idbPut('meta', { key: 'gate:' + this.selectedGateId, value: data.gate });
                    await this.idbPut('meta', { key: 'rules:' + this.selectedGateId, value: data.rules });

                    this.gate = data.gate;
                    this.rules = data.rules;
                    this.lastSyncedAt = data.synced_at;
                } catch (e) {
                    // No connection after all (navigator.onLine can be a
                    // false positive - e.g. a captive portal) - the cached
                    // package from the last successful sync stays in use.
                } finally {
                    this.syncing = false;
                }
            },

            async pushPendingScans() {
                if (this.syncing) {
                    return;
                }

                const pending = await this.idbGetAll('pendingScans');

                if (pending.length === 0) {
                    return;
                }

                // One gate might have queued offline scans for more than
                // one gate_id if the operator switched gates while
                // offline - group and send each batch to its own gate.
                const byGate = {};
                for (const scan of pending) {
                    (byGate[scan.gate_id] ??= []).push(scan);
                }

                this.syncing = true;

                try {
                    for (const [gateId, scans] of Object.entries(byGate)) {
                        const response = await fetch(`/gate-scanner/${gateId}/sync-scans`, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': this.csrfToken(),
                            },
                            body: JSON.stringify({
                                scans: scans.map((s) => ({
                                    client_scan_id: s.client_scan_id,
                                    qr_token: s.qr_token,
                                    scanned_at: s.scanned_at,
                                    local_result: s.local_result,
                                    local_reason: s.local_reason,
                                })),
                            }),
                        });

                        if (! response.ok) {
                            continue;
                        }

                        const data = await response.json();

                        for (const result of data.results) {
                            await this.idbDelete('pendingScans', result.client_scan_id);

                            if (! result.reconciled) {
                                const original = scans.find((s) => s.client_scan_id === result.client_scan_id);
                                this.reconciliationWarnings.push({
                                    client_scan_id: result.client_scan_id,
                                    qr_token: original?.qr_token,
                                    ticket_number: result.ticket_number,
                                    local_result: original?.local_result,
                                    result: result.result,
                                });
                            }
                        }
                    }
                } catch (e) {
                    // Still offline (or lost connection again mid-push) -
                    // whatever wasn't deleted above stays queued for next
                    // time.
                } finally {
                    this.syncing = false;
                    await this.refreshPendingCount();
                }
            },

            csrfToken() {
                return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
            },

            // --- Scanning --------------------------------------------------

            async handleScan(token) {
                token = (token || '').trim();

                if (! token) {
                    return;
                }

                if (! this.selectedGateId) {
                    this.offlineResult = { result: 'rejected', reasonLabel: 'ابتدا گیت را انتخاب کنید.', ticketNumber: null };

                    return;
                }

                if (this.online) {
                    // Give the live Livewire round-trip a real chance first
                    // - navigator.onLine only reflects link-layer
                    // connectivity, not whether the server is actually
                    // reachable (a captive portal, or the connection
                    // dropping mid-request), so a failure here still falls
                    // back to fully offline validation instead of just
                    // hanging with no result on screen.
                    try {
                        this.offlineResult = null;
                        await Promise.race([
                            this.$wire.scan(),
                            new Promise((_, reject) => setTimeout(() => reject(new Error('timeout')), 4000)),
                        ]);

                        return;
                    } catch (e) {
                        // Falls through to the offline path below.
                    }
                }

                await this.scanOffline(token);
            },

            async scanOffline(token) {
                const result = await this.evaluateOffline(token);

                this.offlineResult = result;

                // No Livewire round-trip happens on this path, so nothing
                // else clears/refocuses the input the way the server's own
                // scan() response normally does for the online path.
                if (this.$refs.input) {
                    this.$refs.input.value = '';
                    this.$wire.qrToken = '';
                    this.$nextTick(() => this.$refs.input.focus());
                }

                await this.idbPut('pendingScans', {
                    client_scan_id: (crypto.randomUUID ? crypto.randomUUID() : (Date.now() + '-' + Math.random())),
                    gate_id: this.selectedGateId,
                    qr_token: token,
                    scanned_at: new Date().toISOString(),
                    local_result: result.result,
                    local_reason: result.reasonCode ?? null,
                });
                await this.refreshPendingCount();
            },

            /**
             * A JS port of App\Domain\Ticketing\GateValidationService::scan()
             * running entirely against the IndexedDB snapshot from the last
             * successful sync - see that class for the authoritative PHP
             * version this must stay in lockstep with. Only ever advisory:
             * pushPendingScans() replays the real thing server-side the
             * moment there's a connection again.
             */
            async evaluateOffline(token) {
                const REASON_LABELS = {
                    invalid_qr: 'کد QR نامعتبر است یا هنوز همگام‌سازی نشده (وقتی نت وصل بشه دوباره چک می‌شه).',
                    expired: 'اعتبار این بلیت به پایان رسیده است.',
                    no_remaining_usage: 'ظرفیت مصرف این بلیت تمام شده است.',
                    wrong_gate: 'این بلیت برای این گیت مجاز نیست.',
                    sequence_error: 'ترتیب مصرف این بلیت رعایت نشده است (مثلاً برگشت قبل از رفت).',
                    not_yet_valid: 'اعتبار این بلیت هنوز آغاز نشده است.',
                };

                const reject = (reasonCode, ticketNumber = null) => ({
                    result: 'rejected',
                    reasonCode,
                    reasonLabel: REASON_LABELS[reasonCode] || reasonCode,
                    ticketNumber,
                });

                const ticket = await this.idbGet('tickets', token);

                if (! ticket) {
                    return reject('invalid_qr');
                }

                const now = new Date();

                if (now < new Date(ticket.valid_from)) {
                    return reject('not_yet_valid', ticket.ticket_number);
                }

                if (now >= new Date(ticket.valid_until)) {
                    return reject('expired', ticket.ticket_number);
                }

                const remainingUses = Math.max(0, ticket.allowed_uses - ticket.used_uses);

                if (remainingUses <= 0) {
                    return reject('no_remaining_usage', ticket.ticket_number);
                }

                const nextUsageNumber = ticket.used_uses + 1;

                const ruleMatches = (rule, usageNumber) => {
                    if (rule.product_id && rule.product_id !== ticket.product_id) {
                        return false;
                    }
                    if (rule.ticket_variant_id && rule.ticket_variant_id !== ticket.ticket_variant_id) {
                        return false;
                    }
                    if (rule.direction && rule.direction !== ticket.direction) {
                        return false;
                    }
                    if (rule.usage_stage && rule.usage_stage !== usageNumber) {
                        return false;
                    }

                    return true;
                };

                const matchedRule = this.rules.find((rule) => ruleMatches(rule, nextUsageNumber));

                if (! matchedRule) {
                    const anyRuleForThisTicket = this.rules.some((rule) => ruleMatches(rule, 1) || ruleMatches(rule, 2));
                    const reasonCode = anyRuleForThisTicket ? 'sequence_error' : 'wrong_gate';

                    return reject(reasonCode, ticket.ticket_number);
                }

                // Consume locally - written back to IndexedDB immediately so
                // a second offline scan of the same ticket (a real repeat,
                // or someone trying to reuse a one-time ticket) is correctly
                // rejected too, exactly like the server's own used_uses
                // increment.
                ticket.used_uses += 1;
                ticket.status = ticket.used_uses >= ticket.allowed_uses ? 'used' : 'partially_used';
                await this.idbPut('tickets', ticket);

                return {
                    result: 'success',
                    reasonCode: null,
                    ticketNumber: ticket.ticket_number,
                    usageSummary: `مصرف ${nextUsageNumber} از ${ticket.allowed_uses}`,
                };
            },
        }));
    </script>
    @endscript
</x-filament-panels::page>
