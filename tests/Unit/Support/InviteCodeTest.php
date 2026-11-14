<?php

namespace Tests\Unit\Support;

use App\Support\InviteCode;
use PHPUnit\Framework\TestCase;

class InviteCodeTest extends TestCase
{
    public function test_looks_valid_accepts_5_digits(): void
    {
        $this->assertTrue(InviteCode::looksValid('12345'));
        $this->assertTrue(InviteCode::looksValid('00000'));
        $this->assertTrue(InviteCode::looksValid('99999'));
    }

    public function test_looks_valid_rejects_non_digits(): void
    {
        $this->assertFalse(InviteCode::looksValid('1234'));
        $this->assertFalse(InviteCode::looksValid('123456'));
        $this->assertFalse(InviteCode::looksValid('abcde'));
        $this->assertFalse(InviteCode::looksValid('12a45'));
        $this->assertFalse(InviteCode::looksValid(''));
    }

    public function test_normalize_strips_whitespace(): void
    {
        $this->assertSame('12345', InviteCode::normalize('12345'));
        $this->assertSame('12345', InviteCode::normalize(' 12345 '));
        $this->assertSame('12345', InviteCode::normalize('1 2 3 4 5'));
        $this->assertSame('12345', InviteCode::normalize("1\t2 3\n4 5"));
    }

    public function test_normalize_then_looks_valid_accepts_spaced_code(): void
    {
        $this->assertTrue(InviteCode::looksValid('1 2 3 4 5'));
        $this->assertTrue(InviteCode::looksValid(' 12345 '));
    }

    public function test_length_constant(): void
    {
        $this->assertSame(5, InviteCode::LENGTH);
    }

    public function test_lifetime_constant(): void
    {
        $this->assertSame(10, InviteCode::LIFETIME_MINUTES);
    }
}
