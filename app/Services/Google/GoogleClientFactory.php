<?php

namespace App\Services\Google;

use App\Exceptions\ClassroomApiException;
use App\Models\User;
use Google\Client as GoogleClient;
use Illuminate\Support\Carbon;
use Throwable;

class GoogleClientFactory
{
    /**
     * Build an authenticated Google API client for the given teacher.
     *
     * @throws ClassroomApiException
     */
    public function forUser(User $user): GoogleClient
    {
        if (blank($user->google_access_token) && blank($user->google_refresh_token)) {
            throw ClassroomApiException::missingOAuthToken();
        }

        $client = new GoogleClient;
        $client->setClientId((string) config('reset.google.oauth.client_id'));
        $client->setClientSecret((string) config('reset.google.oauth.client_secret'));
        $client->setRedirectUri((string) config('reset.google.oauth.redirect_uri'));
        $client->setAccessType('offline');
        $client->setScopes([
            'openid',
            'profile',
            'email',
            'https://www.googleapis.com/auth/classroom.courses.readonly',
            'https://www.googleapis.com/auth/classroom.rosters.readonly',
        ]);

        $token = [
            'access_token' => $user->google_access_token,
            'refresh_token' => $user->google_refresh_token,
        ];

        if ($user->google_token_expires_at !== null) {
            $expiresIn = max(0, $user->google_token_expires_at->getTimestamp() - time());
            $token['expires_in'] = $expiresIn;
            $token['created'] = time() - (3600 - min(3600, $expiresIn));
        }

        $client->setAccessToken($token);

        if ($client->isAccessTokenExpired()) {
            $this->refreshAccessToken($client, $user);
        }

        return $client;
    }

    /**
     * @throws ClassroomApiException
     */
    private function refreshAccessToken(GoogleClient $client, User $user): void
    {
        if (blank($user->google_refresh_token)) {
            throw ClassroomApiException::tokenRefreshFailed();
        }

        try {
            $newToken = $client->fetchAccessTokenWithRefreshToken($user->google_refresh_token);
        } catch (Throwable $e) {
            throw ClassroomApiException::tokenRefreshFailed($e);
        }

        if (isset($newToken['error'])) {
            throw ClassroomApiException::tokenRefreshFailed();
        }

        $client->setAccessToken($newToken);

        $user->google_access_token = $newToken['access_token'] ?? $user->google_access_token;

        if (! empty($newToken['refresh_token'])) {
            $user->google_refresh_token = $newToken['refresh_token'];
        }

        if (isset($newToken['expires_in'])) {
            $user->google_token_expires_at = Carbon::now()->addSeconds((int) $newToken['expires_in']);
        }

        $user->save();
    }
}
