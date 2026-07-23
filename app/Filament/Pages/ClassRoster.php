<?php

namespace App\Filament\Pages;

use App\Contracts\ClassroomService;
use App\DataTransferObjects\ClassroomCourse;
use App\Enums\RoleName;
use App\Exceptions\ClassroomApiException;
use App\Exceptions\PasswordResetException;
use App\Models\User;
use App\Services\StudentPasswordResetService;
use App\Support\CourseAccent;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Panel;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\View as SchemaView;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
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

    public function getHeading(): string|Htmlable
    {
        // Custom roster header (breadcrumb + icon) replaces the default page heading.
        return new HtmlString('<span class="sr-only">'.e($this->courseName ?? 'Class roster').'</span>');
    }

    public function getSubheading(): string|Htmlable|null
    {
        return null;
    }

    /**
     * @return array{bg: string, text: string, initials: string}
     */
    public function courseAccent(): array
    {
        return CourseAccent::for($this->courseId !== '' ? $this->courseId : 'default');
    }

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('backToClasses')
                ->label('Back to My Classes')
                ->icon(Heroicon::OutlinedArrowLeft)
                ->color('gray')
                ->url(MyClasses::getUrl()),
        ];
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                SchemaView::make('filament.pages.partials.roster-header'),
                SchemaView::make('filament.pages.partials.temporary-password-host'),
                EmbeddedTable::make(),
                SchemaView::make('filament.pages.partials.roster-security-note'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->records(function (?string $search): array {
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

                $records = $students
                    ->mapWithKeys(fn ($student) => [
                        $student->googleUserId => [
                            'id' => $student->googleUserId,
                            'full_name' => $student->fullName,
                            'email' => $student->email,
                            'google_user_id' => $student->googleUserId,
                        ],
                    ]);

                $term = Str::lower(trim((string) $search));

                if ($term !== '') {
                    $records = $records->filter(function (array $record) use ($term): bool {
                        return str_contains(Str::lower($record['full_name']), $term)
                            || str_contains(Str::lower($record['email']), $term);
                    });
                }

                return $records->all();
            })
            ->columns([
                TextColumn::make('full_name')
                    ->label('Student')
                    ->html()
                    ->formatStateUsing(function (string $state): string {
                        $initials = e(CourseAccent::initials($state));
                        $name = e($state);

                        return <<<HTML
                            <div class="passport-student-cell">
                                <span class="passport-avatar">{$initials}</span>
                                <span class="font-medium text-gray-950 dark:text-white">{$name}</span>
                            </div>
                        HTML;
                    })
                    ->searchable(),
                TextColumn::make('email')
                    ->label('Email')
                    ->copyable()
                    ->copyMessage('Email copied')
                    ->searchable(),
            ])
            ->recordActions([
                Action::make('resetPassword')
                    ->label('Reset Password')
                    ->icon(Heroicon::OutlinedKey)
                    ->button()
                    ->color(fn (array $record): string => $this->rosterEmailIsOnStudentDomain($record['email'] ?? '')
                        ? 'primary'
                        : 'gray')
                    ->visible(fn (): bool => auth()->user()?->canResetStudentPasswords() ?? false)
                    ->disabled(fn (array $record): bool => ! $this->rosterEmailIsOnStudentDomain($record['email'] ?? ''))
                    ->tooltip(function (array $record): ?string {
                        if ($this->rosterEmailIsOnStudentDomain($record['email'] ?? '')) {
                            return null;
                        }

                        $domain = (string) config('reset.student_domain');

                        return "This account is not in the student domain (@{$domain}) and cannot have its password reset.";
                    })
                    ->requiresConfirmation()
                    ->modalHeading('Reset Password')
                    ->modalDescription(null)
                    ->modalContent(fn (array $record) => view('filament.modals.reset-confirm', [
                        'studentName' => $record['full_name'],
                        'studentEmail' => $record['email'],
                        'initials' => CourseAccent::initials($record['full_name']),
                    ]))
                    ->modalSubmitActionLabel('Yes, Reset Password')
                    ->modalSubmitAction(fn (Action $action): Action => $action
                        ->color('danger')
                        ->extraAttributes([
                            'wire:loading.attr' => 'disabled',
                            'x-data' => '{ submitted: false }',
                            'x-bind:disabled' => 'submitted',
                            'x-on:click' => 'submitted = true',
                        ]))
                    ->action(function (array $record): void {
                        $this->confirmResetPassword($record);
                    }),
            ])
            ->searchable()
            ->searchPlaceholder('Search students by name')
            ->paginated(false)
            ->emptyStateHeading('No students enrolled')
            ->emptyStateDescription('This class does not currently have any students.');
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
            $notification = Notification::make()
                ->title($e->allowsRetry ? 'Unable to reset password' : 'Reset not confirmed')
                ->body($e->getMessage())
                ->danger()
                ->icon($e->allowsRetry ? 'heroicon-o-exclamation-triangle' : 'heroicon-o-shield-exclamation');

            if (! $e->allowsRetry) {
                $notification->persistent();
            }

            $notification->send();

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

        // Password is sent only in this response's JS effect — never Livewire/component state.
        $payload = json_encode([
            'password' => $result->temporaryPassword,
            'studentName' => $result->studentName,
            'studentEmail' => $result->studentEmail,
        ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_THROW_ON_ERROR);

        $this->js(
            'window.dispatchEvent(new CustomEvent("passport-temp-password", { detail: '.$payload.' }))'
        );

        Log::info('Student password reset completed', [
            'teacher_id' => $user->id,
            'course_id' => $this->courseId,
            'directory_user_id' => $result->directoryUserId,
            'student_email' => $result->studentEmail,
            'change_password_at_next_login' => $result->changePasswordAtNextLogin,
        ]);
    }

    private function rosterEmailIsOnStudentDomain(string $email): bool
    {
        $normalized = Str::lower(trim($email));

        if ($normalized === '' || ! str_contains($normalized, '@')) {
            return false;
        }

        $domain = Str::lower(Str::afterLast($normalized, '@'));
        $studentDomain = Str::lower((string) config('reset.student_domain'));

        return $domain !== '' && $domain === $studentDomain;
    }
}
