<?php

namespace Tests\Feature;

use App\Contracts\ClassroomService;
use App\Contracts\DirectoryApiGateway;
use App\Contracts\DirectoryService;
use App\DataTransferObjects\ClassroomStudent;
use App\DataTransferObjects\DirectoryUser;
use App\Enums\AuditFailureCode;
use App\Enums\AuditResult;
use App\Enums\RoleName;
use App\Exceptions\PasswordResetException;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\GoogleDirectoryService;
use App\Services\StudentPasswordResetService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Mockery;
use Tests\TestCase;

/**
 * Pass three — two-tenant Directory discovery by roster email.
 */
class PassThreeTwoTenantTest extends TestCase
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
            'reset_access_enabled' => true,
        ]);
        $this->teacher->assignRole(RoleName::Teacher);
    }

    public function test_student_discovered_by_roster_email_can_be_reset(): void
    {
        $result = app(StudentPasswordResetService::class)->reset(
            $this->teacher,
            'course-algebra-101',
            'student-google-1001',
        );

        $this->assertSame('dir-1001', $result->directoryUserId);
        $this->assertSame('alex.rivera@k12louisa.org', $result->studentEmail);
        $this->assertTrue($result->changePasswordAtNextLogin);
    }

    public function test_browser_submitted_email_is_ignored_for_directory_lookup(): void
    {
        $directory = Mockery::mock(DirectoryService::class);
        $directory->shouldReceive('findByRosterEmail')
            ->once()
            ->with('alex.rivera@k12louisa.org')
            ->andReturn(new DirectoryUser(
                id: 'dir-1001',
                primaryEmail: 'alex.rivera@k12louisa.org',
                fullName: 'Alex Rivera',
            ));
        $directory->shouldReceive('resetPassword')
            ->once()
            ->with('dir-1001', Mockery::type('string'), true)
            ->andReturn(new DirectoryUser(
                id: 'dir-1001',
                primaryEmail: 'alex.rivera@k12louisa.org',
                fullName: 'Alex Rivera',
                changePasswordAtNextLogin: true,
            ));
        $this->app->instance(DirectoryService::class, $directory);

        // Even if a caller tried to smuggle an email via a forged ClassroomStudent list,
        // only the server-side roster object matched by member ID is used.
        $classroom = Mockery::mock(ClassroomService::class);
        $classroom->shouldReceive('teacherTeachesCourse')->andReturn(true);
        $classroom->shouldReceive('studentEnrolledInCourse')->andReturn(true);
        $classroom->shouldReceive('studentsForCourse')->andReturn(new Collection([
            new ClassroomStudent(
                'student-google-1001',
                'Alex Rivera',
                '  ALEX.RIVERA@K12LOUISA.ORG  ',
            ),
        ]));
        $classroom->shouldReceive('coursesForTeacher')->andReturn(new Collection);
        $this->app->instance(ClassroomService::class, $classroom);

        $result = app(StudentPasswordResetService::class)->reset(
            $this->teacher,
            'course-algebra-101',
            'student-google-1001',
        );

        $this->assertSame('dir-1001', $result->directoryUserId);
    }

    public function test_roster_email_outside_student_domain_is_rejected_pre_lookup(): void
    {
        $directory = Mockery::mock(DirectoryService::class);
        $directory->shouldNotReceive('findByRosterEmail');
        $this->app->instance(DirectoryService::class, $directory);

        $classroom = Mockery::mock(ClassroomService::class);
        $classroom->shouldReceive('teacherTeachesCourse')->andReturn(true);
        $classroom->shouldReceive('studentEnrolledInCourse')->andReturn(true);
        $classroom->shouldReceive('studentsForCourse')->andReturn(new Collection([
            new ClassroomStudent('student-google-1001', 'Outsider', 'kid@gmail.com'),
        ]));
        $classroom->shouldReceive('coursesForTeacher')->andReturn(new Collection);
        $this->app->instance(ClassroomService::class, $classroom);

        try {
            app(StudentPasswordResetService::class)->reset(
                $this->teacher,
                'course-algebra-101',
                'student-google-1001',
            );
            $this->fail('Expected PasswordResetException');
        } catch (PasswordResetException $e) {
            $this->assertSame(AuditFailureCode::WrongStudentDomain, $e->failureCode);
        }
    }

    public function test_alias_resolving_to_non_student_primary_is_rejected(): void
    {
        $classroom = Mockery::mock(ClassroomService::class);
        $classroom->shouldReceive('teacherTeachesCourse')->andReturn(true);
        $classroom->shouldReceive('studentEnrolledInCourse')->andReturn(true);
        $classroom->shouldReceive('studentsForCourse')->andReturn(new Collection([
            new ClassroomStudent(
                'student-google-misdirect',
                'Misdirected',
                'alias.misdirect@k12louisa.org',
            ),
        ]));
        $classroom->shouldReceive('coursesForTeacher')->andReturn(new Collection);
        $this->app->instance(ClassroomService::class, $classroom);

        try {
            app(StudentPasswordResetService::class)->reset(
                $this->teacher,
                'course-algebra-101',
                'student-google-misdirect',
            );
            $this->fail('Expected PasswordResetException');
        } catch (PasswordResetException $e) {
            $this->assertSame(AuditFailureCode::WrongStudentDomain, $e->failureCode);
        }
    }

    public function test_alias_roster_email_audits_both_emails(): void
    {
        $classroom = Mockery::mock(ClassroomService::class);
        $classroom->shouldReceive('teacherTeachesCourse')->andReturn(true);
        $classroom->shouldReceive('studentEnrolledInCourse')->andReturn(true);
        $classroom->shouldReceive('studentsForCourse')->andReturn(new Collection([
            new ClassroomStudent(
                'student-google-1001',
                'Alex Rivera',
                'alex.alias@k12louisa.org',
            ),
        ]));
        $classroom->shouldReceive('coursesForTeacher')->andReturn(new Collection);
        $this->app->instance(ClassroomService::class, $classroom);

        app(StudentPasswordResetService::class)->reset(
            $this->teacher,
            'course-algebra-101',
            'student-google-1001',
        );

        $log = AuditLog::query()->first();
        $this->assertSame(AuditResult::Success, $log->result);
        $this->assertSame('alex.alias@k12louisa.org', $log->roster_email);
        $this->assertSame('alex.rivera@k12louisa.org', $log->student_email);
        $this->assertNotSame($log->roster_email, $log->student_email);
    }

    public function test_staff_domain_roster_email_is_rejected(): void
    {
        $classroom = Mockery::mock(ClassroomService::class);
        $classroom->shouldReceive('teacherTeachesCourse')->andReturn(true);
        $classroom->shouldReceive('studentEnrolledInCourse')->andReturn(true);
        $classroom->shouldReceive('studentsForCourse')->andReturn(new Collection([
            new ClassroomStudent(
                'student-google-staff',
                'Staff Person',
                'staff.person@lcps.k12.va.us',
            ),
        ]));
        $classroom->shouldReceive('coursesForTeacher')->andReturn(new Collection);
        $this->app->instance(ClassroomService::class, $classroom);

        $this->expectException(PasswordResetException::class);
        $this->expectExceptionMessage('Staff accounts cannot have their passwords reset');

        app(StudentPasswordResetService::class)->reset(
            $this->teacher,
            'course-algebra-101',
            'student-google-staff',
        );
    }

    public function test_missing_student_tenant_directory_user_is_distinct_failure(): void
    {
        $classroom = Mockery::mock(ClassroomService::class);
        $classroom->shouldReceive('teacherTeachesCourse')->andReturn(true);
        $classroom->shouldReceive('studentEnrolledInCourse')->andReturn(true);
        $classroom->shouldReceive('studentsForCourse')->andReturn(new Collection([
            new ClassroomStudent(
                'student-google-missing',
                'Missing Student',
                'missing.student@k12louisa.org',
            ),
        ]));
        $classroom->shouldReceive('coursesForTeacher')->andReturn(new Collection);
        $this->app->instance(ClassroomService::class, $classroom);

        try {
            app(StudentPasswordResetService::class)->reset(
                $this->teacher,
                'course-algebra-101',
                'student-google-missing',
            );
            $this->fail('Expected PasswordResetException');
        } catch (PasswordResetException $e) {
            $this->assertSame(AuditFailureCode::StudentNotInDirectory, $e->failureCode);
            $this->assertStringContainsString('no matching account', $e->getMessage());
        }

        $log = AuditLog::query()->latest('id')->first();
        $this->assertSame(AuditFailureCode::StudentNotInDirectory->value, $log->failure_code);
        $this->assertSame('missing.student@k12louisa.org', $log->roster_email);
    }

    public function test_reset_uses_immutable_directory_id_not_email(): void
    {
        $api = Mockery::mock(DirectoryApiGateway::class);
        $api->shouldReceive('getUser')
            ->once()
            ->with('alex.rivera@k12louisa.org')
            ->andReturn([
                'id' => 'dir-1001',
                'primaryEmail' => 'alex.rivera@k12louisa.org',
                'fullName' => 'Alex Rivera',
                'changePasswordAtNextLogin' => false,
            ]);
        $api->shouldReceive('updatePassword')
            ->once()
            ->withArgs(function (string $id, string $password, bool $change): bool {
                return $id === 'dir-1001'
                    && $id !== 'alex.rivera@k12louisa.org'
                    && $change === true
                    && strlen($password) === 10;
            })
            ->andReturn([
                'id' => 'dir-1001',
                'primaryEmail' => 'alex.rivera@k12louisa.org',
                'fullName' => 'Alex Rivera',
                'changePasswordAtNextLogin' => true,
            ]);
        $this->app->instance(DirectoryApiGateway::class, $api);
        $this->app->instance(DirectoryService::class, app(GoogleDirectoryService::class));

        $result = app(StudentPasswordResetService::class)->reset(
            $this->teacher,
            'course-algebra-101',
            'student-google-1001',
        );

        $this->assertSame('dir-1001', $result->directoryUserId);
    }
}
