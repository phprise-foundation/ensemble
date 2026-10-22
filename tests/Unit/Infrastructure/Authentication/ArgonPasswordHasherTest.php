<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Authentication;

use App\Domain\Security\PlainPassword;
use App\Infrastructure\Authentication\ArgonPasswordHasher;
use PHPUnit\Framework\TestCase;

final class ArgonPasswordHasherTest extends TestCase
{
    public function testHashesAndVerifiesPassword(): void
    {
        $hasher = new ArgonPasswordHasher();
        $password = new PlainPassword('correct horse');
        $hash = $hasher->hash($password);

        self::assertTrue($hasher->verify($password, $hash));
        self::assertFalse($hasher->verify(new PlainPassword('wrong horse'), $hash));
    }
}
