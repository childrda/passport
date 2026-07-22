<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Filament\Pages\ClassRoster;
use App\Filament\Pages\MyClasses;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TeacherClassroomUiTest extends TestCase
{
    use RefreshDatabase;

    private User $teacher;

    private User $otherTeacher;

    private User $auditor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        config([
            'reset.staff_domain' => 'lcps.k12.va.us',
            'reset.student_domain' => 'k12louisa.org',
            'reset.classroom_driver' => 'mock',
        ]);

        $this->teacher = User::factory()->create([
            'email' => 'teacher@lcps.k12.va.us',
            'name' => 'Local Teacher',
            'reset_access_enabled' => true,
        ]);
        $this->teacher->assignRole(RoleName::Teacher);

        $this->otherTeacher = User::factory()->create([
            'email' => 'other.teacher@lcps.k12.va.us',
            'name' => 'Other Teacher',
            'reset_access_enabled' => true,
        ]);
        $this->otherTeacher->assignRole(RoleName::Teacher);

        $this->auditor = User::factory()->create([
            'email' => 'auditor@lcps.k12.va.us',
            'name' => 'Local Auditor',
        ]);
        $this->auditor->assignRole(RoleName::Auditor);
    }

    public function test_teacher_can_open_my_classes_page(): void
    {
        $this->actingAs($this->teacher)
            ->get(MyClasses::getUrl())
            ->assertOk()
            ->assertSee('My Classes')
            ->assertSee('Algebra I')
            ->assertSee('Biology');
    }

    public function test_teacher_does_not_see_another_teachers_classes(): void
    {
        $this->actingAs($this->teacher)
            ->get(MyClasses::getUrl())
            ->assertOk()
            ->assertDontSee('US History');
    }

    public function test_teacher_can_open_own_class_roster(): void
    {
        $this->actingAs($this->teacher)
            ->get(ClassRoster::getUrl(['courseId' => 'course-algebra-101']))
            ->assertOk()
            ->assertSee('Alex Rivera')
            ->assertSee('alex.rivera@k12louisa.org')
            ->assertSee('Reset Password');
    }

    public function test_teacher_cannot_open_another_teachers_roster(): void
    {
        $this->actingAs($this->teacher)
            ->get(ClassRoster::getUrl(['courseId' => 'course-history-301']))
            ->assertRedirect(MyClasses::getUrl());
    }

    public function test_other_teacher_sees_only_their_history_class(): void
    {
        $this->actingAs($this->otherTeacher)
            ->get(MyClasses::getUrl())
            ->assertOk()
            ->assertSee('US History')
            ->assertDontSee('Algebra I');
    }

    public function test_auditor_cannot_access_my_classes(): void
    {
        $this->actingAs($this->auditor)
            ->get(MyClasses::getUrl())
            ->assertForbidden();
    }

    public function test_reset_password_action_rechecks_enrollment(): void
    {
        Livewire::actingAs($this->teacher)
            ->test(ClassRoster::class, ['courseId' => 'course-algebra-101'])
            ->mountTableAction('resetPassword', 'student-google-1001')
            ->callMountedTableAction()
            ->assertHasNoErrors();

        $this->assertDatabaseHas('audit_logs', [
            'student_google_user_id' => 'student-google-1001',
            'result' => 'success',
        ]);
    }

    public function test_reset_password_action_rejects_unenrolled_student(): void
    {
        Livewire::actingAs($this->teacher)
            ->test(ClassRoster::class, ['courseId' => 'course-algebra-101'])
            ->call('confirmResetPassword', [
                'full_name' => 'Morgan Blake',
                'email' => 'morgan.blake@k12louisa.org',
                'google_user_id' => 'student-google-3001',
            ])
            ->assertActionNotMounted()
            ->assertNotified();
    }
}
