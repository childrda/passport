<?php

namespace App\Services\Google;

use App\Exceptions\DirectoryApiException;
use Google\Client as GoogleClient;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * Builds a Google API client for the **student** Workspace tenant Directory API.
 *
 * Staff-tenant Classroom access uses teacher OAuth via {@see GoogleClientFactory}.
 * Domain-wide delegation here must be authorized in the student tenant
 * (e.g. k12louisa.org), with an impersonated admin that exists in that tenant.
 */
class StudentDirectoryClientFactory
{
    /**
     * Build a Directory-capable client using student-tenant domain-wide delegation.
     *
     * @throws DirectoryApiException
     */
    public function makeDirectoryClient(): GoogleClient
    {
        $credentialsPath = $this->resolvedCredentialsPath();
        $impersonatedAdmin = trim((string) config('reset.google.directory.impersonated_admin'));

        if ($impersonatedAdmin === '') {
            throw DirectoryApiException::missingConfiguration(
                'Set GOOGLE_DIRECTORY_IMPERSONATED_ADMIN to a student-tenant Workspace admin email.'
            );
        }

        $this->assertImpersonatedAdminOnStudentDomain($impersonatedAdmin);

        if ($credentialsPath === null) {
            throw DirectoryApiException::missingConfiguration(
                'Set GOOGLE_DIRECTORY_CREDENTIALS to the student-tenant service-account JSON key path.'
            );
        }

        if (! File::isReadable($credentialsPath)) {
            throw DirectoryApiException::credentialsUnreadable($credentialsPath);
        }

        $client = new GoogleClient;
        $client->setAuthConfig($credentialsPath);
        $client->setSubject($impersonatedAdmin);
        $client->setScopes([
            'https://www.googleapis.com/auth/admin.directory.user',
        ]);
        $client->setAccessType('offline');

        return $client;
    }

    public function resolvedCredentialsPath(): ?string
    {
        $configured = trim((string) config('reset.google.directory.credentials'));

        if ($configured === '') {
            return null;
        }

        if (File::exists($configured)) {
            return $configured;
        }

        $fromBase = base_path($configured);
        if (File::exists($fromBase)) {
            return $fromBase;
        }

        $fromStorage = storage_path($configured);
        if (File::exists($fromStorage)) {
            return $fromStorage;
        }

        return $configured;
    }

    /**
     * @throws DirectoryApiException
     */
    public function assertImpersonatedAdminOnStudentDomain(string $impersonatedAdmin): void
    {
        $domain = Str::lower(Str::afterLast($impersonatedAdmin, '@'));
        $studentDomain = Str::lower((string) config('reset.student_domain'));

        if ($domain === '' || $domain !== $studentDomain) {
            throw DirectoryApiException::missingConfiguration(
                'GOOGLE_DIRECTORY_IMPERSONATED_ADMIN must be an admin on the student domain ('.
                config('reset.student_domain').'), not the staff tenant.'
            );
        }
    }
}
