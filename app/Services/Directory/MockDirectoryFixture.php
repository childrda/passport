<?php

namespace App\Services\Directory;

use App\DataTransferObjects\DirectoryUser;

/**
 * Maps Classroom Google user IDs to canonical Directory users for mock resets.
 */
class MockDirectoryFixture
{
    /**
     * @return array<string, array{id: string, primaryEmail: string, fullName: string}>
     */
    public function usersByClassroomId(): array
    {
        $studentDomain = config('reset.student_domain');
        $staffDomain = config('reset.staff_domain');

        return [
            'student-google-1001' => [
                'id' => 'dir-1001',
                'primaryEmail' => 'alex.rivera@'.$studentDomain,
                'fullName' => 'Alex Rivera',
            ],
            'student-google-1002' => [
                'id' => 'dir-1002',
                'primaryEmail' => 'jordan.lee@'.$studentDomain,
                'fullName' => 'Jordan Lee',
            ],
            'student-google-1003' => [
                'id' => 'dir-1003',
                'primaryEmail' => 'sam.patel@'.$studentDomain,
                'fullName' => 'Sam Patel',
            ],
            'student-google-2001' => [
                'id' => 'dir-2001',
                'primaryEmail' => 'casey.nguyen@'.$studentDomain,
                'fullName' => 'Casey Nguyen',
            ],
            'student-google-3001' => [
                'id' => 'dir-3001',
                'primaryEmail' => 'morgan.blake@'.$studentDomain,
                'fullName' => 'Morgan Blake',
            ],
            'student-google-3002' => [
                'id' => 'dir-3002',
                'primaryEmail' => 'taylor.brooks@'.$studentDomain,
                'fullName' => 'Taylor Brooks',
            ],
            // Fixture for staff-domain denial tests (not on any mock roster by default).
            'student-google-staff' => [
                'id' => 'dir-staff',
                'primaryEmail' => 'staff.person@'.$staffDomain,
                'fullName' => 'Staff Person',
            ],
        ];
    }

    public function findByClassroomUserId(string $classroomGoogleUserId): ?DirectoryUser
    {
        $row = $this->usersByClassroomId()[$classroomGoogleUserId] ?? null;

        if ($row === null) {
            return null;
        }

        return new DirectoryUser(
            id: $row['id'],
            primaryEmail: $row['primaryEmail'],
            fullName: $row['fullName'],
        );
    }

    public function findByDirectoryId(string $directoryUserId): ?DirectoryUser
    {
        foreach ($this->usersByClassroomId() as $row) {
            if ($row['id'] === $directoryUserId) {
                return new DirectoryUser(
                    id: $row['id'],
                    primaryEmail: $row['primaryEmail'],
                    fullName: $row['fullName'],
                );
            }
        }

        return null;
    }
}
