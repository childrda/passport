<?php

namespace App\Services;

use App\Contracts\DirectoryApiGateway;
use App\Contracts\DirectoryService;
use App\DataTransferObjects\DirectoryUser;

/**
 * Live Google Admin SDK Directory API driver for the **student** Workspace tenant.
 * Uses a student-tenant service account with domain-wide delegation.
 */
class GoogleDirectoryService implements DirectoryService
{
    public function __construct(
        private readonly DirectoryApiGateway $api,
    ) {}

    public function findByRosterEmail(string $email): ?DirectoryUser
    {
        $user = $this->api->getUser($email);

        if ($user === null) {
            return null;
        }

        return new DirectoryUser(
            id: $user['id'],
            primaryEmail: $user['primaryEmail'],
            fullName: $user['fullName'],
            changePasswordAtNextLogin: $user['changePasswordAtNextLogin'],
        );
    }

    public function resetPassword(string $directoryUserId, string $temporaryPassword, bool $changePasswordAtNextLogin = true): DirectoryUser
    {
        $user = $this->api->updatePassword(
            $directoryUserId,
            $temporaryPassword,
            $changePasswordAtNextLogin,
        );

        // Never retain the password in this service.
        unset($temporaryPassword);

        return new DirectoryUser(
            id: $user['id'],
            primaryEmail: $user['primaryEmail'],
            fullName: $user['fullName'],
            changePasswordAtNextLogin: $user['changePasswordAtNextLogin'],
        );
    }
}
