<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Filament\Pages\MyClasses;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_roles_are_seeded(): void
    {
        $this->seed(\Database\Seeders\RoleSeeder::class);

        foreach (RoleName::cases() as $role) {
            $this->assertDatabaseHas('roles', [
                'name' => $role->value,
                'label' => $role->label(),
            ]);
        }
    }

    public function test_user_can_be_assigned_a_role(): void
    {
        $this->seed(\Database\Seeders\RoleSeeder::class);

        $user = User::factory()->create();
        $user->assignRole(RoleName::Teacher);

        $this->assertTrue($user->isTeacher());
        $this->assertFalse($user->isSystemAdministrator());
        $this->assertTrue($user->hasRole(RoleName::Teacher));
    }

    public function test_reset_config_is_loaded_from_environment(): void
    {
        $this->assertSame('lcps.k12.va.us', config('reset.staff_domain'));
        $this->assertSame('k12louisa.org', config('reset.student_domain'));
        $this->assertSame(10, config('reset.temp_password.length'));
        $this->assertNotEmpty(config('reset.temp_password.alphabet'));
    }

    public function test_admin_panel_login_page_is_reachable(): void
    {
        $this->get('/admin/login')->assertOk();
    }

    public function test_user_without_role_cannot_access_panel(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/admin')
            ->assertForbidden();
    }

    public function test_user_with_role_can_access_panel(): void
    {
        $this->seed(\Database\Seeders\RoleSeeder::class);

        $user = User::factory()->create();
        $user->assignRole(RoleName::Teacher);

        $this->actingAs($user)
            ->get('/admin')
            ->assertRedirect(MyClasses::getUrl());
    }

    public function test_three_application_roles_exist_as_models(): void
    {
        $this->seed(\Database\Seeders\RoleSeeder::class);

        $this->assertSame(3, Role::query()->count());
    }
}
