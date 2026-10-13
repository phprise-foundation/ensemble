<?php

declare(strict_types=1);

namespace App\Application;

use App\Domain\Agent\AgentId;
use App\Domain\Contract\AgentRegistry;
use App\Domain\Contract\ColumnRepository;
use App\Domain\Exception\AgentNotFoundException;
use App\Domain\Exception\ColumnAlreadyExistsException;
use App\Domain\Exception\ColumnNotFoundException;
use App\Domain\Workflow\Column;
use App\Domain\Workflow\ColumnCollection;
use App\Domain\Workflow\ColumnId;
use App\Domain\Workflow\Transitions;
use Phprise\Common\ValueObject\StringObject;

final readonly class CreateColumn
{
    public function __construct(
        private ColumnRepository $columns,
        private AgentRegistry $agents,
    ) {
    }

    /** @param list<ColumnId> $transitions */
    public function execute(string $name, bool $final, ?AgentId $role = null, array $transitions = []): Column
    {
        $all = $this->columns->all();
        $id = new ColumnId((new StringObject($name))->toKebab());
        $this->guardUniqueness($all, $id);
        $this->guardRole($role);
        $this->guardTargets($all, $transitions);
        $column = new Column($id, $name, $all->nextPosition(), $final, $role, new Transitions($transitions));
        $this->columns->save($column);

        return $column;
    }

    private function guardUniqueness(ColumnCollection $all, ColumnId $id): void
    {
        if ($all->has($id)) {
            throw ColumnAlreadyExistsException::forId($id);
        }
    }

    private function guardRole(?AgentId $role): void
    {
        if ($role !== null && $this->agents->find($role) === null) {
            throw AgentNotFoundException::forId($role);
        }
    }

    /** @param list<ColumnId> $targets */
    private function guardTargets(ColumnCollection $all, array $targets): void
    {
        foreach ($targets as $target) {
            if (!$all->has($target)) {
                throw ColumnNotFoundException::forId($target);
            }
        }
    }
}
