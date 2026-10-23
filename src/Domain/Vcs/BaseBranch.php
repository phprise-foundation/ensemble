<?php

declare(strict_types=1);

namespace App\Domain\Vcs;

use App\Domain\Exception\ValidationException;

final readonly class BaseBranch
{
    private string $value;

    public function __construct(string $value)
    {
        $normalized = trim($value);
        if ($normalized === '' || mb_strlen($normalized) > 100) {
            throw new ValidationException('baseBranch', 'Base branch must have 1 to 100 characters.');
        }
        if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9._\/-]*$/', $normalized)) {
            throw new ValidationException('baseBranch', 'Base branch must start with a letter or digit and use letters, digits, dot, dash or slash.');
        }
        if (str_contains($normalized, '..') || str_ends_with($normalized, '/') || str_ends_with($normalized, '.')) {
            throw new ValidationException('baseBranch', 'Base branch is not a valid branch name.');
        }

        $this->value = $normalized;
    }

    public function value(): string
    {
        return $this->value;
    }
}
