@php($pendingActions = $selected?->pending_actions ?? [])

@if (! empty($pendingActions))
    <div class="space-y-2" data-testid="ai-pending-actions">
        @foreach ($pendingActions as $index => $action)
            <div class="rounded-xl border border-warning-300 bg-warning-50 p-3 text-sm dark:border-warning-500/40 dark:bg-warning-500/10" wire:key="pending-action-{{ $index }}">
                <p class="text-[11px] font-semibold text-warning-700 dark:text-warning-400">اقدام در انتظار تأیید شما</p>
                <p class="mt-1 text-gray-900 dark:text-gray-100">{{ $action['summary'] }}</p>
                <div class="mt-2 flex gap-2">
                    <button type="button" wire:click="confirmPendingAction({{ $index }})" wire:loading.attr="disabled" class="rounded-lg bg-primary-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-primary-500">تأیید و اجرا</button>
                    <button type="button" wire:click="cancelPendingAction({{ $index }})" wire:loading.attr="disabled" class="rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50 dark:border-white/10 dark:text-gray-200 dark:hover:bg-white/5">رد کردن</button>
                </div>
            </div>
        @endforeach
    </div>
@endif
