<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\GoogleAuthService;
use DomainException;
use Filament\Facades\Filament;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class GoogleAuthController extends Controller
{
    public function redirect(Request $request, GoogleAuthService $googleAuth): Response
    {
        // Deliberate reconnect: /auth/google/redirect?consent=1 when refresh token is missing.
        $forceConsent = $request->boolean('consent');

        return Socialite::driver('google')
            ->scopes($googleAuth->scopes())
            ->with($googleAuth->withParameters(forceConsent: $forceConsent))
            ->redirect();
    }

    public function callback(GoogleAuthService $googleAuth): RedirectResponse
    {
        try {
            $googleUser = Socialite::driver('google')->user();
            $user = $googleAuth->syncUserFromGoogle($googleUser);
        } catch (DomainException $e) {
            return redirect()
                ->to(Filament::getLoginUrl() ?? '/admin/login')
                ->with('google_auth_error', $e->getMessage());
        } catch (Throwable $e) {
            report($e);

            return redirect()
                ->to(Filament::getLoginUrl() ?? '/admin/login')
                ->with(
                    'google_auth_error',
                    'Google sign-in failed. Please try again or contact an administrator.'
                );
        }

        if (! $user->roles()->exists()) {
            return redirect()
                ->to(Filament::getLoginUrl() ?? '/admin/login')
                ->with(
                    'google_auth_error',
                    'Your Google account is recognized, but it has not been provisioned for this application yet. Contact a System Administrator.'
                );
        }

        Auth::login($user, remember: true);

        return redirect()->intended(Filament::getUrl());
    }
}
