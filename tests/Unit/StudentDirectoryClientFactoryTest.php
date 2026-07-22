<?php

namespace Tests\Unit;

use App\Exceptions\DirectoryApiException;
use App\Services\Google\StudentDirectoryClientFactory;
use App\Support\ResetConfiguration;
use Tests\TestCase;

class StudentDirectoryClientFactoryTest extends TestCase
{
    public function test_missing_impersonated_admin_is_rejected(): void
    {
        config([
            'reset.google.directory.credentials' => storage_path('app/fake-sa.json'),
            'reset.google.directory.impersonated_admin' => '',
        ]);

        $this->expectException(DirectoryApiException::class);
        $this->expectExceptionMessage('GOOGLE_DIRECTORY_IMPERSONATED_ADMIN');

        app(StudentDirectoryClientFactory::class)->makeDirectoryClient();
    }

    public function test_missing_credentials_path_is_rejected(): void
    {
        config([
            'reset.google.directory.credentials' => '',
            'reset.google.directory.impersonated_admin' => 'admin@k12louisa.org',
        ]);

        $this->expectException(DirectoryApiException::class);
        $this->expectExceptionMessage('GOOGLE_DIRECTORY_CREDENTIALS');

        app(StudentDirectoryClientFactory::class)->makeDirectoryClient();
    }

    public function test_unreadable_credentials_path_is_rejected(): void
    {
        config([
            'reset.google.directory.credentials' => storage_path('app/does-not-exist-sa.json'),
            'reset.google.directory.impersonated_admin' => 'admin@k12louisa.org',
        ]);

        $this->expectException(DirectoryApiException::class);
        $this->expectExceptionMessage('could not be read');

        app(StudentDirectoryClientFactory::class)->makeDirectoryClient();
    }

    public function test_staff_domain_impersonated_admin_is_rejected(): void
    {
        config([
            'reset.student_domain' => 'k12louisa.org',
            'reset.staff_domain' => 'lcps.k12.va.us',
            'reset.google.directory.credentials' => storage_path('app/fake-sa.json'),
            'reset.google.directory.impersonated_admin' => 'admin@lcps.k12.va.us',
        ]);

        $this->expectException(DirectoryApiException::class);
        $this->expectExceptionMessage('student domain');

        app(StudentDirectoryClientFactory::class)->makeDirectoryClient();
    }

    public function test_startup_validation_rejects_staff_domain_directory_admin(): void
    {
        config([
            'reset.directory_driver' => 'mock',
            'reset.student_domain' => 'k12louisa.org',
            'reset.staff_domain' => 'lcps.k12.va.us',
            'reset.google.directory.impersonated_admin' => 'admin@lcps.k12.va.us',
            'reset.google.directory.credentials' => '',
        ]);

        $this->expectException(DirectoryApiException::class);
        $this->expectExceptionMessage('student domain');

        ResetConfiguration::validate();
    }
}
