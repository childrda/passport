<?php

namespace App\Filament\Pages;

use App\Contracts\ClassroomService;
use App\DataTransferObjects\ClassroomCourse;
use App\Enums\RoleName;
use App\Exceptions\ClassroomApiException;
use App\Models\User;
use App\Support\CourseAccent;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use UnitEnum;

class MyClasses extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-academic-cap';

    protected static ?string $navigationLabel = 'My Classes';

    protected static ?string $title = 'My Classes';

    protected static ?string $slug = 'classes';

    protected static ?int $navigationSort = -1;

    protected static string|UnitEnum|null $navigationGroup = null;

    protected string $view = 'filament.pages.my-classes';

    /**
     * Plain arrays only — Livewire cannot dehydrate ClassroomCourse DTOs.
     *
     * @var list<array{id: string, name: string, section: ?string}>
     */
    public array $courses = [];

    public ?string $loadError = null;

    public static function getNavigationLabel(): string
    {
        return 'My Classes';
    }

    public function getTitle(): string|Htmlable
    {
        return 'My Classes';
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'These are the Google Classroom classes you currently teach.';
    }

    public static function canAccess(): bool
    {
        /** @var User|null $user */
        $user = auth()->user();

        return $user !== null && (
            $user->hasRole(RoleName::Teacher)
            || $user->hasRole(RoleName::SystemAdministrator)
        );
    }

    public function mount(): void
    {
        /** @var User $user */
        $user = auth()->user();

        try {
            $this->courses = app(ClassroomService::class)
                ->coursesForTeacher($user)
                ->map(fn (ClassroomCourse $course): array => [
                    'id' => $course->id,
                    'name' => $course->name,
                    'section' => $course->section,
                ])
                ->values()
                ->all();
            $this->loadError = null;
        } catch (ClassroomApiException $e) {
            $this->courses = [];
            $this->loadError = $e->getMessage();

            Notification::make()
                ->title('Unable to load classes')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    /**
     * @return array{bg: string, text: string, initials: string}
     */
    public function accentFor(string $courseId): array
    {
        return CourseAccent::for($courseId);
    }

    public function courseInitials(string $name): string
    {
        return CourseAccent::initials($name);
    }

    public function rosterUrl(string $courseId): string
    {
        return ClassRoster::getUrl(['courseId' => $courseId]);
    }
}
