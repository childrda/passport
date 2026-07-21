<?php

namespace App\Services\Classroom;

use App\DataTransferObjects\ClassroomCourse;
use App\DataTransferObjects\ClassroomStudent;
use Illuminate\Support\Collection;

/**
 * Fixture Classroom data shaped like Google Classroom API resources.
 * Keyed by teacher email (lowercase). Used only when CLASSROOM_DRIVER=mock.
 */
class MockClassroomFixture
{
    /**
     * @return array<string, array{courses: list<array<string, mixed>>, students: array<string, list<array<string, mixed>>>}>
     */
    public function all(): array
    {
        $studentDomain = config('reset.student_domain');

        return [
            'teacher@'.config('reset.staff_domain') => [
                'courses' => [
                    [
                        'id' => 'course-algebra-101',
                        'name' => 'Algebra I',
                        'section' => 'Period 2',
                        'courseState' => 'ACTIVE',
                    ],
                    [
                        'id' => 'course-biology-201',
                        'name' => 'Biology',
                        'section' => 'Period 4',
                        'courseState' => 'ACTIVE',
                    ],
                ],
                'students' => [
                    'course-algebra-101' => [
                        [
                            'userId' => 'student-google-1001',
                            'fullName' => 'Alex Rivera',
                            'email' => 'alex.rivera@'.$studentDomain,
                        ],
                        [
                            'userId' => 'student-google-1002',
                            'fullName' => 'Jordan Lee',
                            'email' => 'jordan.lee@'.$studentDomain,
                        ],
                        [
                            'userId' => 'student-google-1003',
                            'fullName' => 'Sam Patel',
                            'email' => 'sam.patel@'.$studentDomain,
                        ],
                    ],
                    'course-biology-201' => [
                        [
                            'userId' => 'student-google-1002',
                            'fullName' => 'Jordan Lee',
                            'email' => 'jordan.lee@'.$studentDomain,
                        ],
                        [
                            'userId' => 'student-google-2001',
                            'fullName' => 'Casey Nguyen',
                            'email' => 'casey.nguyen@'.$studentDomain,
                        ],
                    ],
                ],
            ],
            'other.teacher@'.config('reset.staff_domain') => [
                'courses' => [
                    [
                        'id' => 'course-history-301',
                        'name' => 'US History',
                        'section' => 'Period 1',
                        'courseState' => 'ACTIVE',
                    ],
                ],
                'students' => [
                    'course-history-301' => [
                        [
                            'userId' => 'student-google-3001',
                            'fullName' => 'Morgan Blake',
                            'email' => 'morgan.blake@'.$studentDomain,
                        ],
                        [
                            'userId' => 'student-google-3002',
                            'fullName' => 'Taylor Brooks',
                            'email' => 'taylor.brooks@'.$studentDomain,
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @return Collection<int, ClassroomCourse>
     */
    public function coursesForEmail(string $email): Collection
    {
        $data = $this->all()[strtolower($email)] ?? null;

        if ($data === null) {
            return collect();
        }

        return collect($data['courses'])
            ->filter(fn (array $course): bool => ($course['courseState'] ?? '') === 'ACTIVE')
            ->map(fn (array $course): ClassroomCourse => new ClassroomCourse(
                id: $course['id'],
                name: $course['name'],
                section: $course['section'] ?? null,
                courseState: $course['courseState'],
            ))
            ->values();
    }

    /**
     * @return Collection<int, ClassroomStudent>
     */
    public function studentsForEmailAndCourse(string $email, string $courseId): Collection
    {
        $data = $this->all()[strtolower($email)] ?? null;

        if ($data === null) {
            return collect();
        }

        $students = $data['students'][$courseId] ?? [];

        return collect($students)
            ->map(fn (array $student): ClassroomStudent => new ClassroomStudent(
                googleUserId: $student['userId'],
                fullName: $student['fullName'],
                email: $student['email'],
            ))
            ->values();
    }

    public function teacherHasCourse(string $email, string $courseId): bool
    {
        return $this->coursesForEmail($email)->contains(
            fn (ClassroomCourse $course): bool => $course->id === $courseId
        );
    }
}
