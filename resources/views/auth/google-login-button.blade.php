<div class="mt-6">
    <div class="relative mb-4">
        <div class="absolute inset-0 flex items-center" aria-hidden="true">
            <div class="w-full border-t border-gray-200 dark:border-white/10"></div>
        </div>
        <div class="relative flex justify-center text-sm">
            <span class="bg-white px-2 text-gray-500 dark:bg-gray-950 dark:text-gray-400">
                or
            </span>
        </div>
    </div>

    @if (session('google_auth_error'))
        <div
            class="mb-4 rounded-lg border border-danger-300 bg-danger-50 px-3 py-2 text-sm text-danger-600 dark:border-danger-500/40 dark:bg-danger-500/10 dark:text-danger-400"
            role="alert"
        >
            {{ session('google_auth_error') }}
        </div>
    @endif

    <a
        href="{{ route('auth.google.redirect') }}"
        class="fi-btn relative grid-flow-col items-center justify-center gap-1.5 rounded-lg font-semibold outline-none transition duration-75 focus-visible:ring-2 dark:focus-visible:ring-offset-0 fi-size-md fi-btn-size-md gap-1.5 px-3 py-2 text-sm inline-grid w-full shadow-sm bg-white text-gray-950 hover:bg-gray-50 dark:bg-white/5 dark:text-white dark:hover:bg-white/10 ring-1 ring-gray-950/10 dark:ring-white/20"
    >
        <svg class="h-5 w-5" viewBox="0 0 24 24" aria-hidden="true">
            <path fill="#EA4335" d="M12 10.2v3.6h5.1c-.2 1.2-.9 2.2-1.9 2.9l3.1 2.4c1.8-1.7 2.9-4.1 2.9-7 0-.7-.1-1.3-.2-1.9H12z"/>
            <path fill="#34A853" d="M6.6 14.3l-.5.4-2.3 1.8C5.5 19.3 8.5 21 12 21c2.4 0 4.4-.8 5.9-2.1l-3.1-2.4c-.8.6-1.9.9-2.8.9-2.2 0-4-1.5-4.7-3.5z"/>
            <path fill="#4A90E2" d="M3.8 7.5C3.3 8.5 3 9.7 3 11s.3 2.5.8 3.5l2.8-2.2c-.2-.5-.3-1.1-.3-1.3 0-.5.1-1 .3-1.4L3.8 7.5z"/>
            <path fill="#FBBC05" d="M12 5.3c1.3 0 2.5.5 3.4 1.3l2.6-2.6C16.4 2.6 14.4 2 12 2 8.5 2 5.5 3.7 3.8 6.5l2.8 2.2C7.9 6.8 9.8 5.3 12 5.3z"/>
        </svg>
        <span>Sign in with Google</span>
    </a>

    <p class="mt-3 text-center text-xs text-gray-500 dark:text-gray-400">
        Use your {{ config('reset.staff_domain') }} account.
    </p>
</div>
