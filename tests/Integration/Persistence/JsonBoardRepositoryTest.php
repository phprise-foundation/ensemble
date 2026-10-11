<?php

declare(strict_types=1);

namespace App\Tests\Integration\Persistence;

use App\Domain\Board\Comment;
use App\Domain\Board\Item;
use App\Domain\Board\Role;
use App\Domain\Board\Status;
use App\Infrastructure\Persistence\ItemMapper;
use App\Infrastructure\Persistence\JsonBoardRepository;
use App\Infrastructure\Persistence\JsonFile;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class JsonBoardRepositoryTest extends TestCase
{
    private string $file;

    protected function setUp(): void
    {
        $this->file = tempnam(sys_get_temp_dir(), 'board');
        unlink($this->file);
    }

    protected function tearDown(): void
    {
        if (is_file($this->file)) {
            unlink($this->file);
        }
    }

    public function testPersistsItemsAndComments(): void
    {
        $repository = $this->repository();
        $item = new Item($repository->nextId(), 'Task', 'Do it', Status::Intent, Role::Coder, new DateTimeImmutable());
        $repository->add($item);

        $item->moveTo(Status::Coding);
        $item->addComment(new Comment('Vivy', 'starting', new DateTimeImmutable()));
        $repository->save($item);

        $restored = $repository->find($item->id());
        self::assertNotNull($restored);
        self::assertSame(Status::Coding, $restored->status());
        self::assertCount(1, $restored->comments());
        self::assertSame('Vivy', $restored->comments()->last()?->author());
    }

    public function testGeneratesIncrementingIds(): void
    {
        $repository = $this->repository();
        $first = new Item($repository->nextId(), 'A', '', Status::Intent, null, new DateTimeImmutable());
        $repository->add($first);

        self::assertSame($first->id()->toInt() + 1, $repository->nextId()->toInt());
    }

    private function repository(): JsonBoardRepository
    {
        return new JsonBoardRepository(new JsonFile($this->file), new ItemMapper());
    }
}
