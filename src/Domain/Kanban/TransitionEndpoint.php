<?php

declare(strict_types=1);

namespace App\Domain\Kanban;

use App\Domain\Exception\ValidationException;
use InvalidArgumentException;

final readonly class TransitionEndpoint
{
    private const ANY_VALUE = '*';

    private function __construct(private ?StatusId $statusId)
    {
    }

    public static function any(): self
    {
        return new self(null);
    }

    public static function status(StatusId $statusId): self
    {
        return new self($statusId);
    }

    public static function fromString(string $value): self
    {
        if ($value === self::ANY_VALUE || $value === 'any') {
            return self::any();
        }
        try {
            return self::status(new StatusId($value));
        } catch (InvalidArgumentException) {
            throw new ValidationException('fromStatusId', 'The source status must be a status id, "*" or "any".');
        }
    }

    public function isAny(): bool
    {
        return $this->statusId === null;
    }

    public function statusId(): ?StatusId
    {
        return $this->statusId;
    }

    public function value(): string
    {
        return $this->statusId?->value() ?? self::ANY_VALUE;
    }
}
