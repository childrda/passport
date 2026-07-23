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

        <div class="relative passport-temp-modal__panel">
            <div class="flex flex-col items-center text-center">
                <div class="mb-3 flex h-14 w-14 items-center justify-center rounded-full bg-emerald-100 text-emerald-600 dark:bg-emerald-400/15 dark:text-emerald-300">
                    <x-filament::icon icon="heroicon-o-check" class="h-8 w-8" />
                </div>

                <h2
                    id="passport-temp-password-title"
                    class="passport-temp-modal__title justify-center"
                >
                    Password Reset Successful!
                </h2>

                <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                    Temporary password for
                    <span class="font-medium text-gray-950 dark:text-white" x-text="studentName"></span>
                    (<span>(</span><span x-text="studentEmail"></span><span>)</span>
                </p>
            </div>

            <div class="passport-temp-modal__password">
                <code
                    class="select-all"
                    x-ref="password"
                    x-text="password"
                ></code>
                <button
                    type="button"
                    class="fi-btn relative grid-flow-col items-center justify-center gap-1.5 rounded-lg px-3 py-2 text-sm font-semibold outline-none transition duration-75 bg-primary-600 text-white hover:bg-primary-500 shrink-0"
                    x-on:click="
                        navigator.clipboard.writeText($refs.password.innerText);
                        copied = true;
                        setTimeout(() => copied = false, 2000);
                    "
                >
                    <span x-show="! copied" class="inline-flex items-center gap-1.5">
                        <x-filament::icon icon="heroicon-o-clipboard" class="h-4 w-4" />
                        Copy
                    </span>
                    <span x-show="copied" x-cloak class="inline-flex items-center gap-1.5">
                        <x-filament::icon icon="heroicon-o-check" class="h-4 w-4" />
                        Copied!
                    </span>
                </button>
            </div>

            <div class="passport-temp-modal__ok">
                The student will be required to change this password the next time they sign in.
            </div>

            <p class="mt-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400">
                This password will not be shown again.
            </p>

            <div class="mt-5 flex justify-center">
                <button
                    type="button"
                    class="fi-btn relative grid-flow-col items-center justify-center gap-1.5 rounded-lg px-4 py-2 text-sm font-semibold outline-none transition duration-75 bg-primary-600 text-white hover:bg-primary-500"
                    x-on:click="dismiss()"
                >
                    Close
                </button>
            </div>
        </div>
    </div>
</div>
