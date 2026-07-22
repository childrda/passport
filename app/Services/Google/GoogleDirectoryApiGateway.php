<?php

namespace App\Services\Google;

use App\Contracts\DirectoryApiGateway;
use App\Exceptions\DirectoryApiException;
use Google\Service\Directory;
use Google\Service\Directory\User as DirectoryUserResource;
use Google\Service\Exception as GoogleServiceException;
use GuzzleHttp\Exception\ConnectException;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Live Admin SDK Directory gateway for the **student** Workspace tenant.
 */
class GoogleDirectoryApiGateway implements DirectoryApiGateway
{
    public function __construct(
        private readonly StudentDirectoryClientFactory $clientFactory,
    ) {}

    public function getUser(string $userKey): ?array
    {
        $directory = $this->directory();

        try {
            $user = $directory->users->get($userKey, [
                'projection' => 'basic',
            ]);
        } catch (GoogleServiceException $e) {
            if ($e->getCode() === 404) {
                return null;
            }

            $this->logSanitizedGoogleError('directory.getUser', $e);

            throw DirectoryApiException::confirmedFailure(
                'Google Directory could not be reached. The request was denied.',
                $e,
            );
        } catch (DirectoryApiException $e) {
            throw $e;
        } catch (Throwable $e) {
            if ($this->isUncertainOutcome($e)) {
                $this->logSanitizedGoogleError('directory.getUser.unknown', $e);

                throw DirectoryApiException::outcomeUnknown(previous: $e);
            }

            $this->logSanitizedGoogleError('directory.getUser', $e);

            throw DirectoryApiException::confirmedFailure(previous: $e);
        }

        return $this->mapUser($user);
    }

    public function updatePassword(string $directoryUserId, string $temporaryPassword, bool $changePasswordAtNextLogin = true): array
    {
        $directory = $this->directory();

        $payload = new DirectoryUserResource;
        $payload->setPassword($temporaryPassword);
        $payload->setChangePasswordAtNextLogin($changePasswordAtNextLogin);

        // Do not retain the password beyond the API call.
        unset($temporaryPassword);

        try {
            $user = $directory->users->update($directoryUserId, $payload);
        } catch (GoogleServiceException $e) {
            $this->logSanitizedGoogleError('directory.updatePassword', $e);

            throw DirectoryApiException::confirmedFailure(
                'Google Directory could not be reached. The request was denied.',
                $e,
            );
        } catch (DirectoryApiException $e) {
            throw $e;
        } catch (Throwable $e) {
            if ($this->isUncertainOutcome($e)) {
                $this->logSanitizedGoogleError('directory.updatePassword.unknown', $e);

                throw DirectoryApiException::outcomeUnknown(previous: $e);
            }

            $this->logSanitizedGoogleError('directory.updatePassword', $e);

            throw DirectoryApiException::confirmedFailure(previous: $e);
        }

        return $this->mapUser($user);
    }

    private function directory(): Directory
    {
        return new Directory($this->clientFactory->makeDirectoryClient());
    }

    /**
     * @return array{id: string, primaryEmail: string, fullName: string, changePasswordAtNextLogin: bool}
     */
    private function mapUser(DirectoryUserResource $user): array
    {
        $name = $user->getName();

        return [
            'id' => (string) $user->getId(),
            'primaryEmail' => (string) ($user->getPrimaryEmail() ?? ''),
            'fullName' => (string) (
                $name?->getFullName()
                ?: $user->getPrimaryEmail()
                ?: 'Unknown user'
            ),
            'changePasswordAtNextLogin' => (bool) $user->getChangePasswordAtNextLogin(),
        ];
    }

    private function isUncertainOutcome(Throwable $e): bool
    {
        if ($e instanceof ConnectException) {
            return true;
        }

        $haystack = strtolower($e->getMessage().' '.($e->getPrevious()?->getMessage() ?? ''));

        foreach ([
            'timed out',
            'timeout',
            'connection reset',
            'connection aborted',
            'curl error 28',
            'operation timed out',
            'network is unreachable',
            'failed to connect',
            'connection refused',
        ] as $needle) {
            if (str_contains($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }

    private function logSanitizedGoogleError(string $context, Throwable $e): void
    {
        $message = $e->getMessage();
        $message = preg_replace('/Bearer\s+\S+/i', 'Bearer [redacted]', $message) ?? $message;
        $message = preg_replace('/password["\s:=]+\S+/i', 'password=[redacted]', $message) ?? $message;

        Log::warning('Google Directory API error', [
            'context' => $context,
            'exception_class' => $e::class,
            'message' => mb_substr($message, 0, 500),
            'code' => $e->getCode(),
        ]);
    }
}
