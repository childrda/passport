<?php

namespace App\Services;

use App\Contracts\DirectoryService;
use App\DataTransferObjects\DirectoryUser;
use App\Exceptions\PasswordResetException;
use App\Services\Directory\MockDirectoryFixture;

/**
 * Mock Directory driver for local development before live Admin SDK integration.
 *
 * Does not store temporary passwords.
 */
class MockGoogleDirectoryService implements DirectoryService
{
    /**
     * Tracks changePasswordAtNextLogin flags after mock resets (never stores passwords).
     *
     * @var array<string, bool>
     */
    private array $changePasswordFlags = [];

    public function __construct(
        private readonly MockDirectoryFixture $fixture,
    ) {}

    public function findByClassroomUserId(string $classroomGoogleUserId): ?DirectoryUser
    {
        $user = $this->fixture->findByClassroomUserId($classroomGoogleUserId);

        if ($user === null) {
            return null;
        }

        return new DirectoryUser(
            id: $user->id,
            primaryEmail: $user->primaryEmail,
            fullName: $user->fullName,
            changePasswordAtNextLogin: $this->changePasswordFlags[$user->id] ?? false,
        );
    }

    public function resetPassword(string $directoryUserId, string $temporaryPassword, bool $changePasswordAtNextLogin = true): DirectoryUser
    {
        // Intentionally unused — passwords must never be retained by the mock.
        unset($temporaryPassword);

        $user = $this->fixture->findByDirectoryId($directoryUserId);

        if ($user === null) {
            throw PasswordResetException::resetFailed();
        }

        $this->changePasswordFlags[$directoryUserId] = $changePasswordAtNextLogin;

        return new DirectoryUser(
            id: $user->id,
            primaryEmail: $user->primaryEmail,
            fullName: $user->fullName,
            changePasswordAtNextLogin: $changePasswordAtNextLogin,
        );
    }

    public function wasChangePasswordAtNextLoginSet(string $directoryUserId): bool
    {
        return ($this->changePasswordFlags[$directoryUserId] ?? false) === true;
    }
}
