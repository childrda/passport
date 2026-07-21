<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\GoogleAuthService;
use DomainException;
use Filament\Facades\Filament;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class GoogleAuthController extends Controller
{
    public function redirect(GoogleAuthService $googleAuth): Response
    {
        return Socialite::driver('google')
            ->scopes($googleAuth->scopes())
            ->with($googleAuth->withParameters())
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

        Auth::login($user, remember: true);

        return redirect()->intended(Filament::getUrl());
    }
}
