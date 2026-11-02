<?php

declare(strict_types=1);

namespace App\Domain\Runner;

use App\Domain\Kanban\Issue;
use App\Domain\Kanban\Status;
use App\Domain\Team\Agent;
use App\Domain\Team\Role;

final readonly class CommitMessage
{
    public function __construct(
        private string $subject,
        private string $body,
        private string $authorName,
        private string $authorEmail,
    ) {
    }

    public static function forAgent(
        Issue $issue,
        Agent $agent,
        Role $role,
        Status $status,
        string $branch,
    ): self {
        $subject = mb_substr(
            self::typeForBranch($branch) . '(' . $agent->name() . '/' . $issue->key()->value() . '): ' . $issue->summary(),
            0,
            120,
        );
        $body = implode("\n", [
            'Agent: ' . $agent->name(),
            'Role: ' . $role->name(),
            'Stage: ' . $status->name(),
            'Issue: ' . $issue->key()->value(),
        ]);
        $slug = strtolower(trim((string) preg_replace('/[^a-zA-Z0-9]+/', '-', $agent->name()), '-'));
        $slug = $slug === '' ? 'agent' : $slug;

        return new self($subject, $body, $agent->name(), $slug . '@ensemble.local');
    }

    public static function typeForBranch(string $branch): string
    {
        $type = str_contains($branch, '/') ? (string) strstr($branch, '/', true) : 'feat';
        $allowed = ['feat', 'fix', 'docs', 'refactor', 'test', 'chore', 'build', 'ci', 'perf', 'style', 'revert'];

        return in_array($type, $allowed, true) ? $type : 'feat';
    }

    public static function system(string $subject): self
    {
        return new self($subject, '', 'Ensemble', 'ensemble@ensemble.local');
    }

    public function subject(): string
    {
        return $this->subject;
    }

    public function body(): string
    {
        return $this->body;
    }

    public function authorName(): string
    {
        return $this->authorName;
    }

    public function authorEmail(): string
    {
        return $this->authorEmail;
    }
}
