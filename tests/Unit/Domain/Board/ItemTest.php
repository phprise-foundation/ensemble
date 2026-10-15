<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Board;

use App\Domain\Board\Comment;
use App\Domain\Board\Item;
use App\Domain\Board\ItemId;
use App\Domain\Workflow\ColumnId;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class ItemTest extends TestCase
{
    public function testMovesBetweenColumnsAndTracksEntry(): void
    {
        $item = $this->item();
        $at = new DateTimeImmutable('2026-01-02 03:04:05');

        $item->moveTo(new ColumnId('design'), $at);

        self::assertSame('design', $item->column()->value());
        self::assertSame($at, $item->enteredAt());
        self::assertSame($at, $item->updatedAt());
    }

    public function testAssignsAndClearsWorker(): void
    {
        $item = $this->item();

        $item->assignWorker('Vivy', $this->at());
        self::assertSame('Vivy', $item->worker());

        $item->clearWorker($this->at());
        self::assertSame('', $item->worker());
    }

    public function testBlocksAndUnblocks(): void
    {
        $item = $this->item();

        $item->block('RFC-2', $this->at());
        self::assertTrue($item->blocked());
        self::assertSame('RFC-2', $item->blockedBy());

        $item->unblock($this->at());
        self::assertFalse($item->blocked());
        self::assertSame('', $item->blockedBy());
    }

    public function testAddsCommentAndCloses(): void
    {
        $item = $this->item();

        $item->addComment(new Comment('user', 'hello', $this->at()), $this->at());
        self::assertCount(1, $item->comments());

        $item->close($this->at());
        self::assertTrue($item->closed());
    }

    private function item(): Item
    {
        return new Item(new ItemId(1), new ColumnId('intent'), 'Title', 'Body', null, $this->at());
    }

    private function at(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-01-01 00:00:00');
    }
}
