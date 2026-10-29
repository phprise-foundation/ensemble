<?php

declare(strict_types=1);

namespace App\Domain\Vcs;

use App\Domain\Exception\ValidationException;

final readonly class GitHubSlug
{
    private function __construct(
        private string $owner,
        private string $repository,
    ) {
    }

    public static function fromUrl(RepositoryUrl $url): self
    {
        $host = strtolower((string) parse_url($url->value(), PHP_URL_HOST));
        $path = parse_url($url->value(), PHP_URL_PATH);
        if ($host !== 'github.com' || ! is_string($path)) {
            throw new ValidationException('url', 'Pull requests are only implemented for github.com URLs.');
        }

        $segments = array_values(array_filter(explode('/', trim($path, '/'))));
        if (count($segments) !== 2) {
            throw new ValidationException('url', 'The repository URL must be https://github.com/owner/repository.');
        }

        return new self($segments[0], (string) preg_replace('/\.git$/', '', $segments[1]));
    }

    public function owner(): string
    {
        return $this->owner;
    }

    public function repository(): string
    {
        return $this->repository;
    }

    public function path(): string
    {
        return $this->owner . '/' . $this->repository;
    }
}
