<?php

namespace App\Exceptions;

use Exception;
use Throwable;

class DirectoryApiException extends Exception
{
    public function __construct(
        string $message = '',
        int $code = 0,
        ?Throwable $previous = null,
        public readonly bool $outcomeUnknown = false,
    ) {
        parent::__construct($message, $code, $previous);
    }

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

        return self::confirmedFailure(
            'Google Directory could not be reached. The request was denied.'.$suffix,
            $previous,
        );
    }

    public static function confirmedFailure(string $message = '', ?Throwable $previous = null): self
    {
        $message = $message !== ''
            ? $message
            : 'Google Directory could not be reached. The request was denied.';

        return new self($message, previous: $previous, outcomeUnknown: false);
    }

    public static function outcomeUnknown(string $detail = '', ?Throwable $previous = null): self
    {
        $suffix = $detail !== '' ? ' '.$detail : '';

        return new self(
            'Google Directory response could not be confirmed.'.$suffix,
            previous: $previous,
            outcomeUnknown: true,
        );
    }
}
