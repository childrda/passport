<?php

namespace App\Services\Google;

use App\Exceptions\DirectoryApiException;
use Google\Client as GoogleClient;
use Illuminate\Support\Facades\File;

class GoogleServiceAccountClientFactory
{
    /**
     * Build a Directory-capable client using domain-wide delegation.
     *
     * @throws DirectoryApiException
     */
    public function makeDirectoryClient(): GoogleClient
    {
        $credentialsPath = $this->resolvedCredentialsPath();
        $impersonatedAdmin = trim((string) config('reset.google.impersonated_admin'));

        if ($impersonatedAdmin === '') {
            throw DirectoryApiException::missingConfiguration(
                'Set GOOGLE_IMPERSONATED_ADMIN to a Workspace admin email.'
            );
        }

        if ($credentialsPath === null) {
            throw DirectoryApiException::missingConfiguration(
                'Set GOOGLE_SERVICE_ACCOUNT_CREDENTIALS to the service-account JSON key path.'
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
        $configured = trim((string) config('reset.google.service_account_credentials'));

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
}
