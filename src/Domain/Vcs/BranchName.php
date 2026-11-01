<?php

declare(strict_types=1);

namespace App\Domain\Vcs;

use App\Domain\Kanban\Issue;
use App\Domain\Kanban\IssueKey;
use App\Domain\Kanban\IssueType;
use App\Domain\Kanban\Slug;

final readonly class BranchName
{
    private function __construct(private string $value)
    {
    }

    public static function forIssue(Issue $issue, IssueType $type): self
    {
        return self::forValues($issue->key(), $issue->summary(), $type);
    }

    public static function forValues(IssueKey $key, string $summary, IssueType $type): self
    {
        $ascii = function_exists('iconv') ? @iconv('UTF-8', 'ASCII//TRANSLIT', $summary) : false;
        $source = is_string($ascii) && $ascii !== '' ? $ascii : $summary;
        $slug = rtrim(substr(Slug::fromName($source)->value(), 0, 55), '-');
        $slug = $slug === '' ? 'change' : $slug;

        return new self(self::prefix($type) . '/' . $key->value() . '-' . $slug);
    }

    public function value(): string
    {
        return $this->value;
    }

    private static function prefix(IssueType $type): string
    {
        $name = mb_strtolower($type->key()->value() . ' ' . $type->name());

        return match (true) {
            str_contains($name, 'bug'), str_contains($name, 'fix') => 'fix',
            str_contains($name, 'doc') => 'docs',
            str_contains($name, 'refactor') => 'refactor',
            str_contains($name, 'test') => 'test',
            str_contains($name, 'chore'), str_contains($name, 'build'), str_contains($name, 'ci') => 'chore',
            default => 'feat',
        };
    }
}
