<?php

namespace App\Services;

use App\Enums\RoleName;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Laravel\Socialite\Contracts\User as SocialiteUser;

class GoogleAuthService
{
    /**
     * OAuth scopes needed for sign-in and (later) Classroom roster access.
     *
     * @return list<string>
     */
    public function scopes(): array
    {
        return [
            'openid',
            'profile',
            'email',
            'https://www.googleapis.com/auth/classroom.courses.readonly',
            'https://www.googleapis.com/auth/classroom.rosters.readonly',
        ];
    }

    /**
     * Extra OAuth parameters for offline refresh tokens.
     *
     * @return array<string, string>
     */
    public function withParameters(): array
    {
        return [
            'access_type' => 'offline',
            'prompt' => 'select_account consent',
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
     * Create or update the local user from a Google OAuth profile and assign Teacher if needed.
     */
    public function syncUserFromGoogle(SocialiteUser $googleUser): User
    {
        $email = (string) $googleUser->getEmail();

        if ($email === '' || ! $this->emailBelongsToStaffDomain($email)) {
            throw new \DomainException(
                'Only '.config('reset.staff_domain').' Google accounts may sign in.'
            );
        }

        $user = User::query()
            ->where(function ($query) use ($googleUser, $email): void {
                $query->where('google_id', $googleUser->getId())
                    ->orWhere('email', $email);
            })
            ->first();

        $tokenExpiresAt = null;
        if (isset($googleUser->expiresIn) && is_numeric($googleUser->expiresIn)) {
            $tokenExpiresAt = Carbon::now()->addSeconds((int) $googleUser->expiresIn);
        }

        $attributes = [
            'google_id' => $googleUser->getId(),
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

        if (! $user->roles()->exists()) {
            $user->assignRole(RoleName::Teacher);
        }

        return $user->fresh(['roles']);
    }
}
