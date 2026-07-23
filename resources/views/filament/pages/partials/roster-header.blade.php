@php($accent = $this->courseAccent())

<nav class="passport-breadcrumb" aria-label="Breadcrumb">
    <a href="{{ \App\Filament\Pages\MyClasses::getUrl() }}" wire:navigate>My Classes</a>
    <span aria-hidden="true">›</span>
    <span class="text-gray-700 dark:text-gray-300">{{ $this->courseName ?? 'Class roster' }}</span>
</nav>

<div class="passport-roster-header">
    <div class="passport-roster-header__left">
        <span
            class="passport-class-card__icon"
            style="background: {{ $accent['bg'] }}; color: {{ $accent['text'] }};"
            aria-hidden="true"
        >
            {{ \App\Support\CourseAccent::initials($this->courseName ?? 'C') }}
        </span>
        <div class="min-w-0">
            <h2 class="truncate text-xl font-semibold text-gray-950 dark:text-white">
                {{ $this->courseName ?? 'Class roster' }}
            </h2>
            @if ($this->courseSection)
                <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">
                    {{ $this->courseSection }}
                </p>
            @endif
        </div>
    </div>
</div>
