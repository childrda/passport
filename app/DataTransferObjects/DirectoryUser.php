<?php

namespace App\DataTransferObjects;

readonly class DirectoryUser
{
    public function __construct(
        public string $id,
        public string $primaryEmail,
        public string $fullName,
        public bool $changePasswordAtNextLogin = false,
    ) {}
}
