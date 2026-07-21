<?php

namespace App\Contracts;

use App\DataTransferObjects\ClassroomCourse;
use App\DataTransferObjects\ClassroomStudent;
use App\Models\User;
use Illuminate\Support\Collection;

interface ClassroomService
{
    /**
     * Courses the teacher currently teaches (ACTIVE).
     *
     * @return Collection<int, ClassroomCourse>
     */
    public function coursesForTeacher(User $teacher): Collection;

    /**
     * Students enrolled in a course, only if the teacher teaches that course.
     *
     * @return Collection<int, ClassroomStudent>
     */
    public function studentsForCourse(User $teacher, string $courseId): Collection;

    /**
     * Live-style check: signed-in user is a teacher/co-teacher of the course.
     */
    public function teacherTeachesCourse(User $teacher, string $courseId): bool;

    /**
     * Live-style check: student (by Classroom Google user ID) is enrolled in the course
     * and the teacher teaches that course.
     */
    public function studentEnrolledInCourse(User $teacher, string $courseId, string $studentGoogleUserId): bool;
}
