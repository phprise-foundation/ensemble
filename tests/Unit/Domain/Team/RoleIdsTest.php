<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Team;

use App\Domain\Team\RoleId;
use App\Domain\Team\RoleIds;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class RoleIdsTest extends TestCase
{
    public function testKeepsTheFirstOccurrenceOfEachRole(): void
    {
        $first = RoleId::generate();
        $second = RoleId::generate();
        $roles = new RoleIds([$first, $second, $first]);

        self::assertCount(2, $roles);
        self::assertTrue($roles->contains($first));
        self::assertTrue($roles->contains($second));
        self::assertSame([$first->value(), $second->value()], $roles->values());
    }

    public function testBuildsFromStrings(): void
    {
        $id = RoleId::generate();

        self::assertSame([$id->value()], RoleIds::fromStrings([$id->value()])->values());
    }

    public function testRejectsMalformedIds(): void
    {
        $this->expectException(InvalidArgumentException::class);

        RoleIds::fromStrings(['coder']);
    }

    public function testEmptyCollectionIsEmpty(): void
    {
        self::assertTrue((new RoleIds())->isEmpty());
        self::assertSame([], (new RoleIds())->values());
    }
}
