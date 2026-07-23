<x-filament-panels::page>
    @if ($loadError)
        <div class="rounded-xl border border-danger-300 bg-danger-50 px-4 py-3 text-sm text-danger-700 dark:border-danger-400/40 dark:bg-danger-400/10 dark:text-danger-300">
            {{ $loadError }}
        </div>
    @elseif (count($courses) === 0)
        <div class="rounded-xl border border-dashed border-gray-300 bg-white px-6 py-12 text-center dark:border-white/15 dark:bg-white/5">
            <p class="text-base font-semibold text-gray-950 dark:text-white">No classes found</p>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                You do not currently teach any Google Classroom courses.
            </p>
        </div>
    @else
        <div class="passport-class-grid">
            @foreach ($courses as $course)
                @php($accent = $this->accentFor($course['id']))
                <a
                    href="{{ $this->rosterUrl($course['id']) }}"
                    class="passport-class-card"
                    wire:navigate
                >
                    <span
                        class="passport-class-card__icon"
                        style="background: {{ $accent['bg'] }}; color: {{ $accent['text'] }};"
                    >
                        {{ $this->courseInitials($course['name']) }}
                    </span>
                    <span class="passport-class-card__body">
                        <span class="passport-class-card__name">{{ $course['name'] }}</span>
                        <span class="passport-class-card__meta">
                            {{ $course['section'] ?: 'No section' }}
                        </span>
                    </span>
                    <span class="passport-class-card__chevron" aria-hidden="true">
                        <x-filament::icon icon="heroicon-m-chevron-right" class="h-5 w-5" />
                    </span>
                </a>
            @endforeach
        </div>
    @endif

    <p class="passport-attribution">Data is provided by Google Classroom.</p>
</x-filament-panels::page>
