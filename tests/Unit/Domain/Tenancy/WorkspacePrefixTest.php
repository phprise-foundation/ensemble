<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Tenancy;

use App\Domain\Exception\ValidationException;
use App\Domain\Tenancy\WorkspacePrefix;
use PHPUnit\Framework\TestCase;

final class WorkspacePrefixTest extends TestCase
{
    public function testNormalizesToUppercase(): void
    {
        self::assertSame('KAN', (new WorkspacePrefix('kan'))->value());
    }

    public function testDerivesFromName(): void
    {
        self::assertSame('MYBOARD', WorkspacePrefix::fromName('My Board')->value());
    }

    public function testRejectsPrefixStartingWithDigit(): void
    {
        $this->expectException(ValidationException::class);

        new WorkspacePrefix('1KAN');
    }
}
