<div class="passport-confirm-warning">
    <div class="flex items-start gap-3">
        <div class="mt-0.5 flex h-9 w-9 items-center justify-center rounded-full bg-amber-100 text-amber-700 dark:bg-amber-400/15 dark:text-amber-300">
            <x-filament::icon icon="heroicon-o-exclamation-triangle" class="h-5 w-5" />
        </div>
        <div class="min-w-0">
            <p class="text-sm font-medium text-gray-950 dark:text-white">
                You are about to reset the password for:
            </p>
            <div class="passport-confirm-chip mt-3">
                <span class="passport-avatar">{{ $initials }}</span>
                <span class="min-w-0">
                    <span class="block font-semibold text-gray-950 dark:text-white">{{ $studentName }}</span>
                    <span class="block truncate text-sm text-gray-500 dark:text-gray-400">{{ $studentEmail }}</span>
                </span>
            </div>
        </div>
    </div>

    <div class="passport-confirm-amber">
        A temporary password will be generated and shown once. The student must change it at their next Google sign-in.
    </div>

    <div
        wire:loading.flex
        wire:target="callMountedTableAction"
        class="items-center justify-center gap-3 rounded-xl border border-sky-200 bg-sky-50 px-4 py-5 text-sky-800 dark:border-sky-400/30 dark:bg-sky-400/10 dark:text-sky-200"
    >
        <x-filament::loading-indicator class="h-6 w-6" />
        <div class="text-left">
            <p class="font-semibold">Resetting Password</p>
            <p class="text-sm opacity-80">This may take a few seconds.</p>
        </div>
    </div>
</div>
