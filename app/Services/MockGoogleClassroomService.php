<?php

namespace App\Services;

use App\Contracts\ClassroomService;
use App\DataTransferObjects\ClassroomCourse;
use App\DataTransferObjects\ClassroomStudent;
use App\Models\User;
use App\Services\Classroom\MockClassroomFixture;
use Illuminate\Support\Collection;

/**
 * Mock Classroom driver for local development and UI work before live Google APIs.
 */
class MockGoogleClassroomService implements ClassroomService
{
    public function __construct(
        private readonly MockClassroomFixture $fixture,
    ) {}

    public function coursesForTeacher(User $teacher): Collection
    {
        return $this->fixture->coursesForEmail($teacher->email);
    }

    public function studentsForCourse(User $teacher, string $courseId): Collection
    {
        if (! $this->teacherTeachesCourse($teacher, $courseId)) {
            return collect();
        }

        return $this->fixture->studentsForEmailAndCourse($teacher->email, $courseId);
    }

    public function teacherTeachesCourse(User $teacher, string $courseId): bool
    {
        return $this->fixture->teacherHasCourse($teacher->email, $courseId);
    }

    public function studentEnrolledInCourse(User $teacher, string $courseId, string $studentGoogleUserId): bool
    {
        if (! $this->teacherTeachesCourse($teacher, $courseId)) {
            return false;
        }

        return $this->studentsForCourse($teacher, $courseId)->contains(
            fn (ClassroomStudent $student): bool => $student->googleUserId === $studentGoogleUserId
        );
    }
}
