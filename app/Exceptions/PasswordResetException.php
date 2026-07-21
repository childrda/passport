<?php

namespace App\Exceptions;

use Exception;

class PasswordResetException extends Exception
{
    public static function unauthorized(): self
    {
        return new self(
            'This student is not enrolled in your class, or you are not a teacher of this course.'
        );
    }

    public static function directoryLookupFailed(): self
    {
        return new self(
            'Unable to look up the student account in Google Directory. The reset was denied.'
        );
    }

    public static function staffAccountNotAllowed(): self
    {
        return new self(
            'Staff accounts cannot have their passwords reset through this application.'
        );
    }

    public static function studentDomainRequired(string $studentDomain): self
    {
        return new self(
            "Only accounts on @{$studentDomain} can be reset."
        );
    }

    public static function classroomVerificationFailed(): self
    {
        return new self(
            'Unable to verify class enrollment with Google Classroom. The reset was denied.'
        );
    }

    public static function resetFailed(): self
    {
        return new self(
            'The password reset could not be completed. Please try again.'
        );
    }
}
