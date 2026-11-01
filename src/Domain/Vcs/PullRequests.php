<?php

declare(strict_types=1);

namespace App\Domain\Vcs;

use App\Domain\Kanban\Issue;
use App\Domain\Kanban\Workdir;

interface PullRequests
{
    public function open(Workdir $workdir, Issue $issue): PullRequestResult;

    public function merge(Workdir $workdir, int $number, string $method): MergeResult;
}
