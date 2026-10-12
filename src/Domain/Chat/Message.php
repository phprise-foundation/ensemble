<?php

declare(strict_types=1);

namespace App\Domain\Chat;

use DateTimeImmutable;
use Phprise\Common\ValueObject\StringObject;

final readonly class Message
{
    private StringObject $body;

    public function __construct(
        private MessageRole $role,
        string $body,
        private DateTimeImmutable $createdAt,
    ) {
        $this->body = new StringObject($body);
    }

    public function role(): MessageRole
    {
        return $this->role;
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
