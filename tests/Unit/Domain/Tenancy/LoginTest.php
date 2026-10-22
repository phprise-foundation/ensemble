<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Tenancy;

use App\Domain\Exception\ValidationException;
use App\Domain\Tenancy\Login;
use PHPUnit\Framework\TestCase;

final class LoginTest extends TestCase
{
    public function testNormalizesToLowercase(): void
    {
        self::assertSame('admin', (new Login(' Admin '))->value());
    }

    public function testRejectsShortLogin(): void
    {
        $this->expectException(ValidationException::class);

        new Login('ab');
    }

    public function testRejectsInvalidCharacters(): void
    {
        $this->expectException(ValidationException::class);

        new Login('bad login');
    }
}
