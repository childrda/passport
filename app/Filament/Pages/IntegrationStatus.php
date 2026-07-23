<?php

namespace App\Filament\Pages;

use App\Enums\RoleName;
use App\Models\User;
use App\Services\Google\StudentDirectoryClientFactory;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use UnitEnum;

class IntegrationStatus extends Page
{
    protected string $view = 'filament.pages.integration-status';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSignal;

    protected static string|UnitEnum|null $navigationGroup = 'ADMIN';

    protected static ?string $navigationLabel = 'Integration status';

    protected static ?string $slug = 'integration-status';

    protected static ?int $navigationSort = 20;

    public static function canAccess(): bool
    {
        /** @var User|null $user */
        $user = auth()->user();

        return $user !== null && $user->hasRole(RoleName::SystemAdministrator);
    }

    public function getTitle(): string|Htmlable
    {
        return 'Integration status';
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'Environment-managed settings are read-only here. Secrets are never shown.';
    }

    /**
     * @return array<int, array{label: string, value: string, ok: bool}>
     */
    public function getStatusRows(): array
    {
        $oauthConfigured = filled(config('reset.google.oauth.client_id'))
            && filled(config('reset.google.oauth.client_secret'))
            && filled(config('reset.google.oauth.redirect_uri'));

        $credentialsPath = app(StudentDirectoryClientFactory::class)->resolvedCredentialsPath();
        $credentialsReadable = filled($credentialsPath) && File::isReadable($credentialsPath);

        $impersonatedAdmin = trim((string) config('reset.google.directory.impersonated_admin'));
        $studentDomain = Str::lower((string) config('reset.student_domain'));
        $adminDomain = $impersonatedAdmin !== ''
            ? Str::lower(Str::afterLast($impersonatedAdmin, '@'))
            : '';
        $adminOnStudentDomain = $impersonatedAdmin !== '' && $adminDomain === $studentDomain;

        return [
            [
                'label' => 'Staff domain (Classroom / OAuth)',
                'value' => (string) config('reset.staff_domain'),
                'ok' => filled(config('reset.staff_domain')),
            ],
            [
                'label' => 'Student domain (Directory)',
                'value' => (string) config('reset.student_domain'),
                'ok' => filled(config('reset.student_domain')),
            ],
            [
                'label' => 'Classroom driver',
                'value' => (string) config('reset.classroom_driver'),
                'ok' => in_array(config('reset.classroom_driver'), ['mock', 'google'], true),
            ],
            [
                'label' => 'Directory driver',
                'value' => (string) config('reset.directory_driver'),
                'ok' => in_array(config('reset.directory_driver'), ['mock', 'google'], true),
            ],
            [
                'label' => 'Staff-tenant Google OAuth',
                'value' => $oauthConfigured ? 'Configured' : 'Missing client ID/secret/redirect',
                'ok' => $oauthConfigured,
            ],
            [
                'label' => 'Student-tenant Directory credentials',
                'value' => $credentialsReadable
                    ? 'Readable file configured'
                    : (filled($credentialsPath) ? 'Path set but not readable' : 'Not configured'),
                'ok' => $credentialsReadable || config('reset.directory_driver') === 'mock',
            ],
            [
                'label' => 'Student-tenant impersonated admin',
                'value' => match (true) {
                    $impersonatedAdmin === '' => 'Not configured',
                    $adminOnStudentDomain => 'Configured on student domain',
                    default => 'Configured but not on student domain',
                },
                'ok' => $adminOnStudentDomain || (
                    $impersonatedAdmin === '' && config('reset.directory_driver') === 'mock'
                ),
            ],
            [
                'label' => 'Temp password length',
                'value' => (string) config('reset.temp_password.length'),
                'ok' => (int) config('reset.temp_password.length') >= 3,
            ],
        ];
    }
}
