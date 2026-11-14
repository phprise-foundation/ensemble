<?php

declare(strict_types=1);

namespace App\Application\Kanban;

use App\Application\Tenancy\WorkspaceGuard;
use App\Domain\Contract\Kanban\BoardColumnRepository;
use App\Domain\Contract\Kanban\BoardRepository;
use App\Domain\Contract\Kanban\IssueRepository;
use App\Domain\Exception\NotFoundException;
use App\Domain\Kanban\Board;
use App\Domain\Kanban\BoardId;
use App\Domain\Kanban\Issue;
use App\Domain\Kanban\IssueTypeId;
use App\Domain\Kanban\StatusId;
use App\Domain\Tenancy\User;
use App\Domain\Tenancy\WorkspaceId;

final readonly class ListIssues
{
    public function __construct(
        private IssueRepository $issues,
        private BoardRepository $boards,
        private BoardColumnRepository $columns,
        private WorkspaceGuard $guard,
    ) {
    }

    /** @return list<Issue> */
    public function execute(
        User $actor,
        WorkspaceId $workspaceId,
        ?BoardId $boardId,
        ?string $query = null,
        bool $backlog = false,
        bool $includeArchived = false,
    ): array {
        $this->guard->assertMember($actor, $workspaceId);
        $issues = array_values(array_filter(
            $this->issues->forWorkspace($workspaceId),
            fn(Issue $issue): bool => ($includeArchived || ! $issue->archived()) && $this->matches($issue, $query),
        ));
        if ($boardId === null) {
            return $issues;
        }

        $board = $this->boards->find($workspaceId, $boardId) ?? throw NotFoundException::board();
        if ($backlog) {
            $statuses = [];
            foreach ($board->backlogStatusIds()->all() as $statusId) {
                $statuses[$statusId->value()] = true;
            }

            return array_values(array_filter(
                $issues,
                fn(Issue $issue): bool => $this->belongsToBacklog($issue, $board, $statuses),
            ));
        }

        $statuses = [];
        foreach ($this->columns->forBoard($workspaceId, $boardId) as $column) {
            foreach ($column->statusIds()->all() as $statusId) {
                $statuses[$statusId->value()] = true;
            }
        }

        return array_values(array_filter(
            $issues,
            fn(Issue $issue): bool => $this->belongsToBoard($issue, $board, $statuses),
        ));
    }

    private function matches(Issue $issue, ?string $query): bool
    {
        $needle = $query === null ? '' : trim($query);
        if ($needle === '') {
            return true;
        }
        $haystack = $issue->key()->value() . "\n" . $issue->summary() . "\n" . $issue->description();

        return mb_stripos($haystack, $needle) !== false;
    }

    /** @param array<string, true> $statuses */
    private function belongsToBacklog(Issue $issue, Board $board, array $statuses): bool
    {
        if (! isset($statuses[$issue->statusId()->value()])) {
            return false;
        }
        $filterTypes = array_map(static fn(IssueTypeId $id): string => $id->value(), $board->filters()->issueTypeIds());
        if ($filterTypes !== [] && ! in_array($issue->issueTypeId()->value(), $filterTypes, true)) {
            return false;
        }

        return true;
    }

    /** @param array<string, true> $statuses */
    private function belongsToBoard(Issue $issue, Board $board, array $statuses): bool
    {
        if (!isset($statuses[$issue->statusId()->value()])) {
            return false;
        }

        $filterTypes = array_map(static fn(IssueTypeId $id): string => $id->value(), $board->filters()->issueTypeIds());
        if ($filterTypes !== [] && !in_array($issue->issueTypeId()->value(), $filterTypes, true)) {
            return false;
        }

        $filterStatuses = array_map(static fn(StatusId $id): string => $id->value(), $board->filters()->statusIds());
        if ($filterStatuses !== [] && !in_array($issue->statusId()->value(), $filterStatuses, true)) {
            return false;
        }

        return true;
    }
}
