<?php

namespace App\Contracts;

use App\Models\User;

/**
 * Thin gateway over Google Classroom HTTP calls for testability.
 */
interface ClassroomApiGateway
{
    /**
     * @return list<array{id: string, name: string, section: ?string, courseState: string}>
     */
    public function listActiveCourses(User $teacher): array;

    /**
     * @return list<array{userId: string, fullName: string, email: string}>
     */
    public function listStudents(User $teacher, string $courseId): array;

    /**
     * Teacher / co-teacher Google user IDs for the course.
     *
     * @return list<string>
     */
    public function listTeacherUserIds(User $teacher, string $courseId): array;
}
