<div
    class="space-y-4"
    x-data="{ copied: false }"
>
    <p class="text-sm text-gray-600 dark:text-gray-400">
        Temporary password for
        <span class="font-medium text-gray-950 dark:text-white">{{ $studentName }}</span>
        ({{ $studentEmail }}).
        Copy it now — it cannot be shown again after you close this dialog.
    </p>

    <div class="flex items-center gap-2 rounded-lg bg-gray-50 px-3 py-3 ring-1 ring-gray-950/10 dark:bg-white/5 dark:ring-white/10">
        <code
            class="flex-1 select-all font-mono text-lg tracking-wider text-gray-950 dark:text-white"
            x-ref="password"
        >{{ $password }}</code>
        <button
            type="button"
            class="fi-btn relative grid-flow-col items-center justify-center gap-1.5 rounded-lg px-3 py-2 text-sm font-semibold outline-none transition duration-75 bg-primary-600 text-white hover:bg-primary-500"
            x-on:click="
                navigator.clipboard.writeText($refs.password.innerText);
                copied = true;
                setTimeout(() => copied = false, 2000);
            "
        >
            <span x-show="! copied">Copy</span>
            <span x-show="copied" x-cloak>Copied</span>
        </button>
    </div>

    <p class="text-xs text-gray-500 dark:text-gray-400">
        The student must change this password at their next Google sign-in.
    </p>
</div>
