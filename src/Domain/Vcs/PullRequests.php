<?php

declare(strict_types=1);

namespace App\Domain\Vcs;

use App\Domain\Kanban\Issue;
use App\Domain\Runner\CommitMessage;
use App\Domain\Kanban\Workdir;
use App\Domain\Runner\Outcome;

interface PullRequests
{
    public function open(Workdir $workdir, Issue $issue, CommitMessage $message): PullRequestResult;

    public function merge(
        Workdir $workdir,
        int $number,
        string $method,
        ?string $commitTitle = null,
        ?string $commitMessage = null,
    ): MergeResult;

    public function deleteBranch(Workdir $workdir, string $branch): Outcome;

    public function comment(Workdir $workdir, int $number, string $body): Outcome;
}
