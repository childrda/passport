<?php

namespace Tests\Unit;

use App\Services\TemporaryPasswordGenerator;
use InvalidArgumentException;
use Tests\TestCase;

class TemporaryPasswordGeneratorTest extends TestCase
{
    public function test_generated_password_matches_configured_length(): void
    {
        config([
            'reset.temp_password.length' => 10,
            'reset.temp_password.alphabet' => 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789',
        ]);

        $password = app(TemporaryPasswordGenerator::class)->generate();

        $this->assertSame(10, strlen($password));
    }

    public function test_generated_password_uses_only_alphabet_characters(): void
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789';

        config([
            'reset.temp_password.length' => 10,
            'reset.temp_password.alphabet' => $alphabet,
        ]);

        $password = app(TemporaryPasswordGenerator::class)->generate();

        foreach (str_split($password) as $char) {
            $this->assertStringContainsString($char, $alphabet);
        }
    }

    public function test_generated_password_contains_upper_lower_and_digit(): void
    {
        config([
            'reset.temp_password.length' => 10,
            'reset.temp_password.alphabet' => 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789',
        ]);

        $password = app(TemporaryPasswordGenerator::class)->generate();

        $this->assertMatchesRegularExpression('/[A-Z]/', $password);
        $this->assertMatchesRegularExpression('/[a-z]/', $password);
        $this->assertMatchesRegularExpression('/[0-9]/', $password);
    }

    public function test_rejects_duplicate_alphabet_characters(): void
    {
        $this->expectException(InvalidArgumentException::class);

        TemporaryPasswordGenerator::validateConfiguration(10, 'AABC');
    }

    public function test_rejects_length_too_short_for_required_groups(): void
    {
        $this->expectException(InvalidArgumentException::class);

        TemporaryPasswordGenerator::validateConfiguration(2, 'Aa1');
    }
}
