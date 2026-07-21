<?php

namespace App\Services\Google;

use App\Contracts\ClassroomApiGateway;
use App\Exceptions\ClassroomApiException;
use App\Models\User;
use Google\Service\Classroom;
use Google\Service\Exception as GoogleServiceException;
use Throwable;

class GoogleClassroomApiGateway implements ClassroomApiGateway
{
    public function __construct(
        private readonly GoogleClientFactory $clientFactory,
    ) {}

    public function listActiveCourses(User $teacher): array
    {
        $classroom = $this->classroom($teacher);
        $courses = [];
        $pageToken = null;

        try {
            do {
                $response = $classroom->courses->listCourses([
                    'teacherId' => 'me',
                    'courseStates' => ['ACTIVE'],
                    'pageSize' => 100,
                    'pageToken' => $pageToken,
                ]);

                foreach ($response->getCourses() ?? [] as $course) {
                    $courses[] = [
                        'id' => (string) $course->getId(),
                        'name' => (string) ($course->getName() ?? 'Untitled course'),
                        'section' => $course->getSection() !== null && $course->getSection() !== ''
                            ? (string) $course->getSection()
                            : null,
                        'courseState' => (string) ($course->getCourseState() ?? 'ACTIVE'),
                    ];
                }

                $pageToken = $response->getNextPageToken();
            } while (filled($pageToken));
        } catch (GoogleServiceException $e) {
            throw ClassroomApiException::requestFailed($this->googleErrorDetail($e), $e);
        } catch (ClassroomApiException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw ClassroomApiException::requestFailed(previous: $e);
        }

        return $courses;
    }

    public function listStudents(User $teacher, string $courseId): array
    {
        $classroom = $this->classroom($teacher);
        $students = [];
        $pageToken = null;

        try {
            do {
                $response = $classroom->courses_students->listCoursesStudents($courseId, [
                    'pageSize' => 100,
                    'pageToken' => $pageToken,
                ]);

                foreach ($response->getStudents() ?? [] as $student) {
                    $profile = $student->getProfile();
                    $name = $profile?->getName();

                    $students[] = [
                        'userId' => (string) $student->getUserId(),
                        'fullName' => (string) (
                            $name?->getFullName()
                            ?: $profile?->getEmailAddress()
                            ?: 'Unknown student'
                        ),
                        'email' => (string) ($profile?->getEmailAddress() ?? ''),
                    ];
                }

                $pageToken = $response->getNextPageToken();
            } while (filled($pageToken));
        } catch (GoogleServiceException $e) {
            throw ClassroomApiException::requestFailed($this->googleErrorDetail($e), $e);
        } catch (ClassroomApiException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw ClassroomApiException::requestFailed(previous: $e);
        }

        return $students;
    }

    public function listTeacherUserIds(User $teacher, string $courseId): array
    {
        $classroom = $this->classroom($teacher);
        $ids = [];
        $pageToken = null;

        try {
            do {
                $response = $classroom->courses_teachers->listCoursesTeachers($courseId, [
                    'pageSize' => 100,
                    'pageToken' => $pageToken,
                ]);

                foreach ($response->getTeachers() ?? [] as $courseTeacher) {
                    $ids[] = (string) $courseTeacher->getUserId();
                }

                $pageToken = $response->getNextPageToken();
            } while (filled($pageToken));
        } catch (GoogleServiceException $e) {
            throw ClassroomApiException::requestFailed($this->googleErrorDetail($e), $e);
        } catch (ClassroomApiException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw ClassroomApiException::requestFailed(previous: $e);
        }

        return $ids;
    }

    private function classroom(User $teacher): Classroom
    {
        return new Classroom($this->clientFactory->forUser($teacher));
    }

    private function googleErrorDetail(GoogleServiceException $e): string
    {
        $message = trim($e->getMessage());

        return $message !== '' ? "({$message})" : '';
    }
}
