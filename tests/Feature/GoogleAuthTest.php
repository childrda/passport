<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Filament\Pages\Auth\Login;
use App\Filament\Pages\MyClasses;
use App\Models\User;
use App\Services\GoogleAuthService;
use App\Services\StudentPasswordResetService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Contracts\User as SocialiteUserContract;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Livewire\Livewire;
use Mockery;
use Tests\TestCase;

class GoogleAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        config([
            'reset.staff_domain' => 'lcps.k12.va.us',
            'reset.student_domain' => 'k12louisa.org',
            'reset.classroom_driver' => 'mock',
            'reset.google.oauth.client_id' => 'test-client-id',
            'reset.google.oauth.client_secret' => 'test-client-secret',
            'reset.google.oauth.redirect_uri' => 'http://localhost/auth/google/callback',
            'services.google' => [
                'client_id' => 'test-client-id',
                'client_secret' => 'test-client-secret',
                'redirect' => 'http://localhost/auth/google/callback',
            ],
        ]);
    }

    public function test_staff_domain_emails_are_accepted(): void
    {
        $service = app(GoogleAuthService::class);

        $this->assertTrue($service->emailBelongsToStaffDomain('teacher@lcps.k12.va.us'));
        $this->assertTrue($service->emailBelongsToStaffDomain('Teacher@LCPS.K12.VA.US'));
    }

    public function test_non_staff_domain_emails_are_rejected(): void
    {
        $service = app(GoogleAuthService::class);

        $this->assertFalse($service->emailBelongsToStaffDomain('student@k12louisa.org'));
        $this->assertFalse($service->emailBelongsToStaffDomain('outsider@gmail.com'));
    }

    public function test_sync_creates_user_as_enabled_teacher(): void
    {
        $googleUser = $this->makeGoogleUser(
            id: 'google-123',
            email: 'new.teacher@lcps.k12.va.us',
            name: 'New Teacher',
        );

        $user = app(GoogleAuthService::class)->syncUserFromGoogle($googleUser);

        $this->assertTrue($user->isTeacher());
        $this->assertTrue($user->reset_access_enabled);
        $this->assertTrue($user->canResetStudentPasswords());
        $this->assertFalse($user->isSystemAdministrator());
        $this->assertFalse($user->isAuditor());
        $this->assertSame(1, $user->roles()->count());
        $this->assertSame('google-123', $user->google_id);
        $this->assertSame('new.teacher@lcps.k12.va.us', $user->email);
        $this->assertNotNull($user->google_access_token);
        $this->assertNotNull($user->google_refresh_token);
    }

    public function test_sync_rejects_student_domain_accounts(): void
    {
        $googleUser = $this->makeGoogleUser(
            id: 'google-student',
            email: 'student@k12louisa.org',
            name: 'Student',
        );

        $this->expectException(\DomainException::class);

        app(GoogleAuthService::class)->syncUserFromGoogle($googleUser);

        $this->assertDatabaseCount('users', 0);
    }

    public function test_sync_preserves_existing_administrator_role(): void
    {
        $admin = User::factory()->create([
            'email' => 'admin@lcps.k12.va.us',
            'reset_access_enabled' => false,
        ]);
        $admin->assignRole(RoleName::SystemAdministrator);

        $googleUser = $this->makeGoogleUser(
            id: 'google-admin',
            email: 'admin@lcps.k12.va.us',
            name: 'Local Admin',
        );

        $user = app(GoogleAuthService::class)->syncUserFromGoogle($googleUser);

        $this->assertTrue($user->isSystemAdministrator());
        $this->assertFalse($user->isTeacher());
        $this->assertFalse($user->reset_access_enabled);
        $this->assertSame('google-admin', $user->google_id);
    }

    public function test_relogin_does_not_restore_revoked_reset_access(): void
    {
        $existing = User::factory()->create([
            'email' => 'revoked@lcps.k12.va.us',
            'google_id' => 'google-revoked',
            'reset_access_enabled' => false,
        ]);
        $existing->assignRole(RoleName::Teacher);

        $googleUser = $this->makeGoogleUser(
            id: 'google-revoked',
            email: 'revoked@lcps.k12.va.us',
            name: 'Revoked Teacher',
        );

        $user = app(GoogleAuthService::class)->syncUserFromGoogle($googleUser);

        $this->assertFalse($user->reset_access_enabled);
        $this->assertFalse($user->canResetStudentPasswords());
        $this->assertTrue($user->isTeacher());
    }

    public function test_relogin_does_not_restore_removed_roles(): void
    {
        $existing = User::factory()->create([
            'email' => 'norole@lcps.k12.va.us',
            'google_id' => 'google-norole',
            'reset_access_enabled' => true,
        ]);

        $this->assertFalse($existing->roles()->exists());

        $googleUser = $this->makeGoogleUser(
            id: 'google-norole',
            email: 'norole@lcps.k12.va.us',
            name: 'No Role',
        );

        $user = app(GoogleAuthService::class)->syncUserFromGoogle($googleUser);

        $this->assertFalse($user->roles()->exists());
        $this->assertFalse($user->isTeacher());
        $this->assertTrue($user->reset_access_enabled);
    }

    public function test_callback_logs_in_newly_auto_provisioned_staff_user(): void
    {
        $googleUser = $this->makeGoogleUser(
            id: 'google-callback',
            email: 'callback.teacher@lcps.k12.va.us',
            name: 'Callback Teacher',
        );

        $this->mockSocialiteUser($googleUser);

        $this->get('/auth/google/callback')
            ->assertRedirect('/admin');

        $this->assertAuthenticated();
        $this->assertTrue(auth()->user()->isTeacher());
        $this->assertTrue(auth()->user()->reset_access_enabled);
        $this->assertTrue(auth()->user()->canResetStudentPasswords());
    }

    public function test_callback_logs_in_provisioned_staff_user(): void
    {
        $existing = User::factory()->create([
            'email' => 'callback.teacher@lcps.k12.va.us',
            'reset_access_enabled' => true,
        ]);
        $existing->assignRole(RoleName::Teacher);

        $googleUser = $this->makeGoogleUser(
            id: 'google-callback',
            email: 'callback.teacher@lcps.k12.va.us',
            name: 'Callback Teacher',
        );

        $this->mockSocialiteUser($googleUser);

        $this->get('/auth/google/callback')
            ->assertRedirect('/admin');

        $this->assertAuthenticated();
        $this->assertTrue(auth()->user()->isTeacher());
    }

    public function test_callback_rejects_staff_user_without_roles(): void
    {
        User::factory()->create([
            'email' => 'stripped@lcps.k12.va.us',
            'google_id' => 'google-stripped',
            'reset_access_enabled' => false,
        ]);

        $googleUser = $this->makeGoogleUser(
            id: 'google-stripped',
            email: 'stripped@lcps.k12.va.us',
            name: 'Stripped',
        );

        $this->mockSocialiteUser($googleUser);

        $this->get('/auth/google/callback')
            ->assertRedirect('/admin/login');

        $this->assertGuest();
        $this->assertFalse(
            User::where('email', 'stripped@lcps.k12.va.us')->first()->roles()->exists()
        );
    }

    public function test_callback_rejects_non_staff_user(): void
    {
        $googleUser = $this->makeGoogleUser(
            id: 'google-outsider',
            email: 'person@gmail.com',
            name: 'Outsider',
        );

        $this->mockSocialiteUser($googleUser);

        $this->get('/auth/google/callback')
            ->assertRedirect('/admin/login');

        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
    }

    public function test_login_page_includes_google_sign_in_only(): void
    {
        $this->get('/admin/login')
            ->assertOk()
            ->assertSee('Sign in with Google')
            ->assertSee('Use your lcps.k12.va.us account.')
            ->assertSee(route('auth.google.redirect'), false)
            ->assertDontSee('wire:submit="authenticate"', false)
            ->assertDontSee('type="password"', false);
    }

    public function test_local_credential_login_is_rejected(): void
    {
        $user = User::factory()->create([
            'email' => 'local@lcps.k12.va.us',
            'password' => 'password',
            'reset_access_enabled' => true,
        ]);
        $user->assignRole(RoleName::Teacher);

        Livewire::test(Login::class)
            ->set('data.email', 'local@lcps.k12.va.us')
            ->set('data.password', 'password')
            ->call('authenticate')
            ->assertHasErrors(['data.email']);

        $this->assertGuest();
    }

    public function test_auto_provisioned_teacher_with_no_courses_cannot_reset(): void
    {
        $googleUser = $this->makeGoogleUser(
            id: 'google-empty',
            email: 'empty.teacher@lcps.k12.va.us',
            name: 'Empty Teacher',
        );

        $user = app(GoogleAuthService::class)->syncUserFromGoogle($googleUser);

        $this->assertTrue($user->canResetStudentPasswords());

        $this->actingAs($user)
            ->get(MyClasses::getUrl())
            ->assertOk()
            ->assertSee('No classes found')
            ->assertDontSee('Algebra I');

        $this->expectException(\App\Exceptions\PasswordResetException::class);

        app(StudentPasswordResetService::class)->reset(
            $user,
            'course-algebra-101',
            'student-google-1001',
        );
    }

    public function test_google_redirect_route_is_registered(): void
    {
        $this->assertNotNull(route('auth.google.redirect'));
        $this->assertNotNull(route('auth.google.callback'));
    }

    private function makeGoogleUser(string $id, string $email, string $name): SocialiteUser
    {
        $user = new SocialiteUser;
        $user->map([
            'id' => $id,
            'name' => $name,
            'email' => $email,
            'avatar' => 'https://example.com/avatar.png',
        ]);
        $user->token = 'access-token';
        $user->refreshToken = 'refresh-token';
        $user->expiresIn = 3600;

        return $user;
    }

    private function mockSocialiteUser(SocialiteUserContract $googleUser): void
    {
        $driver = Mockery::mock();
        $driver->shouldReceive('user')->andReturn($googleUser);

        Socialite::shouldReceive('driver')
            ->with('google')
            ->andReturn($driver);
    }
}
