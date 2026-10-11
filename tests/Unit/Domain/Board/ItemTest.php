<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Board;

use App\Domain\Board\Comment;
use App\Domain\Board\Item;
use App\Domain\Board\ItemId;
use App\Domain\Board\Role;
use App\Domain\Board\Status;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class ItemTest extends TestCase
{
    public function testMovesThroughStatuses(): void
    {
        $item = $this->item();

        $item->moveTo(Status::Design);

        self::assertSame(Status::Design, $item->status());
    }

    public function testAssignsAndClearsWorker(): void
    {
        $item = $this->item();

        $item->assignWorker('Vivy');
        self::assertSame('Vivy', $item->worker());

        $item->clearWorker();
        self::assertSame('', $item->worker());
    }

    public function testBlocksAndUnblocks(): void
    {
        $item = $this->item();

        $item->block('RFC-2');
        self::assertTrue($item->blocked());
        self::assertSame('RFC-2', $item->blockedBy());

        $item->unblock();
        self::assertFalse($item->blocked());
        self::assertSame('', $item->blockedBy());
    }

    public function testAddsCommentAndCloses(): void
    {
        $item = $this->item();

        $item->addComment(new Comment('user', 'hello', new DateTimeImmutable()));
        self::assertCount(1, $item->comments());

        $item->close();
        self::assertTrue($item->closed());
        self::assertSame(Status::Done, $item->status());
    }

    private function item(): Item
    {
        return new Item(new ItemId(1), 'Title', 'Body', Status::Intent, Role::ProductOwner, new DateTimeImmutable());
    }
}
