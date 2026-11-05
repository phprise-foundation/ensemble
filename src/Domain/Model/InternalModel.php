<?php

declare(strict_types=1);

namespace App\Domain\Model;

use App\Domain\Exception\ValidationException;

final readonly class InternalModel
{
    public function __construct(
        private string $model,
        private string $fallback,
        private string $subagent,
        private string $fallbackSubagent,
    ) {
        if (trim($model) === '') {
            throw new ValidationException('internal', 'The internal model reference is required.');
        }
    }

    public function model(): string
    {
        return $this->model;
    }

    public function fallback(): string
    {
        return $this->fallback;
    }

    public function subagent(): string
    {
        return $this->subagent;
    }

    public function fallbackSubagent(): string
    {
        return $this->fallbackSubagent;
    }
}
