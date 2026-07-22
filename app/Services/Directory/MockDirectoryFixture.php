<?php

namespace App\Services\Directory;

use App\DataTransferObjects\DirectoryUser;
use Illuminate\Support\Str;

/**
 * Mock student-tenant Directory users for local development.
 * Discovery is by roster/alias email; reset uses immutable Directory IDs.
 */
class MockDirectoryFixture
{
    /**
     * Canonical Directory users keyed by immutable Directory ID.
     *
     * @return array<string, array{id: string, primaryEmail: string, fullName: string}>
     */
    public function usersByDirectoryId(): array
    {
        $studentDomain = config('reset.student_domain');
        $staffDomain = config('reset.staff_domain');

        return [
            'dir-1001' => [
                'id' => 'dir-1001',
                'primaryEmail' => 'alex.rivera@'.$studentDomain,
                'fullName' => 'Alex Rivera',
            ],
            'dir-1002' => [
                'id' => 'dir-1002',
                'primaryEmail' => 'jordan.lee@'.$studentDomain,
                'fullName' => 'Jordan Lee',
            ],
            'dir-1003' => [
                'id' => 'dir-1003',
                'primaryEmail' => 'sam.patel@'.$studentDomain,
                'fullName' => 'Sam Patel',
            ],
            'dir-2001' => [
                'id' => 'dir-2001',
                'primaryEmail' => 'casey.nguyen@'.$studentDomain,
                'fullName' => 'Casey Nguyen',
            ],
            'dir-3001' => [
                'id' => 'dir-3001',
                'primaryEmail' => 'morgan.blake@'.$studentDomain,
                'fullName' => 'Morgan Blake',
            ],
            'dir-3002' => [
                'id' => 'dir-3002',
                'primaryEmail' => 'taylor.brooks@'.$studentDomain,
                'fullName' => 'Taylor Brooks',
            ],
            // Staff-domain primary (for denial tests — not a student-tenant account).
            'dir-staff' => [
                'id' => 'dir-staff',
                'primaryEmail' => 'staff.person@'.$staffDomain,
                'fullName' => 'Staff Person',
            ],
            // Alias-misdirection fixture: alias on student domain, primary elsewhere.
            'dir-misdirected' => [
                'id' => 'dir-misdirected',
                'primaryEmail' => 'outsider@example.com',
                'fullName' => 'Misdirected User',
            ],
        ];
    }

    /**
     * Email keys (primary + aliases) → Directory ID.
     *
     * @return array<string, string>
     */
    public function emailIndex(): array
    {
        $studentDomain = config('reset.student_domain');
        $staffDomain = config('reset.staff_domain');
        $index = [];

        foreach ($this->usersByDirectoryId() as $id => $row) {
            $index[Str::lower($row['primaryEmail'])] = $id;
        }

        // Alias for Alex — resolves to canonical primary alex.rivera@student
        $index['alex.alias@'.Str::lower((string) $studentDomain)] = 'dir-1001';

        // Staff denial via email discovery
        $index['staff.person@'.Str::lower((string) $staffDomain)] = 'dir-staff';

        // Alias on student domain that resolves to a staff primary (post-lookup denial).
        $index['staff.alias@'.Str::lower((string) $studentDomain)] = 'dir-staff';

        // Alias on student domain that resolves to a non-student primary
        $index['alias.misdirect@'.Str::lower((string) $studentDomain)] = 'dir-misdirected';

        // Roster email with no Directory account (student_not_in_directory)
        // intentionally omitted: missing.student@{studentDomain}

        return $index;
    }

    public function findByRosterEmail(string $email): ?DirectoryUser
    {
        $key = Str::lower(trim($email));
        $directoryId = $this->emailIndex()[$key] ?? null;

        if ($directoryId === null) {
            return null;
        }

        return $this->findByDirectoryId($directoryId);
    }

    public function findByDirectoryId(string $directoryUserId): ?DirectoryUser
    {
        $row = $this->usersByDirectoryId()[$directoryUserId] ?? null;

        if ($row === null) {
            return null;
        }

        return new DirectoryUser(
            id: $row['id'],
            primaryEmail: $row['primaryEmail'],
            fullName: $row['fullName'],
        );
    }
}
