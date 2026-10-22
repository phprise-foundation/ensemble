<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Tenancy;

use App\Domain\Exception\ValidationException;
use App\Domain\Security\PasswordHash;
use App\Domain\Tenancy\Login;
use App\Domain\Tenancy\User;
use App\Domain\Tenancy\UserId;
use PHPUnit\Framework\TestCase;

final class UserTest extends TestCase
{
    public function testKeepsProfileMutable(): void
    {
        $user = $this->user();

        $user->rename('Alice');
        $user->recolor('#4c9aff');

        self::assertSame('Alice', $user->displayName());
        self::assertSame('#4c9aff', $user->avatarColor());
    }

    public function testRejectsLongDisplayName(): void
    {
        $this->expectException(ValidationException::class);

        $this->user()->rename(str_repeat('a', 41));
    }

    public function testRejectsInvalidColor(): void
    {
        $this->expectException(ValidationException::class);

        $this->user()->recolor('blue');
    }

    public function testPromotesAdmin(): void
    {
        $user = $this->user();

        $user->promote(true);

        self::assertTrue($user->isAdmin());
    }

    private function user(): User
    {
        return new User(
            UserId::generate(),
            new Login('alice'),
            new PasswordHash('hash'),
            'Alice',
        );
    }
}
