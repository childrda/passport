<?php

namespace App\DataTransferObjects;

readonly class ClassroomCourse
{
    public function __construct(
        public string $id,
        public string $name,
        public ?string $section = null,
        public string $courseState = 'ACTIVE',
    ) {}
}
