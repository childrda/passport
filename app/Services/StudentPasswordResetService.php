<?php

namespace App\Services;

use App\Contracts\ClassroomService;
use App\Contracts\DirectoryService;
use App\DataTransferObjects\PasswordResetResult;
use App\Enums\AuditFailureCode;
use App\Enums\AuditResult;
use App\Exceptions\ClassroomApiException;
use App\Exceptions\DirectoryApiException;
use App\Exceptions\PasswordResetException;
use App\Models\User;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class StudentPasswordResetService
{
    public function __construct(
        private readonly ClassroomService $classroom,
        private readonly DirectoryService $directory,
        private readonly TemporaryPasswordGenerator $passwordGenerator,
        private readonly AuditService $audit,
    ) {}

    /**
     * Coordinate roster verification, Directory lookup, domain checks, generation, and reset.
     * Returns the temporary password only to the caller for one-time display.
     * Every attempt is audited (never the temporary password).
     *
     * @throws PasswordResetException
     */
    public function reset(User $teacher, string $courseId, string $classroomStudentGoogleUserId): PasswordResetResult
    {
        $correlationId = (string) Str::uuid();
        $courseName = null;
        $studentDirectoryUserId = null;
        $studentEmail = null;
        $studentName = null;

        try {
            if (! Gate::forUser($teacher)->allows('reset-student-password')) {
                throw PasswordResetException::resetAccessDenied();
            }

            try {
                $teaches = $this->classroom->teacherTeachesCourse($teacher, $courseId);
                $enrolled = $this->classroom->studentEnrolledInCourse(
                    $teacher,
                    $courseId,
                    $classroomStudentGoogleUserId,
                );
            } catch (ClassroomApiException) {
                throw PasswordResetException::classroomVerificationFailed();
            }

            if (! $teaches || ! $enrolled) {
                throw PasswordResetException::unauthorized();
            }

            // Resolve course name only after authorization (audit convenience; may be null).
            $courseName = $this->resolveCourseName($teacher, $courseId);

            try {
                $directoryUser = $this->directory->findByClassroomUserId($classroomStudentGoogleUserId);
            } catch (DirectoryApiException) {
                throw PasswordResetException::directoryLookupFailed();
            }

            if ($directoryUser === null) {
                throw PasswordResetException::directoryLookupFailed();
            }

            $studentDirectoryUserId = $directoryUser->id;
            $studentEmail = $directoryUser->primaryEmail;
            $studentName = $directoryUser->fullName;

            $primaryEmail = Str::lower($directoryUser->primaryEmail);
            $emailDomain = Str::lower(Str::afterLast($primaryEmail, '@'));
            $studentDomain = Str::lower((string) config('reset.student_domain'));
            $staffDomain = Str::lower((string) config('reset.staff_domain'));

            if ($emailDomain === $staffDomain) {
                throw PasswordResetException::staffAccountNotAllowed();
            }

            if ($emailDomain !== $studentDomain) {
                throw PasswordResetException::studentDomainRequired((string) config('reset.student_domain'));
            }

            try {
                return Cache::lock("student-password-reset:{$directoryUser->id}", 15)
                    ->block(2, function () use (
                        $teacher,
                        $courseId,
                        $classroomStudentGoogleUserId,
                        $directoryUser,
                        &$courseName,
                        $correlationId,
                    ): PasswordResetResult {
                        // Re-validate enrollment inside the lock.
                        try {
                            $teaches = $this->classroom->teacherTeachesCourse($teacher, $courseId);
                            $enrolled = $this->classroom->studentEnrolledInCourse(
                                $teacher,
                                $courseId,
                                $classroomStudentGoogleUserId,
                            );
                        } catch (ClassroomApiException) {
                            throw PasswordResetException::classroomVerificationFailed();
                        }

                        if (! $teaches || ! $enrolled) {
                            throw PasswordResetException::unauthorized();
                        }

                        $temporaryPassword = $this->passwordGenerator->generate();

                        try {
                            $updatedUser = $this->directory->resetPassword(
                                $directoryUser->id,
                                $temporaryPassword,
                                changePasswordAtNextLogin: true,
                            );
                        } catch (DirectoryApiException $e) {
                            if ($e->outcomeUnknown) {
                                throw PasswordResetException::resetOutcomeUnknown();
                            }

                            throw PasswordResetException::resetFailed();
                        }

                        $this->audit->recordResetAttempt([
                            'teacher' => $teacher,
                            'courseId' => $courseId,
                            'courseName' => $courseName,
                            'studentGoogleUserId' => $classroomStudentGoogleUserId,
                            'studentDirectoryUserId' => $updatedUser->id,
                            'studentEmail' => $updatedUser->primaryEmail,
                            'studentName' => $updatedUser->fullName,
                            'result' => AuditResult::Success,
                            'correlationId' => $correlationId,
                        ]);

                        return new PasswordResetResult(
                            temporaryPassword: $temporaryPassword,
                            studentName: $updatedUser->fullName,
                            studentEmail: $updatedUser->primaryEmail,
                            directoryUserId: $updatedUser->id,
                            changePasswordAtNextLogin: $updatedUser->changePasswordAtNextLogin,
                        );
                    });
            } catch (LockTimeoutException) {
                throw PasswordResetException::resetInProgress();
            }
        } catch (PasswordResetException $e) {
            $this->audit->recordResetAttempt([
                'teacher' => $teacher,
                'courseId' => $courseId,
                'courseName' => $courseName,
                'studentGoogleUserId' => $classroomStudentGoogleUserId,
                'studentDirectoryUserId' => $studentDirectoryUserId,
                'studentEmail' => $studentEmail,
                'studentName' => $studentName,
                'result' => AuditResult::Failure,
                'failureReason' => $e->getMessage(),
                'failureCode' => $e->failureCode,
                'correlationId' => $correlationId,
            ]);

            throw $e;
        } catch (Throwable $e) {
            $this->audit->recordResetAttempt([
                'teacher' => $teacher,
                'courseId' => $courseId,
                'courseName' => $courseName,
                'studentGoogleUserId' => $classroomStudentGoogleUserId,
                'studentDirectoryUserId' => $studentDirectoryUserId,
                'studentEmail' => $studentEmail,
                'studentName' => $studentName,
                'result' => AuditResult::Failure,
                'failureReason' => 'An unexpected error occurred. The reset was denied.',
                'failureCode' => AuditFailureCode::Unexpected,
                'correlationId' => $correlationId,
            ]);

            Log::warning('Unexpected password reset failure', [
                'correlation_id' => $correlationId,
                'exception_class' => $e::class,
                'exception_message' => $this->sanitizeExceptionMessage($e->getMessage()),
            ]);

            throw $e;
        }
    }

    private function resolveCourseName(User $teacher, string $courseId): ?string
    {
        try {
            return $this->classroom->coursesForTeacher($teacher)
                ->firstWhere('id', $courseId)
                ?->name;
        } catch (Throwable) {
            return null;
        }
    }

    private function sanitizeExceptionMessage(string $message): string
    {
        // Strip likely secrets / tokens from diagnostic logs.
        $message = preg_replace('/Bearer\s+\S+/i', 'Bearer [redacted]', $message) ?? $message;
        $message = preg_replace('/password["\s:=]+\S+/i', 'password=[redacted]', $message) ?? $message;

        return Str::limit($message, 500);
    }
}
