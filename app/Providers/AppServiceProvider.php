<?php

namespace App\Providers;

use App\Contracts\ClassroomService;
use App\Contracts\ClassroomApiGateway;
use App\Contracts\DirectoryApiGateway;
use App\Contracts\DirectoryService;
use App\Models\User;
use App\Services\Google\GoogleClassroomApiGateway;
use App\Services\Google\GoogleDirectoryApiGateway;
use App\Services\GoogleClassroomService;
use App\Services\GoogleDirectoryService;
use App\Services\MockGoogleClassroomService;
use App\Services\MockGoogleDirectoryService;
use App\Services\TemporaryPasswordGenerator;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(ClassroomService::class, function ($app): ClassroomService {
            return match (config('reset.classroom_driver', 'mock')) {
                'mock' => $app->make(MockGoogleClassroomService::class),
                'google' => $app->make(GoogleClassroomService::class),
                default => throw new InvalidArgumentException(
                    'Invalid CLASSROOM_DRIVER. Use mock or google.'
                ),
            };
        });

        $this->app->bind(ClassroomApiGateway::class, GoogleClassroomApiGateway::class);

        $this->app->bind(DirectoryApiGateway::class, GoogleDirectoryApiGateway::class);

        $this->app->bind(DirectoryService::class, function ($app): DirectoryService {
            return match (config('reset.directory_driver', 'mock')) {
                'mock' => $app->make(MockGoogleDirectoryService::class),
                'google' => $app->make(GoogleDirectoryService::class),
                default => throw new InvalidArgumentException(
                    'Invalid DIRECTORY_DRIVER. Use mock or google.'
                ),
            };
        });

        $this->app->singleton(MockGoogleDirectoryService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Socialite reads config('services.google'); keep a single source in config/reset.php.
        Config::set('services.google', [
            'client_id' => config('reset.google.oauth.client_id'),
            'client_secret' => config('reset.google.oauth.client_secret'),
            'redirect' => config('reset.google.oauth.redirect_uri'),
        ]);

        Gate::define('reset-student-password', function (User $user): bool {
            return $user->canResetStudentPasswords();
        });

        TemporaryPasswordGenerator::validateConfiguration();
    }
}
