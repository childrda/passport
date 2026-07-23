<?php

namespace App\Support;

/**
 * Deterministic accent colors for class cards (stable per course ID).
 */
final class CourseAccent
{
    /**
     * @return array{bg: string, text: string, initials: string}
     */
    public static function for(string $courseId): array
    {
        $palette = [
            ['bg' => '#dcfce7', 'text' => '#166534', 'initials' => '#15803d'],
            ['bg' => '#ede9fe', 'text' => '#5b21b6', 'initials' => '#6d28d9'],
            ['bg' => '#dbeafe', 'text' => '#1e40af', 'initials' => '#1d4ed8'],
            ['bg' => '#ffedd5', 'text' => '#9a3412', 'initials' => '#c2410c'],
            ['bg' => '#fce7f3', 'text' => '#9d174d', 'initials' => '#be185d'],
            ['bg' => '#e0e7ff', 'text' => '#3730a3', 'initials' => '#4338ca'],
            ['bg' => '#ccfbf1', 'text' => '#115e59', 'initials' => '#0f766e'],
            ['bg' => '#fef3c7', 'text' => '#92400e', 'initials' => '#b45309'],
        ];

        $index = abs(crc32($courseId)) % count($palette);

        return $palette[$index];
    }

    public static function initials(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];
        $parts = array_values(array_filter($parts));

        if ($parts === []) {
            return '?';
        }

        if (count($parts) === 1) {
            return strtoupper(mb_substr($parts[0], 0, 2));
        }

        return strtoupper(mb_substr($parts[0], 0, 1).mb_substr($parts[array_key_last($parts)], 0, 1));
    }
}
