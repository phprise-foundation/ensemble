<?php

declare(strict_types=1);

namespace App\Domain\Board;

use DateTimeImmutable;
use Phprise\Common\ValueObject\StringObject;

final readonly class Comment
{
    private StringObject $author;
    private StringObject $body;

    public function __construct(
        string $author,
        string $body,
        private DateTimeImmutable $createdAt,
    ) {
        $this->author = new StringObject($author);
        $this->body = new StringObject($body);
    }

    public function author(): string
    {
        return $this->author->getValue();
    }

    public function body(): string
    {
        return $this->body->getValue();
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
