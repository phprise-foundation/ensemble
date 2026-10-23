<?php

declare(strict_types=1);

namespace App\Domain\Vcs;

use App\Domain\Exception\ValidationException;

final readonly class RepositoryUrl
{
    private string $value;

    public function __construct(string $value)
    {
        $normalized = trim($value);
        if (mb_strlen($normalized) > 2048) {
            throw new ValidationException('url', 'Repository URL must have at most 2048 characters.');
        }
        if (!str_starts_with($normalized, 'https://') || filter_var($normalized, FILTER_VALIDATE_URL) === false) {
            throw new ValidationException('url', 'Repository URL must be an https URL.');
        }
        if (parse_url($normalized, PHP_URL_USER) !== null || parse_url($normalized, PHP_URL_PASS) !== null) {
            throw new ValidationException('url', 'Repository URL must not carry credentials.');
        }

        $this->value = $normalized;
    }

    public function value(): string
    {
        return $this->value;
    }
}
