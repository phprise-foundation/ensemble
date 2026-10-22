<?php

declare(strict_types=1);

namespace App\Interface\Http\Kanban;

use App\Domain\Kanban\IssueComment;

final class IssueCommentPresenter
{
    /** @return array<string, mixed> */
    public function present(IssueComment $comment): array
    {
        return [
            'id' => $comment->id()->value(),
            'author' => $comment->author(),
            'body' => $comment->body(),
            'createdAt' => $comment->createdAt()->format(DATE_ATOM),
        ];
    }

    /**
     * @param list<IssueComment> $comments
     * @return list<array<string, mixed>>
     */
    public function presentMany(array $comments): array
    {
        return array_map(fn(IssueComment $comment): array => $this->present($comment), $comments);
    }
}
