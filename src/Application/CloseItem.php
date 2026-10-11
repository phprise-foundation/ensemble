<?php

declare(strict_types=1);

namespace App\Application;

use App\Domain\Board\Comment;
use App\Domain\Board\Item;
use App\Domain\Board\ItemId;
use App\Domain\Contract\BoardRepository;
use App\Domain\Exception\ItemNotFoundException;
use DateTimeImmutable;

final readonly class CloseItem
{
    public function __construct(private BoardRepository $repository)
    {
    }

    public function execute(ItemId $id, ?string $comment): Item
    {
        $item = $this->find($id);
        $item->close();
        $this->appendClosingComment($item, $comment);
        $this->repository->save($item);

        return $item;
    }

    private function find(ItemId $id): Item
    {
        return $this->repository->find($id) ?? throw ItemNotFoundException::forId($id);
    }

    private function appendClosingComment(Item $item, ?string $comment): void
    {
        if ($comment === null || $comment === '') {
            return;
        }
        $item->addComment(new Comment('system', $comment, new DateTimeImmutable()));
    }
}
