<?php

namespace App\Services\Google;

use App\Contracts\DirectoryApiGateway;
use App\Exceptions\DirectoryApiException;
use Google\Service\Directory;
use Google\Service\Directory\User as DirectoryUserResource;
use Google\Service\Exception as GoogleServiceException;
use Throwable;

class GoogleDirectoryApiGateway implements DirectoryApiGateway
{
    public function __construct(
        private readonly GoogleServiceAccountClientFactory $clientFactory,
    ) {}

    public function getUserById(string $googleUserId): ?array
    {
        $directory = $this->directory();

        try {
            $user = $directory->users->get($googleUserId, [
                'projection' => 'basic',
            ]);
        } catch (GoogleServiceException $e) {
            if ($e->getCode() === 404) {
                return null;
            }

            throw DirectoryApiException::requestFailed($this->googleErrorDetail($e), $e);
        } catch (DirectoryApiException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw DirectoryApiException::requestFailed(previous: $e);
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
            throw DirectoryApiException::requestFailed($this->googleErrorDetail($e), $e);
        } catch (DirectoryApiException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw DirectoryApiException::requestFailed(previous: $e);
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

    private function googleErrorDetail(GoogleServiceException $e): string
    {
        $message = trim($e->getMessage());

        return $message !== '' ? "({$message})" : '';
    }
}
