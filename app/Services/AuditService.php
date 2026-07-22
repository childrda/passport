<?php

namespace App\Services;

use App\Enums\AuditFailureCode;
use App\Enums\AuditResult;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Str;

class AuditService
{
    /**
     * Record a password-reset attempt. Never accepts or stores a temporary password.
     *
     * @param  array{
     *     teacher: User,
     *     courseId: string,
     *     courseName?: ?string,
     *     studentGoogleUserId: string,
     *     studentDirectoryUserId?: ?string,
     *     studentEmail?: ?string,
     *     rosterEmail?: ?string,
     *     studentName?: ?string,
     *     result: AuditResult,
     *     failureReason?: ?string,
     *     failureCode?: AuditFailureCode|string|null,
     *     correlationId?: ?string,
     *     ipAddress?: ?string,
     *     userAgent?: ?string,
     * }  $data
     */
    public function recordResetAttempt(array $data): AuditLog
    {
        $failureCode = $data['failureCode'] ?? null;
        if ($failureCode instanceof AuditFailureCode) {
            $failureCode = $failureCode->value;
        }

        return AuditLog::query()->create([
            'teacher_user_id' => $data['teacher']->id,
            'teacher_email' => $data['teacher']->email,
            'teacher_name' => $data['teacher']->name,
            'student_google_user_id' => $data['studentGoogleUserId'],
            'student_directory_user_id' => $data['studentDirectoryUserId'] ?? null,
            'student_email' => $data['studentEmail'] ?? null,
            'roster_email' => $data['rosterEmail'] ?? null,
            'student_name' => $data['studentName'] ?? null,
            'course_id' => $data['courseId'],
            'course_name' => $data['courseName'] ?? null,
            'result' => $data['result']->value,
            'failure_reason' => $data['failureReason'] ?? null,
            'failure_code' => $failureCode,
            'ip_address' => $data['ipAddress'] ?? Request::ip(),
            'occurred_at_utc' => now('UTC'),
            'user_agent' => $data['userAgent'] ?? Request::userAgent(),
            'correlation_id' => $data['correlationId'] ?? (string) Str::uuid(),
        ]);
    }
}
