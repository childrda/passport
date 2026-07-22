<?php

namespace App\Support;

use App\Exceptions\DirectoryApiException;
use App\Services\Google\StudentDirectoryClientFactory;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Startup validation for password-reset / two-tenant Google configuration.
 */
class ResetConfiguration
{
    public static function validate(): void
    {
        $studentDomain = Str::lower(trim((string) config('reset.student_domain')));
        $staffDomain = Str::lower(trim((string) config('reset.staff_domain')));

        if ($studentDomain === '' || $staffDomain === '') {
            throw new InvalidArgumentException(
                'STAFF_DOMAIN and STUDENT_DOMAIN must both be configured.'
            );
        }

        if ($studentDomain === $staffDomain) {
            throw new InvalidArgumentException(
                'STAFF_DOMAIN and STUDENT_DOMAIN must be different Workspace tenants.'
            );
        }

        $impersonatedAdmin = trim((string) config('reset.google.directory.impersonated_admin'));
        $credentials = trim((string) config('reset.google.directory.credentials'));
        $directoryDriver = (string) config('reset.directory_driver', 'mock');

        if ($impersonatedAdmin !== '') {
            app(StudentDirectoryClientFactory::class)
                ->assertImpersonatedAdminOnStudentDomain($impersonatedAdmin);
        }

        if ($directoryDriver === 'google') {
            if ($impersonatedAdmin === '') {
                throw DirectoryApiException::missingConfiguration(
                    'DIRECTORY_DRIVER=google requires GOOGLE_DIRECTORY_IMPERSONATED_ADMIN '.
                    '(student-tenant admin email).'
                );
            }

            if ($credentials === '') {
                throw DirectoryApiException::missingConfiguration(
                    'DIRECTORY_DRIVER=google requires GOOGLE_DIRECTORY_CREDENTIALS '.
                    '(student-tenant service-account JSON path).'
                );
            }
        }
    }
}
