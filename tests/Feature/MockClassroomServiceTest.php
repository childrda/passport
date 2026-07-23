<?php

namespace Tests\Feature;

use App\Contracts\ClassroomService;
use App\Models\User;
use App\Services\MockGoogleClassroomService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MockClassroomServiceTest extends TestCase
{
    use RefreshDatabase;

    private ClassroomService $classroom;

    private User $teacher;

    private User $otherTeacher;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        config([
            'reset.staff_domain' => 'lcps.k12.va.us',
            'reset.student_domain' => 'k12louisa.org',
            'reset.classroom_driver' => 'mock',
        ]);

        $this->classroom = app(ClassroomService::class);

        $this->assertInstanceOf(MockGoogleClassroomService::class, $this->classroom);

        $this->teacher = User::factory()->create([
            'email' => 'teacher@lcps.k12.va.us',
            'name' => 'Local Teacher',
        ]);

        $this->otherTeacher = User::factory()->create([
            'email' => 'other.teacher@lcps.k12.va.us',
            'name' => 'Other Teacher',
        ]);
    }

    public function test_teacher_can_see_their_own_classes(): void
    {
        $courses = $this->classroom->coursesForTeacher($this->teacher);

        $this->assertCount(2, $courses);
        $this->assertTrue($courses->contains(fn ($course) => $course->id === 'course-algebra-101'));
        $this->assertTrue($courses->contains(fn ($course) => $course->id === 'course-biology-201'));
        $this->assertFalse($courses->contains(fn ($course) => $course->id === 'course-history-301'));
    }

    public function test_teacher_can_see_students_enrolled_in_their_classes(): void
    {
        $students = $this->classroom->studentsForCourse($this->teacher, 'course-algebra-101');

        $this->assertCount(4, $students);
        $this->assertTrue($students->contains(fn ($s) => $s->googleUserId === 'student-google-1001'));
        $this->assertTrue($students->contains(fn ($s) => $s->email === 'alex.rivera@k12louisa.org'));
        $this->assertTrue($students->contains(fn ($s) => $s->googleUserId === 'student-google-external'));
    }

    public function test_teacher_cannot_access_another_teachers_roster(): void
    {
        $courses = $this->classroom->coursesForTeacher($this->teacher);
        $this->assertFalse($courses->contains(fn ($course) => $course->id === 'course-history-301'));

        $students = $this->classroom->studentsForCourse($this->teacher, 'course-history-301');
        $this->assertCount(0, $students);

        $this->assertFalse(
            $this->classroom->teacherTeachesCourse($this->teacher, 'course-history-301')
        );
        $this->assertFalse(
            $this->classroom->studentEnrolledInCourse(
                $this->teacher,
                'course-history-301',
                'student-google-3001'
            )
        );
    }

    public function test_other_teacher_sees_only_their_courses_and_students(): void
    {
        $courses = $this->classroom->coursesForTeacher($this->otherTeacher);

        $this->assertCount(1, $courses);
        $this->assertSame('course-history-301', $courses->first()->id);

        $students = $this->classroom->studentsForCourse($this->otherTeacher, 'course-history-301');
        $this->assertCount(2, $students);

        $this->assertCount(
            0,
            $this->classroom->studentsForCourse($this->otherTeacher, 'course-algebra-101')
        );
    }

    public function test_unknown_teacher_has_empty_course_list(): void
    {
        $unknown = User::factory()->create([
            'email' => 'unknown.teacher@lcps.k12.va.us',
        ]);

        $this->assertCount(0, $this->classroom->coursesForTeacher($unknown));
    }

    public function test_enrollment_verification_uses_classroom_google_user_id(): void
    {
        $this->assertTrue(
            $this->classroom->studentEnrolledInCourse(
                $this->teacher,
                'course-algebra-101',
                'student-google-1001'
            )
        );

        $this->assertFalse(
            $this->classroom->studentEnrolledInCourse(
                $this->teacher,
                'course-algebra-101',
                'student-google-3001'
            )
        );
    }

    public function test_student_emails_are_on_configured_student_domain(): void
    {
        $students = $this->classroom->studentsForCourse($this->teacher, 'course-algebra-101');

        $onDomain = $students->filter(
            fn ($student) => str_ends_with(strtolower($student->email), '@k12louisa.org')
        );
        $offDomain = $students->reject(
            fn ($student) => str_ends_with(strtolower($student->email), '@k12louisa.org')
        );

        $this->assertNotEmpty($onDomain);
        foreach ($onDomain as $student) {
            $this->assertStringEndsWith('@k12louisa.org', $student->email);
        }

        // External participants may appear on the roster with non-student domains.
        $this->assertTrue($offDomain->contains(
            fn ($student) => $student->googleUserId === 'student-google-external'
        ));
    }
}
