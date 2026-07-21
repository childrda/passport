<?php

namespace App\Services;

use App\Contracts\ClassroomService;
use App\Contracts\DirectoryService;
use App\DataTransferObjects\PasswordResetResult;
use App\Enums\AuditResult;
use App\Exceptions\ClassroomApiException;
use App\Exceptions\DirectoryApiException;
use App\Exceptions\PasswordResetException;
use App\Models\User;
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
        $courseName = $this->resolveCourseName($teacher, $courseId);
        $studentDirectoryUserId = null;
        $studentEmail = null;
        $studentName = null;

        try {
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

            $temporaryPassword = $this->passwordGenerator->generate();

            try {
                $updatedUser = $this->directory->resetPassword(
                    $directoryUser->id,
                    $temporaryPassword,
                    changePasswordAtNextLogin: true,
                );
            } catch (DirectoryApiException) {
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
            ]);

            return new PasswordResetResult(
                temporaryPassword: $temporaryPassword,
                studentName: $updatedUser->fullName,
                studentEmail: $updatedUser->primaryEmail,
                directoryUserId: $updatedUser->id,
                changePasswordAtNextLogin: $updatedUser->changePasswordAtNextLogin,
            );
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
}
