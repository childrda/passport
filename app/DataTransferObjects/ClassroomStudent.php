<?php

namespace App\DataTransferObjects;

readonly class ClassroomStudent
{
    /**
     * @param  string  $googleUserId  Classroom-provided Google user ID (not email).
     * @param  string  $email  Display/informational only — never authorize by email alone.
     */
    public function __construct(
        public string $googleUserId,
        public string $fullName,
        public string $email,
    ) {}
}
