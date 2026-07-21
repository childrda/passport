<?php

namespace Tests\Feature;

use App\Contracts\ClassroomService;
use App\Contracts\DirectoryService;
use App\Enums\RoleName;
use App\Exceptions\PasswordResetException;
use App\Filament\Pages\ClassRoster;
use App\Models\User;
use App\Services\MockGoogleDirectoryService;
use App\Services\StudentPasswordResetService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Livewire\Livewire;
use Mockery;
use Tests\TestCase;

class PasswordResetTest extends TestCase
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
        ]);
        $this->teacher->assignRole(RoleName::Teacher);
    }

    public function test_successful_reset_sets_change_password_at_next_login(): void
    {
        $result = app(StudentPasswordResetService::class)->reset(
            $this->teacher,
            'course-algebra-101',
            'student-google-1001',
        );

        $this->assertTrue($result->changePasswordAtNextLogin);
        $this->assertSame(10, strlen($result->temporaryPassword));
        $this->assertSame('dir-1001', $result->directoryUserId);

        /** @var MockGoogleDirectoryService $directory */
        $directory = app(MockGoogleDirectoryService::class);
        $this->assertTrue($directory->wasChangePasswordAtNextLoginSet('dir-1001'));
    }

    public function test_teacher_cannot_reset_student_outside_roster(): void
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
        $this->app->instance(ClassroomService::class, $classroom);

        $this->expectException(PasswordResetException::class);
        $this->expectExceptionMessage('Staff accounts cannot have their passwords reset');

        app(StudentPasswordResetService::class)->reset(
            $this->teacher,
            'course-algebra-101',
            'student-google-staff',
        );
    }

    public function test_directory_lookup_failure_denies_reset(): void
    {
        $classroom = Mockery::mock(ClassroomService::class);
        $classroom->shouldReceive('teacherTeachesCourse')->andReturn(true);
        $classroom->shouldReceive('studentEnrolledInCourse')->andReturn(true);
        $this->app->instance(ClassroomService::class, $classroom);

        $directory = Mockery::mock(DirectoryService::class);
        $directory->shouldReceive('findByClassroomUserId')->andReturn(null);
        $this->app->instance(DirectoryService::class, $directory);

        $this->expectException(PasswordResetException::class);
        $this->expectExceptionMessage('Unable to look up the student account');

        app(StudentPasswordResetService::class)->reset(
            $this->teacher,
            'course-algebra-101',
            'student-google-1001',
        );
    }

    public function test_temporary_password_is_not_logged(): void
    {
        Log::spy();

        $component = Livewire::actingAs($this->teacher)
            ->test(ClassRoster::class, ['courseId' => 'course-algebra-101'])
            ->mountTableAction('resetPassword', 'student-google-1001')
            ->callMountedTableAction()
            ->assertActionMounted('showTemporaryPassword');

        $password = $component->instance()->mountedActions[0]['arguments']['temporaryPassword'] ?? null;
        $this->assertNotEmpty($password);

        Log::shouldHaveReceived('info')
            ->withArgs(function (string $message, array $context) use ($password): bool {
                $payload = json_encode([$message, $context]);

                return ! str_contains((string) $payload, $password);
            })
            ->atLeast()
            ->once();
    }

    public function test_temporary_password_is_not_a_livewire_property_and_clears_on_dismiss(): void
    {
        $component = Livewire::actingAs($this->teacher)
            ->test(ClassRoster::class, ['courseId' => 'course-algebra-101'])
            ->mountTableAction('resetPassword', 'student-google-1001')
            ->callMountedTableAction()
            ->assertActionMounted('showTemporaryPassword');

        $this->assertFalse(property_exists($component->instance(), 'temporaryPassword'));

        $password = $component->instance()->mountedActions[0]['arguments']['temporaryPassword'] ?? null;
        $this->assertNotEmpty($password);
        $component->assertMountedActionModalSee($password);

        $component
            ->unmountAction()
            ->assertActionNotMounted();

        $this->assertSame([], $component->instance()->mountedActions);
        $this->assertNull(session('temporaryPassword') ?? session('temp_password') ?? null);
    }

    public function test_ui_reset_shows_one_time_password_modal(): void
    {
        Livewire::actingAs($this->teacher)
            ->test(ClassRoster::class, ['courseId' => 'course-algebra-101'])
            ->mountTableAction('resetPassword', 'student-google-1001')
            ->callMountedTableAction()
            ->assertActionMounted('showTemporaryPassword')
            ->assertMountedActionModalSee('Temporary password for')
            ->assertMountedActionModalSee('Alex Rivera');
    }

    public function test_mock_directory_does_not_retain_password(): void
    {
        $result = app(StudentPasswordResetService::class)->reset(
            $this->teacher,
            'course-algebra-101',
            'student-google-1001',
        );

        /** @var MockGoogleDirectoryService $directory */
        $directory = app(MockGoogleDirectoryService::class);
        $serialized = serialize($directory);

        $this->assertStringNotContainsString($result->temporaryPassword, $serialized);
    }
}
