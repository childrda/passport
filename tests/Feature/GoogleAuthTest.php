<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Models\User;
use App\Services\GoogleAuthService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Contracts\User as SocialiteUserContract;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
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

    public function test_sync_creates_teacher_for_new_staff_user(): void
    {
        $googleUser = $this->makeGoogleUser(
            id: 'google-123',
            email: 'new.teacher@lcps.k12.va.us',
            name: 'New Teacher',
        );

        $user = app(GoogleAuthService::class)->syncUserFromGoogle($googleUser);

        $this->assertTrue($user->isTeacher());
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
    }

    public function test_sync_preserves_existing_administrator_role(): void
    {
        $admin = User::factory()->create([
            'email' => 'admin@lcps.k12.va.us',
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
        $this->assertSame('google-admin', $user->google_id);
    }

    public function test_callback_logs_in_staff_user_and_redirects_to_panel(): void
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

    public function test_login_page_includes_google_sign_in(): void
    {
        $this->get('/admin/login')
            ->assertOk()
            ->assertSee('Sign in with Google')
            ->assertSee(route('auth.google.redirect'), false);
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
