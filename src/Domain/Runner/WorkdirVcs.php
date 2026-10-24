<?php

declare(strict_types=1);

namespace App\Domain\Runner;

use App\Domain\Kanban\Workdir;

/** Prepares (clone/fetch) and publishes (commit/push) a repository workdir. */
interface WorkdirVcs
{
    public function prepare(Workdir $workdir, string $path): Outcome;

    public function publish(Workdir $workdir, string $path): Outcome;

    /** Reconciles a conflicting workdir with the base branch and the agent branch. */
    public function resolve(Workdir $workdir, string $path, ResolutionStrategy $strategy): Outcome;
}
