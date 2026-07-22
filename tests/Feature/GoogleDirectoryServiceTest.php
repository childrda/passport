<?php

namespace Tests\Feature;

use App\Contracts\ClassroomService;
use App\Contracts\DirectoryApiGateway;
use App\Contracts\DirectoryService;
use App\DataTransferObjects\ClassroomStudent;
use App\DataTransferObjects\DirectoryUser;
use App\Enums\RoleName;
use App\Exceptions\DirectoryApiException;
use App\Exceptions\PasswordResetException;
use App\Models\User;
use App\Services\GoogleDirectoryService;
use App\Services\StudentPasswordResetService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Mockery;
use Tests\TestCase;

class GoogleDirectoryServiceTest extends TestCase
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
            'reset.directory_driver' => 'google',
            'reset.temp_password.length' => 10,
            'reset.temp_password.alphabet' => 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789',
        ]);

        $this->teacher = User::factory()->create([
            'email' => 'teacher@lcps.k12.va.us',
            'reset_access_enabled' => true,
        ]);
        $this->teacher->assignRole(RoleName::Teacher);
    }

    public function test_find_by_roster_email_maps_canonical_user(): void
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

        $service = new GoogleDirectoryService($api);
        $user = $service->findByRosterEmail('alex.rivera@k12louisa.org');

        $this->assertInstanceOf(DirectoryUser::class, $user);
        $this->assertSame('dir-1001', $user->id);
        $this->assertSame('alex.rivera@k12louisa.org', $user->primaryEmail);
    }

    public function test_find_returns_null_when_directory_user_missing(): void
    {
        $api = Mockery::mock(DirectoryApiGateway::class);
        $api->shouldReceive('getUser')->once()->andReturn(null);

        $service = new GoogleDirectoryService($api);

        $this->assertNull($service->findByRosterEmail('missing@k12louisa.org'));
    }

    public function test_reset_password_sets_change_password_at_next_login(): void
    {
        $api = Mockery::mock(DirectoryApiGateway::class);
        $api->shouldReceive('updatePassword')
            ->once()
            ->withArgs(function (string $id, string $password, bool $change): bool {
                return $id === 'dir-1001'
                    && strlen($password) === 10
                    && $change === true;
            })
            ->andReturn([
                'id' => 'dir-1001',
                'primaryEmail' => 'alex.rivera@k12louisa.org',
                'fullName' => 'Alex Rivera',
                'changePasswordAtNextLogin' => true,
            ]);

        $service = new GoogleDirectoryService($api);
        $updated = $service->resetPassword('dir-1001', 'Abcd234567', true);

        $this->assertTrue($updated->changePasswordAtNextLogin);
        $this->assertSame('dir-1001', $updated->id);
    }

    public function test_directory_api_failure_denies_password_reset(): void
    {
        $this->bindClassroomRoster('student-google-1001', 'Alex Rivera', 'alex.rivera@k12louisa.org');

        $api = Mockery::mock(DirectoryApiGateway::class);
        $api->shouldReceive('getUser')
            ->once()
            ->andThrow(DirectoryApiException::requestFailed('(timeout)'));

        $this->app->bind(DirectoryService::class, fn () => new GoogleDirectoryService($api));

        $this->expectException(PasswordResetException::class);
        $this->expectExceptionMessage('Unable to look up the student account');

        app(StudentPasswordResetService::class)->reset(
            $this->teacher,
            'course-algebra-101',
            'student-google-1001',
        );
    }

    public function test_live_directory_reset_end_to_end_with_mocked_gateway(): void
    {
        $this->bindClassroomRoster('student-google-1001', 'Alex Rivera', 'alex.rivera@k12louisa.org');

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
                $this->assertSame('dir-1001', $id);
                $this->assertTrue($change);
                $this->assertSame(10, strlen($password));

                return true;
            })
            ->andReturn([
                'id' => 'dir-1001',
                'primaryEmail' => 'alex.rivera@k12louisa.org',
                'fullName' => 'Alex Rivera',
                'changePasswordAtNextLogin' => true,
            ]);

        $this->app->bind(DirectoryService::class, fn () => new GoogleDirectoryService($api));

        $result = app(StudentPasswordResetService::class)->reset(
            $this->teacher,
            'course-algebra-101',
            'student-google-1001',
        );

        $this->assertTrue($result->changePasswordAtNextLogin);
        $this->assertSame('dir-1001', $result->directoryUserId);
        $this->assertSame(10, strlen($result->temporaryPassword));
    }

    public function test_staff_domain_still_blocked_with_live_directory_driver(): void
    {
        // Roster email on student domain that resolves to a staff primary (post-lookup reject).
        $this->bindClassroomRoster(
            'student-google-staff',
            'Staff Person',
            'staff.alias@k12louisa.org',
        );

        $api = Mockery::mock(DirectoryApiGateway::class);
        $api->shouldReceive('getUser')
            ->once()
            ->with('staff.alias@k12louisa.org')
            ->andReturn([
                'id' => 'dir-staff',
                'primaryEmail' => 'staff.person@lcps.k12.va.us',
                'fullName' => 'Staff Person',
                'changePasswordAtNextLogin' => false,
            ]);
        $api->shouldReceive('updatePassword')->never();

        $this->app->bind(DirectoryService::class, fn () => new GoogleDirectoryService($api));

        $this->expectException(PasswordResetException::class);
        $this->expectExceptionMessage('Staff accounts cannot have their passwords reset');

        app(StudentPasswordResetService::class)->reset(
            $this->teacher,
            'course-algebra-101',
            'student-google-staff',
        );
    }

    public function test_update_password_api_failure_denies_reset(): void
    {
        $this->bindClassroomRoster('student-google-1001', 'Alex Rivera', 'alex.rivera@k12louisa.org');

        $api = Mockery::mock(DirectoryApiGateway::class);
        $api->shouldReceive('getUser')->once()->andReturn([
            'id' => 'dir-1001',
            'primaryEmail' => 'alex.rivera@k12louisa.org',
            'fullName' => 'Alex Rivera',
            'changePasswordAtNextLogin' => false,
        ]);
        $api->shouldReceive('updatePassword')
            ->once()
            ->andThrow(DirectoryApiException::requestFailed('(forbidden)'));

        $this->app->bind(DirectoryService::class, fn () => new GoogleDirectoryService($api));

        $this->expectException(PasswordResetException::class);
        $this->expectExceptionMessage('The password reset could not be completed');

        app(StudentPasswordResetService::class)->reset(
            $this->teacher,
            'course-algebra-101',
            'student-google-1001',
        );
    }

    private function bindClassroomRoster(string $id, string $name, string $email): void
    {
        $classroom = Mockery::mock(ClassroomService::class);
        $classroom->shouldReceive('teacherTeachesCourse')->andReturn(true);
        $classroom->shouldReceive('studentEnrolledInCourse')->andReturn(true);
        $classroom->shouldReceive('studentsForCourse')->andReturn(new Collection([
            new ClassroomStudent($id, $name, $email),
        ]));
        $classroom->shouldReceive('coursesForTeacher')->andReturn(new Collection);
        $this->app->instance(ClassroomService::class, $classroom);
    }
}
