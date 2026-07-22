<?php

namespace App\Contracts;

use App\DataTransferObjects\DirectoryUser;

/**
 * Application Directory service for the student Workspace tenant.
 */
interface DirectoryService
{
    /**
     * Discover a Directory user from a live Classroom roster email (student tenant).
     * Returns null when the user cannot be found (caller must deny the reset).
     */
    public function findByRosterEmail(string $email): ?DirectoryUser;

    /**
     * Reset the Workspace password and set changePasswordAtNextLogin.
     * Must be called with the immutable Directory user ID — never an email.
     *
     * @return DirectoryUser Updated canonical user (never includes the password).
     */
    public function resetPassword(string $directoryUserId, string $temporaryPassword, bool $changePasswordAtNextLogin = true): DirectoryUser;
}
