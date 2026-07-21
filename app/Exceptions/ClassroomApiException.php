<?php

namespace App\Exceptions;

use Exception;
use Throwable;

class ClassroomApiException extends Exception
{
    public static function missingOAuthToken(): self
    {
        return new self(
            'Your Google Classroom access token is missing. Sign in again with Google to continue.'
        );
    }

    public static function tokenRefreshFailed(?Throwable $previous = null): self
    {
        return new self(
            'Unable to refresh your Google access token. Sign out and sign in again with Google.',
            previous: $previous,
        );
    }

    public static function requestFailed(string $detail = '', ?Throwable $previous = null): self
    {
        $suffix = $detail !== '' ? ' '.$detail : '';

        return new self(
            'Google Classroom could not be reached. The request was denied.'.$suffix,
            previous: $previous,
        );
    }
}
