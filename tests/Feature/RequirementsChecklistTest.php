<?php

namespace Tests\Feature;

use App\Contracts\ClassroomService;
use App\Contracts\DirectoryApiGateway;
use App\Contracts\DirectoryService;
use App\Enums\AuditResult;
use App\Enums\RoleName;
use App\Exceptions\ClassroomApiException;
use App\Exceptions\PasswordResetException;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\GoogleClassroomService;
use App\Services\GoogleDirectoryService;
use App\Services\StudentPasswordResetService;
use App\Services\TemporaryPasswordGenerator;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

/**
 * Maps directly to the automated-test requirements in prompts/main.md.
 */
class RequirementsChecklistTest extends TestCase
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
        ]);

        $this->teacher = User::factory()->create([
            'email' => 'teacher@lcps.k12.va.us',
            'google_id' => 'teacher-google-1',
            'reset_access_enabled' => true,
        ]);
        $this->teacher->assignRole(RoleName::Teacher);
    }

    public function test_teacher_can_see_their_own_classes(): void
    {
        $courses = app(ClassroomService::class)->coursesForTeacher($this->teacher);

        $this->assertTrue($courses->contains(fn ($c) => $c->id === 'course-algebra-101'));
        $this->assertFalse($courses->contains(fn ($c) => $c->id === 'course-history-301'));
    }

    public function test_teacher_can_see_students_enrolled_in_their_classes(): void
    {
        $students = app(ClassroomService::class)
            ->studentsForCourse($this->teacher, 'course-algebra-101');

        $this->assertTrue($students->contains(fn ($s) => $s->googleUserId === 'student-google-1001'));
    }

    public function test_teacher_cannot_access_another_teachers_roster(): void
    {
        $this->assertFalse(
            app(ClassroomService::class)->teacherTeachesCourse($this->teacher, 'course-history-301')
        );
        $this->assertCount(
            0,
            app(ClassroomService::class)->studentsForCourse($this->teacher, 'course-history-301')
        );
    }

    public function test_teacher_cannot_reset_student_outside_their_roster(): void
    {
        $this->expectException(PasswordResetException::class);

        app(StudentPasswordResetService::class)->reset(
            $this->teacher,
            'course-algebra-101',
            'student-google-3001',
        );
    }

    public function test_staff_domain_accounts_cannot_be_reset(): void
    {
        $classroom = Mockery::mock(ClassroomService::class);
        $classroom->shouldReceive('teacherTeachesCourse')->andReturn(true);
        $classroom->shouldReceive('studentEnrolledInCourse')->andReturn(true);
        $classroom->shouldReceive('coursesForTeacher')->andReturn(collect());
        $this->app->instance(ClassroomService::class, $classroom);

        $this->expectException(PasswordResetException::class);
        $this->expectExceptionMessage('Staff accounts cannot have their passwords reset');

        app(StudentPasswordResetService::class)->reset(
            $this->teacher,
            'course-algebra-101',
            'student-google-staff',
        );
    }

    public function test_generated_password_matches_configured_length_default_10(): void
    {
        $password = app(TemporaryPasswordGenerator::class)->generate();

        $this->assertSame(10, strlen($password));
    }

    public function test_password_contains_only_characters_from_configured_alphabet(): void
    {
        $alphabet = (string) config('reset.temp_password.alphabet');
        $password = app(TemporaryPasswordGenerator::class)->generate();

        foreach (str_split($password) as $char) {
            $this->assertStringContainsString($char, $alphabet);
        }
    }

    public function test_change_password_at_next_login_is_set_to_true(): void
    {
        $result = app(StudentPasswordResetService::class)->reset(
            $this->teacher,
            'course-algebra-101',
            'student-google-1001',
        );

        $this->assertTrue($result->changePasswordAtNextLogin);
    }

    public function test_temporary_password_is_not_stored_or_logged(): void
    {
        $result = app(StudentPasswordResetService::class)->reset(
            $this->teacher,
            'course-algebra-101',
            'student-google-1001',
        );

        $log = AuditLog::query()->latest('id')->first();
        $this->assertNotNull($log);
        $this->assertStringNotContainsString(
            $result->temporaryPassword,
            (string) json_encode($log->getAttributes())
        );

        /** @var \App\Services\MockGoogleDirectoryService $directory */
        $directory = app(\App\Services\MockGoogleDirectoryService::class);
        $this->assertStringNotContainsString(
            $result->temporaryPassword,
            serialize($directory)
        );
    }

    public function test_google_api_failures_are_shown_as_failures_rather_than_successes(): void
    {
        $api = Mockery::mock(\App\Contracts\ClassroomApiGateway::class);
        $api->shouldReceive('listTeacherUserIds')
            ->andThrow(ClassroomApiException::requestFailed('(timeout)'));

        $this->app->bind(ClassroomService::class, fn () => new GoogleClassroomService($api));

        try {
            app(StudentPasswordResetService::class)->reset(
                $this->teacher,
                'course-algebra-101',
                'student-google-1001',
            );
            $this->fail('Expected PasswordResetException');
        } catch (PasswordResetException $e) {
            $this->assertStringContainsString('Google Classroom', $e->getMessage());
        }

        $log = AuditLog::query()->latest('id')->first();
        $this->assertNotNull($log);
        $this->assertSame(AuditResult::Failure, $log->result);
    }

    public function test_every_attempt_creates_an_audit_record(): void
    {
        app(StudentPasswordResetService::class)->reset(
            $this->teacher,
            'course-algebra-101',
            'student-google-1001',
        );

        try {
            app(StudentPasswordResetService::class)->reset(
                $this->teacher,
                'course-algebra-101',
                'student-google-3001',
            );
        } catch (PasswordResetException) {
            // expected failure
        }

        $this->assertSame(2, AuditLog::query()->count());
        $this->assertSame(1, AuditLog::query()->where('result', AuditResult::Success->value)->count());
        $this->assertSame(1, AuditLog::query()->where('result', AuditResult::Failure->value)->count());
    }

    public function test_directory_api_failure_creates_failure_audit_not_success(): void
    {
        $classroom = Mockery::mock(ClassroomService::class);
        $classroom->shouldReceive('teacherTeachesCourse')->andReturn(true);
        $classroom->shouldReceive('studentEnrolledInCourse')->andReturn(true);
        $classroom->shouldReceive('coursesForTeacher')->andReturn(collect());
        $this->app->instance(ClassroomService::class, $classroom);

        $gateway = Mockery::mock(DirectoryApiGateway::class);
        $gateway->shouldReceive('getUserById')
            ->andThrow(\App\Exceptions\DirectoryApiException::requestFailed('(timeout)'));
        $this->app->bind(DirectoryService::class, fn () => new GoogleDirectoryService($gateway));

        try {
            app(StudentPasswordResetService::class)->reset(
                $this->teacher,
                'course-algebra-101',
                'student-google-1001',
            );
            $this->fail('Expected PasswordResetException');
        } catch (PasswordResetException) {
            // expected
        }

        $log = AuditLog::query()->latest('id')->first();
        $this->assertSame(AuditResult::Failure, $log->result);
        $this->assertSame(0, AuditLog::query()->where('result', AuditResult::Success->value)->count());
    }
}
