<?php

namespace Tests\Feature;

use App\Contracts\ClassroomApiGateway;
use App\Enums\RoleName;
use App\Exceptions\ClassroomApiException;
use App\Exceptions\PasswordResetException;
use App\Models\User;
use App\Services\GoogleClassroomService;
use App\Services\StudentPasswordResetService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class GoogleClassroomServiceTest extends TestCase
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
            'reset.classroom_driver' => 'google',
            'reset.directory_driver' => 'mock',
        ]);

        $this->teacher = User::factory()->create([
            'email' => 'teacher@lcps.k12.va.us',
            'google_id' => 'teacher-google-1',
            'google_access_token' => 'access-token',
            'google_refresh_token' => 'refresh-token',
            'reset_access_enabled' => true,
        ]);
        $this->teacher->assignRole(RoleName::Teacher);
    }

    public function test_teacher_can_see_their_own_classes(): void
    {
        $api = Mockery::mock(ClassroomApiGateway::class);
        $api->shouldReceive('listActiveCourses')
            ->once()
            ->with(Mockery::on(fn (User $user): bool => $user->is($this->teacher)))
            ->andReturn([
                [
                    'id' => 'course-1',
                    'name' => 'Algebra I',
                    'section' => 'Period 2',
                    'courseState' => 'ACTIVE',
                ],
            ]);

        $service = new GoogleClassroomService($api);
        $courses = $service->coursesForTeacher($this->teacher);

        $this->assertCount(1, $courses);
        $this->assertSame('course-1', $courses->first()->id);
        $this->assertSame('Algebra I', $courses->first()->name);
    }

    public function test_teacher_can_see_students_enrolled_in_their_classes(): void
    {
        $api = Mockery::mock(ClassroomApiGateway::class);
        $api->shouldReceive('listTeacherUserIds')
            ->once()
            ->with($this->teacher, 'course-1')
            ->andReturn(['teacher-google-1']);
        $api->shouldReceive('listStudents')
            ->once()
            ->with($this->teacher, 'course-1')
            ->andReturn([
                [
                    'userId' => 'student-1',
                    'fullName' => 'Alex Rivera',
                    'email' => 'alex.rivera@k12louisa.org',
                ],
            ]);

        $service = new GoogleClassroomService($api);
        $students = $service->studentsForCourse($this->teacher, 'course-1');

        $this->assertCount(1, $students);
        $this->assertSame('student-1', $students->first()->googleUserId);
    }

    public function test_teacher_cannot_access_another_teachers_roster(): void
    {
        $api = Mockery::mock(ClassroomApiGateway::class);
        $api->shouldReceive('listTeacherUserIds')
            ->twice()
            ->with($this->teacher, 'course-other')
            ->andReturn(['other-teacher-google']);
        $api->shouldReceive('listActiveCourses')
            ->twice()
            ->andReturn([
                [
                    'id' => 'course-1',
                    'name' => 'Algebra I',
                    'section' => null,
                    'courseState' => 'ACTIVE',
                ],
            ]);
        $api->shouldReceive('listStudents')->never();

        $service = new GoogleClassroomService($api);

        $this->assertFalse($service->teacherTeachesCourse($this->teacher, 'course-other'));
        $this->assertCount(0, $service->studentsForCourse($this->teacher, 'course-other'));
    }

    public function test_api_failure_denies_password_reset(): void
    {
        $api = Mockery::mock(ClassroomApiGateway::class);
        $api->shouldReceive('listTeacherUserIds')
            ->once()
            ->andThrow(ClassroomApiException::requestFailed('(timeout)'));

        $this->app->instance(ClassroomApiGateway::class, $api);
        $this->app->forgetInstance(GoogleClassroomService::class);
        config(['reset.classroom_driver' => 'google']);

        // Re-bind ClassroomService to live implementation for this test.
        $this->app->bind(
            \App\Contracts\ClassroomService::class,
            fn () => new GoogleClassroomService($api),
        );

        $this->expectException(PasswordResetException::class);
        $this->expectExceptionMessage('Unable to verify class enrollment with Google Classroom');

        app(StudentPasswordResetService::class)->reset(
            $this->teacher,
            'course-1',
            'student-1',
        );
    }

    public function test_courses_for_teacher_surfaces_api_failures(): void
    {
        $api = Mockery::mock(ClassroomApiGateway::class);
        $api->shouldReceive('listActiveCourses')
            ->once()
            ->andThrow(ClassroomApiException::requestFailed());

        $service = new GoogleClassroomService($api);

        $this->expectException(ClassroomApiException::class);

        $service->coursesForTeacher($this->teacher);
    }
}
