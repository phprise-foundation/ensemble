<?php

declare(strict_types=1);

namespace App\Application;

use App\Domain\Board\Comment;
use App\Domain\Board\Item;
use App\Domain\Board\ItemId;
use App\Domain\Contract\BoardRepository;
use App\Domain\Exception\ItemNotFoundException;
use DateTimeImmutable;

final readonly class AddComment
{
    public function __construct(private BoardRepository $repository)
    {
    }

    public function execute(ItemId $id, string $author, string $body): Comment
    {
        $item = $this->find($id);
        $comment = new Comment($author, $body, new DateTimeImmutable());
        $item->addComment($comment);
        $this->repository->save($item);

        return $comment;
    }

    private function find(ItemId $id): Item
    {
        return $this->repository->find($id) ?? throw ItemNotFoundException::forId($id);
    }
}
