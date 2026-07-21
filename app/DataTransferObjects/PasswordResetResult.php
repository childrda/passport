<?php

namespace App\DataTransferObjects;

readonly class PasswordResetResult
{
    public function __construct(
        public string $temporaryPassword,
        public string $studentName,
        public string $studentEmail,
        public string $directoryUserId,
        public bool $changePasswordAtNextLogin,
    ) {}
}
