<?php

namespace App\Contracts;

/**
 * Thin gateway over Google Admin SDK Directory calls for testability.
 * Operates against the student Workspace tenant only.
 */
interface DirectoryApiGateway
{
    /**
     * Look up a Directory user by primary email, alias email, or immutable user ID.
     *
     * @return array{id: string, primaryEmail: string, fullName: string, changePasswordAtNextLogin: bool}|null
     */
    public function getUser(string $userKey): ?array;

    /**
     * Update password and changePasswordAtNextLogin. Never returns the password.
     * $directoryUserId must be the immutable Directory user ID.
     *
     * @return array{id: string, primaryEmail: string, fullName: string, changePasswordAtNextLogin: bool}
     */
    public function updatePassword(string $directoryUserId, string $temporaryPassword, bool $changePasswordAtNextLogin = true): array;
}
