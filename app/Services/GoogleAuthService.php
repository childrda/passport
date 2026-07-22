<?php

namespace App\Services;

use App\Enums\RoleName;
use App\Models\User;
use DomainException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Laravel\Socialite\Contracts\User as SocialiteUser;

class GoogleAuthService
{
    /**
     * OAuth scopes needed for sign-in and Classroom roster access.
     * Canonical list lives in config('reset.google.scopes').
     *
     * @return list<string>
     */
    public function scopes(): array
    {
        /** @var list<string> $scopes */
        $scopes = config('reset.google.scopes', []);

        return array_values($scopes);
    }

    /**
     * Extra OAuth parameters for offline refresh tokens.
     *
     * @return array<string, string>
     */
    public function withParameters(bool $forceConsent = false): array
    {
        return [
            'access_type' => 'offline',
            // Prefer select_account only; request consent deliberately when reconnecting.
            'prompt' => $forceConsent ? 'select_account consent' : 'select_account',
            'hd' => $this->staffDomain(),
        ];
    }

    public function staffDomain(): string
    {
        return Str::lower((string) config('reset.staff_domain'));
    }

    public function emailBelongsToStaffDomain(string $email): bool
    {
        $domain = Str::lower(Str::afterLast($email, '@'));

        return $domain !== '' && $domain === $this->staffDomain();
    }

    /**
     * Create or update the local user from a Google OAuth profile.
     * Does not auto-assign Teacher — roles and reset access are provisioned by admins.
     */
    public function syncUserFromGoogle(SocialiteUser $googleUser): User
    {
        $email = (string) $googleUser->getEmail();
        $googleId = (string) $googleUser->getId();

        if ($email === '' || ! $this->emailBelongsToStaffDomain($email)) {
            throw new DomainException(
                'Only '.config('reset.staff_domain').' Google accounts may sign in.'
            );
        }

        $user = User::query()->where('google_id', $googleId)->first();

        if ($user === null) {
            $user = User::query()->where('email', $email)->first();

            if ($user !== null && filled($user->google_id) && $user->google_id !== $googleId) {
                throw new DomainException(
                    'This email is already linked to a different Google account. Contact Technology Support.'
                );
            }
        }

        $tokenExpiresAt = null;
        if (isset($googleUser->expiresIn) && is_numeric($googleUser->expiresIn)) {
            $tokenExpiresAt = Carbon::now()->addSeconds((int) $googleUser->expiresIn);
        }

        $attributes = [
            'google_id' => $googleId,
            'name' => $googleUser->getName() ?: $email,
            'email' => $email,
            'avatar' => $googleUser->getAvatar(),
            'email_verified_at' => now(),
            'google_access_token' => $googleUser->token,
            'google_token_expires_at' => $tokenExpiresAt,
        ];

        if (! empty($googleUser->refreshToken)) {
            $attributes['google_refresh_token'] = $googleUser->refreshToken;
        }

        if ($user === null) {
            $user = User::query()->create($attributes);
        } else {
            $user->fill($attributes);
            $user->save();
        }

        return $user->fresh(['roles']);
    }
}
