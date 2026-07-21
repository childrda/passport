<?php

namespace App\Filament\Pages;

use App\Contracts\ClassroomService;
use App\Enums\RoleName;
use App\Exceptions\ClassroomApiException;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use UnitEnum;

class MyClasses extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAcademicCap;

    protected static ?string $navigationLabel = 'My Classes';

    protected static ?string $title = 'My Classes';

    protected static ?string $slug = 'classes';

    protected static ?int $navigationSort = -1;

    protected static string|UnitEnum|null $navigationGroup = null;

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
        return 'Select a class to view enrolled students.';
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

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                EmbeddedTable::make(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->records(function (): array {
                /** @var User $user */
                $user = auth()->user();

                try {
                    $courses = app(ClassroomService::class)->coursesForTeacher($user);
                } catch (ClassroomApiException $e) {
                    Notification::make()
                        ->title('Unable to load classes')
                        ->body($e->getMessage())
                        ->danger()
                        ->send();

                    return [];
                }

                return $courses
                    ->mapWithKeys(fn ($course) => [
                        $course->id => [
                            'id' => $course->id,
                            'name' => $course->name,
                            'section' => $course->section,
                            'course_state' => $course->courseState,
                        ],
                    ])
                    ->all();
            })
            ->columns([
                TextColumn::make('name')
                    ->label('Class')
                    ->searchable(false)
                    ->sortable(false),
                TextColumn::make('section')
                    ->label('Section')
                    ->placeholder('—'),
            ])
            ->recordActions([
                Action::make('viewRoster')
                    ->label('View roster')
                    ->icon(Heroicon::OutlinedUsers)
                    ->url(fn (array $record): string => ClassRoster::getUrl(['courseId' => $record['id']])),
            ])
            ->paginated(false)
            ->emptyStateHeading('No classes found')
            ->emptyStateDescription('You do not currently teach any Google Classroom courses.')
            ->recordUrl(fn (array $record): string => ClassRoster::getUrl(['courseId' => $record['id']]));
    }
}
