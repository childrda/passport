<?php

namespace App\Exceptions;

use Exception;
use Throwable;

class DirectoryApiException extends Exception
{
    public static function missingConfiguration(string $detail = ''): self
    {
        $suffix = $detail !== '' ? ' '.$detail : '';

        return new self(
            'Google Directory is not configured.'.$suffix
        );
    }

    public static function credentialsUnreadable(string $path): self
    {
        return new self(
            "Google service-account credentials could not be read at [{$path}]."
        );
    }

    public static function requestFailed(string $detail = '', ?Throwable $previous = null): self
    {
        $suffix = $detail !== '' ? ' '.$detail : '';

        return new self(
            'Google Directory could not be reached. The request was denied.'.$suffix,
            previous: $previous,
        );
    }
}
