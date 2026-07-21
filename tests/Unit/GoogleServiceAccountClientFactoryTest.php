<?php

namespace Tests\Unit;

use App\Exceptions\DirectoryApiException;
use App\Services\Google\GoogleServiceAccountClientFactory;
use Tests\TestCase;

class GoogleServiceAccountClientFactoryTest extends TestCase
{
    public function test_missing_impersonated_admin_is_rejected(): void
    {
        config([
            'reset.google.service_account_credentials' => storage_path('app/fake-sa.json'),
            'reset.google.impersonated_admin' => '',
        ]);

        $this->expectException(DirectoryApiException::class);
        $this->expectExceptionMessage('GOOGLE_IMPERSONATED_ADMIN');

        app(GoogleServiceAccountClientFactory::class)->makeDirectoryClient();
    }

    public function test_missing_credentials_path_is_rejected(): void
    {
        config([
            'reset.google.service_account_credentials' => '',
            'reset.google.impersonated_admin' => 'admin@lcps.k12.va.us',
        ]);

        $this->expectException(DirectoryApiException::class);
        $this->expectExceptionMessage('GOOGLE_SERVICE_ACCOUNT_CREDENTIALS');

        app(GoogleServiceAccountClientFactory::class)->makeDirectoryClient();
    }

    public function test_unreadable_credentials_path_is_rejected(): void
    {
        config([
            'reset.google.service_account_credentials' => storage_path('app/does-not-exist-sa.json'),
            'reset.google.impersonated_admin' => 'admin@lcps.k12.va.us',
        ]);

        $this->expectException(DirectoryApiException::class);
        $this->expectExceptionMessage('could not be read');

        app(GoogleServiceAccountClientFactory::class)->makeDirectoryClient();
    }
}
