<?php

namespace App\Filament\Pages;

use App\Contracts\ClassroomService;
use App\DataTransferObjects\ClassroomCourse;
use App\Enums\RoleName;
use App\Exceptions\ClassroomApiException;
use App\Exceptions\PasswordResetException;
use App\Models\User;
use App\Services\StudentPasswordResetService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Panel;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Locked;

class ClassRoster extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static ?string $slug = 'class-roster';

    protected static bool $shouldRegisterNavigation = false;

    #[Locked]
    public string $courseId = '';

    public ?string $courseName = null;

    public ?string $courseSection = null;

    public static function getRoutePath(Panel $panel): string
    {
        return '/classes/{courseId}';
    }

    public static function getRelativeRouteName(Panel $panel): string
    {
        return 'class-roster';
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

    public function mount(string $courseId): void
    {
        /** @var User $user */
        $user = auth()->user();
        $classroom = app(ClassroomService::class);

        try {
            if (! $classroom->teacherTeachesCourse($user, $courseId)) {
                Notification::make()
                    ->title('Class not found')
                    ->body('You do not teach this class, or it is no longer available.')
                    ->danger()
                    ->send();

                $this->redirect(MyClasses::getUrl());

                return;
            }
        } catch (ClassroomApiException $e) {
            Notification::make()
                ->title('Unable to verify class')
                ->body($e->getMessage())
                ->danger()
                ->send();

            $this->redirect(MyClasses::getUrl());

            return;
        }

        $this->courseId = $courseId;

        try {
            /** @var ClassroomCourse|null $course */
            $course = $classroom->coursesForTeacher($user)
                ->first(fn (ClassroomCourse $item): bool => $item->id === $courseId);

            $this->courseName = $course?->name;
            $this->courseSection = $course?->section;
        } catch (ClassroomApiException) {
            $this->courseName = null;
            $this->courseSection = null;
        }
    }

    public function getTitle(): string|Htmlable
    {
        return $this->courseName ?? 'Class roster';
    }

    public function getSubheading(): string|Htmlable|null
    {
        $parts = array_filter([
            $this->courseSection,
            'Students currently enrolled in this class',
        ]);

        return implode(' · ', $parts);
    }

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('backToClasses')
                ->label('My Classes')
                ->icon(Heroicon::OutlinedArrowLeft)
                ->color('gray')
                ->url(MyClasses::getUrl()),
        ];
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
                if ($this->courseId === '') {
                    return [];
                }

                /** @var User $user */
                $user = auth()->user();

                try {
                    $students = app(ClassroomService::class)
                        ->studentsForCourse($user, $this->courseId);
                } catch (ClassroomApiException $e) {
                    Notification::make()
                        ->title('Unable to load roster')
                        ->body($e->getMessage())
                        ->danger()
                        ->send();

                    return [];
                }

                return $students
                    ->mapWithKeys(fn ($student) => [
                        $student->googleUserId => [
                            'id' => $student->googleUserId,
                            'full_name' => $student->fullName,
                            'email' => $student->email,
                            'google_user_id' => $student->googleUserId,
                        ],
                    ])
                    ->all();
            })
            ->columns([
                TextColumn::make('full_name')
                    ->label('Student')
                    ->searchable(false)
                    ->sortable(false),
                TextColumn::make('email')
                    ->label('Email')
                    ->copyable()
                    ->copyMessage('Email copied'),
            ])
            ->recordActions([
                Action::make('resetPassword')
                    ->label('Reset Password')
                    ->icon(Heroicon::OutlinedKey)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading(fn (array $record): string => 'Reset password for '.$record['full_name'].'?')
                    ->modalDescription(
                        'A temporary password will be generated and shown once. '.
                        'The student must change it at their next Google sign-in.'
                    )
                    ->modalSubmitActionLabel('Reset Password')
                    ->action(function (array $record, Action $action): void {
                        $this->confirmResetPassword($record);
                        // Keep the one-time password modal mounted after this action finishes.
                        $action->halt();
                    }),
            ])
            ->paginated(false)
            ->emptyStateHeading('No students enrolled')
            ->emptyStateDescription('This class does not currently have any students.');
    }

    /**
     * One-time password display. Mounted via replaceMountedAction with arguments only;
     * dismissing or refreshing clears them. Not registered as a visible header button.
     */
    public function showTemporaryPasswordAction(): Action
    {
        return Action::make('showTemporaryPassword')
            ->modalHeading('Temporary password')
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Done')
            ->closeModalByClickingAway(false)
            ->modalContent(function (Action $action) {
                $arguments = $action->getArguments();

                return view('filament.modals.temporary-password', [
                    'password' => $arguments['temporaryPassword'] ?? '',
                    'studentName' => $arguments['studentName'] ?? '',
                    'studentEmail' => $arguments['studentEmail'] ?? '',
                ]);
            });
    }

    /**
     * @param  array{full_name: string, email: string, google_user_id: string}  $record
     */
    public function confirmResetPassword(array $record): void
    {
        /** @var User $user */
        $user = auth()->user();

        try {
            $result = app(StudentPasswordResetService::class)->reset(
                $user,
                $this->courseId,
                $record['google_user_id'],
            );
        } catch (PasswordResetException $e) {
            Notification::make()
                ->title('Unable to reset password')
                ->body($e->getMessage())
                ->danger()
                ->send();

            return;
        } catch (\Throwable $e) {
            report($e);

            Notification::make()
                ->title('Unable to reset password')
                ->body('An unexpected error occurred. The reset was denied.')
                ->danger()
                ->send();

            return;
        }

        // Password is passed only into ephemeral mounted action arguments for this response.
        $this->replaceMountedAction('showTemporaryPassword', [
            'temporaryPassword' => $result->temporaryPassword,
            'studentName' => $result->studentName,
            'studentEmail' => $result->studentEmail,
        ]);

        // Ensure we never write the password to application logs.
        Log::info('Student password reset completed', [
            'teacher_id' => $user->id,
            'course_id' => $this->courseId,
            'directory_user_id' => $result->directoryUserId,
            'student_email' => $result->studentEmail,
            'change_password_at_next_login' => $result->changePasswordAtNextLogin,
        ]);
    }
}
