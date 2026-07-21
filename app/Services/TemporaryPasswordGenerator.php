<?php

namespace App\Services;

use InvalidArgumentException;
use Random\RandomException;

class TemporaryPasswordGenerator
{
    /**
     * Validate password configuration at startup / before generation.
     *
     * @throws InvalidArgumentException
     */
    public static function validateConfiguration(?int $length = null, ?string $alphabet = null): void
    {
        $length ??= (int) config('reset.temp_password.length');
        $alphabet ??= (string) config('reset.temp_password.alphabet');

        if ($length < 1) {
            throw new InvalidArgumentException('TEMP_PASSWORD_LENGTH must be at least 1.');
        }

        if ($alphabet === '') {
            throw new InvalidArgumentException('TEMP_PASSWORD_ALPHABET must not be empty.');
        }

        $chars = mb_str_split($alphabet);
        if (count($chars) !== count(array_unique($chars))) {
            throw new InvalidArgumentException('TEMP_PASSWORD_ALPHABET must not contain duplicate characters.');
        }

        $groups = self::characterGroupsPresentInAlphabet($alphabet);
        $requiredGroupCount = count($groups);

        if ($requiredGroupCount === 0) {
            throw new InvalidArgumentException(
                'TEMP_PASSWORD_ALPHABET must include at least one uppercase letter, lowercase letter, or digit.'
            );
        }

        if ($length < $requiredGroupCount) {
            throw new InvalidArgumentException(
                "TEMP_PASSWORD_LENGTH ({$length}) is too short to include required character groups ({$requiredGroupCount})."
            );
        }
    }

    /**
     * @throws InvalidArgumentException
     * @throws RandomException
     */
    public function generate(): string
    {
        $length = (int) config('reset.temp_password.length');
        $alphabet = (string) config('reset.temp_password.alphabet');

        self::validateConfiguration($length, $alphabet);

        $groups = self::characterGroupsPresentInAlphabet($alphabet);
        $passwordChars = [];

        foreach ($groups as $groupChars) {
            $passwordChars[] = $groupChars[random_int(0, count($groupChars) - 1)];
        }

        $alphabetChars = mb_str_split($alphabet);
        while (count($passwordChars) < $length) {
            $passwordChars[] = $alphabetChars[random_int(0, count($alphabetChars) - 1)];
        }

        return implode('', self::secureShuffle($passwordChars));
    }

    /**
     * Groups that exist in the alphabet and must appear at least once.
     *
     * @return list<list<string>>
     */
    private static function characterGroupsPresentInAlphabet(string $alphabet): array
    {
        $chars = mb_str_split($alphabet);
        $groups = [];

        $upper = array_values(array_filter($chars, fn (string $c): bool => ctype_upper($c)));
        $lower = array_values(array_filter($chars, fn (string $c): bool => ctype_lower($c)));
        $digits = array_values(array_filter($chars, fn (string $c): bool => ctype_digit($c)));

        if ($upper !== []) {
            $groups[] = $upper;
        }
        if ($lower !== []) {
            $groups[] = $lower;
        }
        if ($digits !== []) {
            $groups[] = $digits;
        }

        return $groups;
    }

    /**
     * @param  list<string>  $chars
     * @return list<string>
     *
     * @throws RandomException
     */
    private static function secureShuffle(array $chars): array
    {
        $count = count($chars);
        for ($i = $count - 1; $i > 0; $i--) {
            $j = random_int(0, $i);
            [$chars[$i], $chars[$j]] = [$chars[$j], $chars[$i]];
        }

        return $chars;
    }
}
