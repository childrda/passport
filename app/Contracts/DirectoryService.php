<?php

namespace App\Contracts;

use App\DataTransferObjects\DirectoryUser;

interface DirectoryService
{
    /**
     * Resolve a Classroom Google user ID to the canonical Directory user.
     * Returns null when the user cannot be found (caller must deny the reset).
     */
    public function findByClassroomUserId(string $classroomGoogleUserId): ?DirectoryUser;

    /**
     * Reset the Workspace password and set changePasswordAtNextLogin.
     *
     * @return DirectoryUser Updated canonical user (never includes the password).
     */
    public function resetPassword(string $directoryUserId, string $temporaryPassword, bool $changePasswordAtNextLogin = true): DirectoryUser;
}
