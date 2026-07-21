<?php

namespace App\Contracts;

/**
 * Thin gateway over Google Admin SDK Directory calls for testability.
 */
interface DirectoryApiGateway
{
    /**
     * @return array{id: string, primaryEmail: string, fullName: string, changePasswordAtNextLogin: bool}|null
     */
    public function getUserById(string $googleUserId): ?array;

    /**
     * Update password and changePasswordAtNextLogin. Never returns the password.
     *
     * @return array{id: string, primaryEmail: string, fullName: string, changePasswordAtNextLogin: bool}
     */
    public function updatePassword(string $directoryUserId, string $temporaryPassword, bool $changePasswordAtNextLogin = true): array;
}
