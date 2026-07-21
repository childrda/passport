<?php

namespace Tests\Feature;

use App\Enums\AuditResult;
use App\Enums\RoleName;
use App\Filament\Pages\IntegrationStatus;
use App\Filament\Pages\MyClasses;
use App\Filament\Resources\AuditLogs\AuditLogResource;
use App\Filament\Resources\Users\UserResource;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\StudentPasswordResetService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditAndAdministrationTest extends TestCase
{
    use RefreshDatabase;

    private User $teacher;

    private User $admin;

    private User $auditor;

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
        ]);

        $this->teacher = User::factory()->create(['email' => 'teacher@lcps.k12.va.us']);
        $this->teacher->assignRole(RoleName::Teacher);

        $this->admin = User::factory()->create(['email' => 'admin@lcps.k12.va.us']);
        $this->admin->assignRole(RoleName::SystemAdministrator);

        $this->auditor = User::factory()->create(['email' => 'auditor@lcps.k12.va.us']);
        $this->auditor->assignRole(RoleName::Auditor);
    }

    public function test_successful_reset_creates_success_audit_record_without_password(): void
    {
        $result = app(StudentPasswordResetService::class)->reset(
            $this->teacher,
            'course-algebra-101',
            'student-google-1001',
        );

        $log = AuditLog::query()->first();
        $this->assertNotNull($log);
        $this->assertSame(AuditResult::Success, $log->result);
        $this->assertSame($this->teacher->id, $log->teacher_user_id);
        $this->assertSame('student-google-1001', $log->student_google_user_id);
        $this->assertSame('course-algebra-101', $log->course_id);
        $this->assertNull($log->failure_reason);

        $serialized = $log->toArray();
        $this->assertStringNotContainsString($result->temporaryPassword, json_encode($serialized));
    }

    public function test_failed_reset_creates_failure_audit_record(): void
    {
        try {
            app(StudentPasswordResetService::class)->reset(
                $this->teacher,
                'course-algebra-101',
                'student-google-3001',
            );
            $this->fail('Expected PasswordResetException');
        } catch (\App\Exceptions\PasswordResetException) {
            // expected
        }

        $log = AuditLog::query()->first();
        $this->assertNotNull($log);
        $this->assertSame(AuditResult::Failure, $log->result);
        $this->assertNotNull($log->failure_reason);
        $this->assertSame(1, AuditLog::query()->count());
    }

    public function test_system_administrator_can_access_admin_pages(): void
    {
        $this->actingAs($this->admin)
            ->get(UserResource::getUrl())
            ->assertOk();

        $this->actingAs($this->admin)
            ->get(AuditLogResource::getUrl())
            ->assertOk();

        $this->actingAs($this->admin)
            ->get(IntegrationStatus::getUrl())
            ->assertOk()
            ->assertSee('Integration status')
            ->assertSee('Environment-managed settings are read-only')
            ->assertDontSee('GOOGLE_OAUTH_CLIENT_SECRET')
            ->assertDontSee('GOOGLE_SERVICE_ACCOUNT_CREDENTIALS');
    }

    public function test_auditor_can_only_access_audit_logs(): void
    {
        $this->actingAs($this->auditor)
            ->get(AuditLogResource::getUrl())
            ->assertOk();

        $this->actingAs($this->auditor)
            ->get(UserResource::getUrl())
            ->assertForbidden();

        $this->actingAs($this->auditor)
            ->get(IntegrationStatus::getUrl())
            ->assertForbidden();

        $this->actingAs($this->auditor)
            ->get(MyClasses::getUrl())
            ->assertForbidden();
    }

    public function test_teacher_cannot_access_administration(): void
    {
        $this->actingAs($this->teacher)
            ->get(UserResource::getUrl())
            ->assertForbidden();

        $this->actingAs($this->teacher)
            ->get(AuditLogResource::getUrl())
            ->assertForbidden();

        $this->actingAs($this->teacher)
            ->get(IntegrationStatus::getUrl())
            ->assertForbidden();
    }

    public function test_audit_logs_are_read_only(): void
    {
        $this->assertFalse(AuditLogResource::canCreate());
        $this->assertFalse(AuditLogResource::canDeleteAny());
    }
}
