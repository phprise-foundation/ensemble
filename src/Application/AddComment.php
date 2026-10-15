<?php

declare(strict_types=1);

namespace App\Application;

use App\Domain\Board\Comment;
use App\Domain\Board\Item;
use App\Domain\Board\ItemId;
use App\Domain\Contract\ItemRepository;
use App\Domain\Exception\ItemNotFoundException;
use DateTimeImmutable;

final readonly class AddComment
{
    public function __construct(private ItemRepository $items)
    {
    }

    public function execute(ItemId $id, string $author, string $body): Comment
    {
        $item = $this->find($id);
        $at = new DateTimeImmutable();
        $comment = new Comment($author, $body, $at);
        $item->addComment($comment, $at);
        $this->items->save($item);

        return $comment;
    }

    private function find(ItemId $id): Item
    {
        return $this->items->find($id) ?? throw ItemNotFoundException::forId($id);
    }
}
