<?php

namespace App\Services;

use App\Contracts\ClassroomApiGateway;
use App\Contracts\ClassroomService;
use App\DataTransferObjects\ClassroomCourse;
use App\DataTransferObjects\ClassroomStudent;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Live Google Classroom API driver. Uses the signed-in teacher's OAuth token.
 *
 * Authorization checks let ClassroomApiException bubble so callers can deny
 * resets / show errors when Google is unreachable.
 */
class GoogleClassroomService implements ClassroomService
{
    public function __construct(
        private readonly ClassroomApiGateway $api,
    ) {}

    public function coursesForTeacher(User $teacher): Collection
    {
        return collect($this->api->listActiveCourses($teacher))
            ->map(fn (array $course): ClassroomCourse => new ClassroomCourse(
                id: $course['id'],
                name: $course['name'],
                section: $course['section'],
                courseState: $course['courseState'],
            ))
            ->values();
    }

    public function studentsForCourse(User $teacher, string $courseId): Collection
    {
        if (! $this->teacherTeachesCourse($teacher, $courseId)) {
            return collect();
        }

        return collect($this->api->listStudents($teacher, $courseId))
            ->map(fn (array $student): ClassroomStudent => new ClassroomStudent(
                googleUserId: $student['userId'],
                fullName: $student['fullName'],
                email: $student['email'],
            ))
            ->values();
    }

    public function teacherTeachesCourse(User $teacher, string $courseId): bool
    {
        $teacherIds = $this->api->listTeacherUserIds($teacher, $courseId);
        $teacherGoogleId = (string) $teacher->google_id;

        if ($teacherGoogleId !== '' && in_array($teacherGoogleId, $teacherIds, true)) {
            return true;
        }

        // Fallback when google_id is unavailable: courses the teacher currently teaches.
        return collect($this->api->listActiveCourses($teacher))
            ->contains(fn (array $course): bool => $course['id'] === $courseId);
    }

    public function studentEnrolledInCourse(User $teacher, string $courseId, string $studentGoogleUserId): bool
    {
        if (! $this->teacherTeachesCourse($teacher, $courseId)) {
            return false;
        }

        return collect($this->api->listStudents($teacher, $courseId))
            ->contains(fn (array $student): bool => $student['userId'] === $studentGoogleUserId);
    }
}
