<?php

declare(strict_types=1);

namespace App\Domain\Exception;

use RuntimeException;

final class ValidationException extends RuntimeException
{
    public function __construct(private readonly string $field, string $message)
    {
        parent::__construct($message);
    }

    public function field(): string
    {
        return $this->field;
    }
}
