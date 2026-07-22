<?php

namespace App\Enums;

enum AuditFailureCode: string
{
    case NotOnRoster = 'not_on_roster';
    case StaffAccount = 'staff_account';
    case WrongStudentDomain = 'wrong_student_domain';
    case DirectoryLookupFailed = 'directory_lookup_failed';
    case ClassroomVerificationFailed = 'classroom_verification_failed';
    case DirectoryConfirmedFailure = 'directory_confirmed_failure';
    case DirectoryTimeoutUnknown = 'directory_timeout_unknown';
    case ResetInProgress = 'reset_in_progress';
    case ResetAccessDenied = 'reset_access_denied';
    case StudentNotInDirectory = 'student_not_in_directory';
    case Unexpected = 'unexpected';

    public function label(): string
    {
        return match ($this) {
            self::NotOnRoster => 'Not on roster',
            self::StaffAccount => 'Staff account',
            self::WrongStudentDomain => 'Wrong student domain',
            self::DirectoryLookupFailed => 'Directory lookup failed',
            self::ClassroomVerificationFailed => 'Classroom verification failed',
            self::DirectoryConfirmedFailure => 'Directory confirmed failure',
            self::DirectoryTimeoutUnknown => 'Directory outcome unknown',
            self::ResetInProgress => 'Reset in progress',
            self::ResetAccessDenied => 'Reset access denied',
            self::StudentNotInDirectory => 'Student not in directory',
            self::Unexpected => 'Unexpected error',
        };
    }
}
