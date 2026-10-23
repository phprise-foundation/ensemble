<?php

declare(strict_types=1);

namespace App\Interface\Http\Kanban;

use App\Domain\Kanban\Workdir;

final class WorkdirPresenter
{
    /** @return array<string, mixed> */
    public function present(Workdir $workdir): array
    {
        return [
            'repositoryId' => $workdir->repositoryId()?->value(),
            'branch' => $workdir->branch()?->value(),
            'state' => $workdir->state()->value,
        ];
    }
}
