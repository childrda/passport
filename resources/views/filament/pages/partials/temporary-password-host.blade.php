<div
    x-data="{
        open: false,
        password: '',
        studentName: '',
        studentEmail: '',
        copied: false,
        show(detail) {
            this.password = detail.password ?? ''
            this.studentName = detail.studentName ?? ''
            this.studentEmail = detail.studentEmail ?? ''
            this.copied = false
            this.open = true
        },
        dismiss() {
            this.open = false
            this.password = ''
            this.studentName = ''
            this.studentEmail = ''
            this.copied = false
        }
    }"
    x-on:passport-temp-password.window="show($event.detail)"
>
    <div
        x-show="open"
        x-cloak
        class="fixed inset-0 z-50 flex items-center justify-center p-4"
        role="dialog"
        aria-modal="true"
        aria-labelledby="passport-temp-password-title"
    >
        <div
            class="absolute inset-0 bg-gray-950/50"
            x-on:click.prevent
        ></div>

        <div class="relative w-full max-w-lg rounded-xl bg-white p-6 shadow-xl ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <h2
                id="passport-temp-password-title"
                class="text-base font-semibold text-gray-950 dark:text-white"
            >
                Temporary password
            </h2>

            <div class="mt-4 space-y-4">
                <p class="text-sm text-gray-600 dark:text-gray-400">
                    Temporary password for
                    <span class="font-medium text-gray-950 dark:text-white" x-text="studentName"></span>
                    (<span>(</span><span x-text="studentEmail"></span><span>)</span>.
                    Copy it now — it cannot be shown again after you close this dialog.
                </p>

                <div class="flex items-center gap-2 rounded-lg bg-gray-50 px-3 py-3 ring-1 ring-gray-950/10 dark:bg-white/5 dark:ring-white/10">
                    <code
                        class="flex-1 select-all font-mono text-lg tracking-wider text-gray-950 dark:text-white"
                        x-ref="password"
                        x-text="password"
                    ></code>
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

            <div class="mt-6 flex justify-end">
                <button
                    type="button"
                    class="fi-btn relative grid-flow-col items-center justify-center gap-1.5 rounded-lg px-3 py-2 text-sm font-semibold outline-none transition duration-75 bg-gray-950 text-white hover:bg-gray-800 dark:bg-white dark:text-gray-950"
                    x-on:click="dismiss()"
                >
                    Done
                </button>
            </div>
        </div>
    </div>
</div>
