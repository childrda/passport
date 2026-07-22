<?php

namespace Database\Seeders;

use App\Enums\RoleName;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(RoleSeeder::class);

        $admin = User::factory()->create([
            'name' => 'Local Admin',
            'email' => 'admin@'.config('reset.staff_domain'),
            'reset_access_enabled' => true,
        ]);
        $admin->assignRole(RoleName::SystemAdministrator);

        $teacher = User::factory()->create([
            'name' => 'Local Teacher',
            'email' => 'teacher@'.config('reset.staff_domain'),
            'reset_access_enabled' => true,
        ]);
        $teacher->assignRole(RoleName::Teacher);

        $auditor = User::factory()->create([
            'name' => 'Local Auditor',
            'email' => 'auditor@'.config('reset.staff_domain'),
        ]);
        $auditor->assignRole(RoleName::Auditor);
    }
}
