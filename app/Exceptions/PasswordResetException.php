<?php

namespace App\Exceptions;

use App\Enums\AuditFailureCode;
use Exception;
use Throwable;

class PasswordResetException extends Exception
{
    public function __construct(
        string $message,
        public readonly AuditFailureCode $failureCode,
        public readonly bool $allowsRetry = true,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public static function unauthorized(): self
    {
        return new self(
            'This student is not enrolled in your class, or you are not a teacher of this course.',
            AuditFailureCode::NotOnRoster,
            allowsRetry: false,
        );
    }

    public static function directoryLookupFailed(): self
    {
        return new self(
            'Unable to look up the student account in Google Directory. The reset was denied.',
            AuditFailureCode::DirectoryLookupFailed,
            allowsRetry: true,
        );
    }

    public static function staffAccountNotAllowed(): self
    {
        return new self(
            'Staff accounts cannot have their passwords reset through this application.',
            AuditFailureCode::StaffAccount,
            allowsRetry: false,
        );
    }

    public static function studentDomainRequired(string $studentDomain): self
    {
        return new self(
            "Only accounts on @{$studentDomain} can be reset.",
            AuditFailureCode::WrongStudentDomain,
            allowsRetry: false,
        );
    }

    public static function classroomVerificationFailed(): self
    {
        return new self(
            'Unable to verify class enrollment with Google Classroom. The reset was denied.',
            AuditFailureCode::ClassroomVerificationFailed,
            allowsRetry: true,
        );
    }

    public static function resetFailed(): self
    {
        return new self(
            'The password reset could not be completed. Please try again.',
            AuditFailureCode::DirectoryConfirmedFailure,
            allowsRetry: true,
        );
    }

    public static function resetOutcomeUnknown(): self
    {
        return new self(
            'We could not confirm whether Google completed the reset. Do not retry yet — contact Technology Support.',
            AuditFailureCode::DirectoryTimeoutUnknown,
            allowsRetry: false,
        );
    }

    public static function resetInProgress(): self
    {
        return new self(
            'Another password reset is already in progress for this student. Please wait a moment and check with your colleagues before trying again.',
            AuditFailureCode::ResetInProgress,
            allowsRetry: true,
        );
    }

    public static function resetAccessDenied(): self
    {
        return new self(
            'Your account is not enabled to reset student passwords. Contact a System Administrator.',
            AuditFailureCode::ResetAccessDenied,
            allowsRetry: false,
        );
    }
}
