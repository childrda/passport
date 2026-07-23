<?php

namespace Tests\Feature;

use App\DataTransferObjects\DirectoryUser;
use App\DataTransferObjects\PasswordResetResult;
use App\Enums\AuditFailureCode;
use App\Enums\AuditResult;
use App\Enums\RoleName;
use App\Exceptions\DirectoryApiException;
use App\Exceptions\PasswordResetException;
use App\Filament\Pages\ClassRoster;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\Google\GoogleClientFactory;
use App\Services\GoogleAuthService;
use App\Services\StudentPasswordResetService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Laravel\Socialite\Two\User as SocialiteUser;
use Livewire\Livewire;
use Mockery;
use Tests\TestCase;

/**
 * Pass two remediation coverage (prompts/pass2.md).
 */
class PassTwoRemediationTest extends TestCase
{
    use RefreshDatabase;

    private User $teacher;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        config([
            'reset.staff_domain' => 'lcps.k12.va.us',
            'reset.student_domain' => 'k12louisa.org',
            'reset.classroom_driver' => 'mock',
            'reset.directory_driver' => 'mock',
            'reset.temp_password.length' => 10,
            'reset.temp_password.alphabet' => 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789',
            'reset.google.oauth.client_id' => 'test-client-id',
            'reset.google.oauth.client_secret' => 'test-client-secret',
            'reset.google.oauth.redirect_uri' => 'http://localhost/auth/google/callback',
        ]);

        $this->teacher = User::factory()->create([
            'email' => 'teacher@lcps.k12.va.us',
            'reset_access_enabled' => true,
        ]);
        $this->teacher->assignRole(RoleName::Teacher);
    }

    public function test_canonical_oauth_scopes_include_classroom_email_scope_and_are_shared(): void
    {
        $scopes = config('reset.google.scopes');

        $this->assertContains('https://www.googleapis.com/auth/classroom.courses.readonly', $scopes);
        $this->assertContains('https://www.googleapis.com/auth/classroom.rosters.readonly', $scopes);
        $this->assertContains('https://www.googleapis.com/auth/classroom.profile.emails', $scopes);

        $this->assertSame($scopes, app(GoogleAuthService::class)->scopes());

        config([
            'reset.google.scopes' => ['openid', 'custom-scope-from-config'],
        ]);

        $this->assertSame(['openid', 'custom-scope-from-config'], app(GoogleAuthService::class)->scopes());

        $factory = Mockery::mock(GoogleClientFactory::class)->makePartial();
        $reflected = new \ReflectionClass(GoogleClientFactory::class);
        // Consumers read config directly — assert factory code path uses the same key.
        $source = file_get_contents($reflected->getFileName());
        $this->assertStringContainsString("config('reset.google.scopes'", $source);
    }

    public function test_temporary_password_is_not_in_livewire_serialized_state(): void
    {
        $knownPassword = 'KnownPwd12';

        $service = Mockery::mock(StudentPasswordResetService::class);
        $service->shouldReceive('reset')
            ->once()
            ->andReturn(new PasswordResetResult(
                temporaryPassword: $knownPassword,
                studentName: 'Alex Rivera',
                studentEmail: 'alex.rivera@k12louisa.org',
                directoryUserId: 'dir-1001',
                changePasswordAtNextLogin: true,
            ));
        $this->app->instance(StudentPasswordResetService::class, $service);

        $component = Livewire::actingAs($this->teacher)
            ->test(ClassRoster::class, ['courseId' => 'course-algebra-101'])
            ->mountTableAction('resetPassword', 'student-google-1001')
            ->callMountedTableAction();

        $this->assertFalse(property_exists($component->instance(), 'temporaryPassword'));
        $this->assertStringNotContainsString(
            $knownPassword,
            (string) json_encode($component->instance()->mountedActions ?? [])
        );

        // Immediate response may include the password in a one-shot JS effect only.
        $this->assertStringContainsString($knownPassword, (string) json_encode($component->effects));

        // Subsequent request payload must not carry the password.
        $component->call('$refresh');
        $nextPayload = json_encode([
            'mountedActions' => $component->instance()->mountedActions ?? [],
            'effects' => $component->effects,
            'data' => $component->getData(),
        ]);
        $this->assertStringNotContainsString($knownPassword, (string) $nextPayload);
    }

    public function test_confirmed_directory_failure_allows_retry_messaging(): void
    {
        $directory = Mockery::mock(\App\Contracts\DirectoryService::class);
        $directory->shouldReceive('findByRosterEmail')
            ->andReturn(new DirectoryUser(
                id: 'dir-1001',
                primaryEmail: 'alex.rivera@k12louisa.org',
                fullName: 'Alex Rivera',
                changePasswordAtNextLogin: false,
            ));
        $directory->shouldReceive('resetPassword')
            ->andThrow(DirectoryApiException::confirmedFailure('denied'));
        $this->app->instance(\App\Contracts\DirectoryService::class, $directory);

        try {
            app(StudentPasswordResetService::class)->reset(
                $this->teacher,
                'course-algebra-101',
                'student-google-1001',
            );
            $this->fail('Expected PasswordResetException');
        } catch (PasswordResetException $e) {
            $this->assertTrue($e->allowsRetry);
            $this->assertSame(AuditFailureCode::DirectoryConfirmedFailure, $e->failureCode);
            $this->assertStringContainsString('Please try again', $e->getMessage());
        }

        $log = AuditLog::query()->latest('id')->first();
        $this->assertSame(AuditResult::Failure, $log->result);
        $this->assertSame(AuditFailureCode::DirectoryConfirmedFailure->value, $log->failure_code);
    }

    public function test_unknown_directory_outcome_forbids_retry(): void
    {
        $directory = Mockery::mock(\App\Contracts\DirectoryService::class);
        $directory->shouldReceive('findByRosterEmail')
            ->andReturn(new DirectoryUser(
                id: 'dir-1001',
                primaryEmail: 'alex.rivera@k12louisa.org',
                fullName: 'Alex Rivera',
                changePasswordAtNextLogin: false,
            ));
        $directory->shouldReceive('resetPassword')
            ->andThrow(DirectoryApiException::outcomeUnknown('timeout'));
        $this->app->instance(\App\Contracts\DirectoryService::class, $directory);

        try {
            app(StudentPasswordResetService::class)->reset(
                $this->teacher,
                'course-algebra-101',
                'student-google-1001',
            );
            $this->fail('Expected PasswordResetException');
        } catch (PasswordResetException $e) {
            $this->assertFalse($e->allowsRetry);
            $this->assertSame(AuditFailureCode::DirectoryTimeoutUnknown, $e->failureCode);
            $this->assertStringContainsString('Do not retry yet', $e->getMessage());
        }

        Livewire::actingAs($this->teacher)
            ->test(ClassRoster::class, ['courseId' => 'course-algebra-101'])
            ->call('confirmResetPassword', [
                'full_name' => 'Alex Rivera',
                'email' => 'alex.rivera@k12louisa.org',
                'google_user_id' => 'student-google-1001',
            ])
            ->assertNotified();

        $log = AuditLog::query()->where('failure_code', AuditFailureCode::DirectoryTimeoutUnknown->value)->first();
        $this->assertNotNull($log);
    }

    public function test_lock_prevents_concurrent_reset_on_same_student(): void
    {
        $lock = Cache::lock('student-password-reset:dir-1001', 15);
        $this->assertTrue($lock->get());

        try {
            app(StudentPasswordResetService::class)->reset(
                $this->teacher,
                'course-algebra-101',
                'student-google-1001',
            );
            $this->fail('Expected PasswordResetException');
        } catch (PasswordResetException $e) {
            $this->assertSame(AuditFailureCode::ResetInProgress, $e->failureCode);
        } finally {
            $lock->release();
        }

        $result = app(StudentPasswordResetService::class)->reset(
            $this->teacher,
            'course-algebra-101',
            'student-google-1001',
        );
        $this->assertSame('dir-1001', $result->directoryUserId);
    }

    public function test_staff_without_reset_access_cannot_reset(): void
    {
        $this->teacher->update(['reset_access_enabled' => false]);
        $this->teacher->refresh();

        $this->assertFalse(Gate::forUser($this->teacher)->allows('reset-student-password'));

        try {
            app(StudentPasswordResetService::class)->reset(
                $this->teacher,
                'course-algebra-101',
                'student-google-1001',
            );
            $this->fail('Expected PasswordResetException');
        } catch (PasswordResetException $e) {
            $this->assertSame(AuditFailureCode::ResetAccessDenied, $e->failureCode);
        }

        Livewire::actingAs($this->teacher)
            ->test(ClassRoster::class, ['courseId' => 'course-algebra-101'])
            ->assertSuccessful()
            ->assertDontSee('Reset Password');
    }

    public function test_google_sync_auto_assigns_enabled_teacher_on_create_only(): void
    {
        $googleUser = new SocialiteUser;
        $googleUser->map([
            'id' => 'google-new',
            'name' => 'New Staff',
            'email' => 'new.staff@lcps.k12.va.us',
            'avatar' => null,
        ]);
        $googleUser->token = 'access';
        $googleUser->refreshToken = 'refresh';
        $googleUser->expiresIn = 3600;

        $user = app(GoogleAuthService::class)->syncUserFromGoogle($googleUser);

        $this->assertTrue($user->isTeacher());
        $this->assertTrue($user->reset_access_enabled);
        $this->assertTrue($user->canResetStudentPasswords());
        $this->assertFalse($user->isSystemAdministrator());
        $this->assertFalse($user->isAuditor());

        $user->update(['reset_access_enabled' => false]);
        $user->roles()->detach();

        $again = app(GoogleAuthService::class)->syncUserFromGoogle($googleUser);

        $this->assertFalse($again->reset_access_enabled);
        $this->assertFalse($again->roles()->exists());
        $this->assertFalse($again->canResetStudentPasswords());
    }

    public function test_google_id_mismatch_on_email_match_is_rejected(): void
    {
        $existing = User::factory()->create([
            'email' => 'linked@lcps.k12.va.us',
            'google_id' => 'original-google-id',
        ]);

        $googleUser = new SocialiteUser;
        $googleUser->map([
            'id' => 'different-google-id',
            'name' => 'Linked',
            'email' => 'linked@lcps.k12.va.us',
            'avatar' => null,
        ]);
        $googleUser->token = 'access';
        $googleUser->expiresIn = 3600;

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('already linked to a different Google account');

        app(GoogleAuthService::class)->syncUserFromGoogle($googleUser);

        $this->assertSame('original-google-id', $existing->fresh()->google_id);
    }

    public function test_oauth_prompt_defaults_without_forced_consent(): void
    {
        $params = app(GoogleAuthService::class)->withParameters();
        $this->assertSame('select_account', $params['prompt']);

        $forced = app(GoogleAuthService::class)->withParameters(forceConsent: true);
        $this->assertSame('select_account consent', $forced['prompt']);
    }

    public function test_audit_logs_are_append_only_at_model_and_policy(): void
    {
        $log = AuditLog::query()->create([
            'teacher_user_id' => $this->teacher->id,
            'teacher_email' => $this->teacher->email,
            'teacher_name' => $this->teacher->name,
            'student_google_user_id' => 'student-google-1001',
            'course_id' => 'course-algebra-101',
            'result' => AuditResult::Success->value,
            'occurred_at_utc' => now('UTC'),
            'correlation_id' => (string) \Illuminate\Support\Str::uuid(),
        ]);

        $this->expectException(\LogicException::class);
        $log->update(['failure_reason' => 'tamper']);
    }

    public function test_audit_log_delete_is_blocked(): void
    {
        $log = AuditLog::query()->create([
            'teacher_user_id' => $this->teacher->id,
            'teacher_email' => $this->teacher->email,
            'teacher_name' => $this->teacher->name,
            'student_google_user_id' => 'student-google-1001',
            'course_id' => 'course-algebra-101',
            'result' => AuditResult::Failure->value,
            'failure_code' => AuditFailureCode::NotOnRoster->value,
            'occurred_at_utc' => now('UTC'),
            'correlation_id' => (string) \Illuminate\Support\Str::uuid(),
        ]);

        $this->expectException(\LogicException::class);
        $log->delete();
    }

    public function test_audit_records_include_enrichment_fields(): void
    {
        $this->withServerVariables([
            'HTTP_USER_AGENT' => 'PassTwoTestAgent/1.0',
        ]);

        app(StudentPasswordResetService::class)->reset(
            $this->teacher,
            'course-algebra-101',
            'student-google-1001',
        );

        $log = AuditLog::query()->first();
        $this->assertNotNull($log->occurred_at_utc);
        $this->assertNotNull($log->correlation_id);
        $this->assertNull($log->failure_code);
        $this->assertSame(AuditResult::Success, $log->result);
    }
}
