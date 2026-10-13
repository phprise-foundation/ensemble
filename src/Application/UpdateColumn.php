<?php

declare(strict_types=1);

namespace App\Application;

use App\Domain\Agent\AgentId;
use App\Domain\Contract\AgentRegistry;
use App\Domain\Contract\ColumnRepository;
use App\Domain\Exception\AgentNotFoundException;
use App\Domain\Exception\ColumnNotFoundException;
use App\Domain\Exception\InvalidTransitionException;
use App\Domain\Workflow\Column;
use App\Domain\Workflow\ColumnId;
use App\Domain\Workflow\Position;
use App\Domain\Workflow\Transitions;

final readonly class UpdateColumn
{
    public function __construct(
        private ColumnRepository $columns,
        private AgentRegistry $agents,
    ) {
    }

    /** @param list<ColumnId> $transitions */
    public function execute(
        ColumnId $id,
        ?string $name,
        ?int $position,
        ?bool $final,
        ?AgentId $role,
        bool $roleChanged,
        array $transitions,
        bool $transitionsChanged,
    ): Column {
        $column = $this->columns->find($id) ?? throw ColumnNotFoundException::forId($id);
        $this->applyName($column, $name);
        $this->applyPosition($column, $position);
        $this->applyFinal($column, $final);
        $this->applyRole($column, $role, $roleChanged);
        $this->applyTransitions($column, $transitions, $transitionsChanged);
        $this->columns->save($column);

        return $column;
    }

    private function applyName(Column $column, ?string $name): void
    {
        if ($name !== null && $name !== '') {
            $column->rename($name);
        }
    }

    private function applyPosition(Column $column, ?int $position): void
    {
        if ($position !== null) {
            $column->placeAt(new Position($position));
        }
    }

    private function applyFinal(Column $column, ?bool $final): void
    {
        if ($final !== null) {
            $column->markFinal($final);
        }
    }

    private function applyRole(Column $column, ?AgentId $role, bool $changed): void
    {
        if (!$changed) {
            return;
        }
        if ($role !== null && $this->agents->find($role) === null) {
            throw AgentNotFoundException::forId($role);
        }
        $column->assignRole($role);
    }

    /** @param list<ColumnId> $targets */
    private function applyTransitions(Column $column, array $targets, bool $changed): void
    {
        if (!$changed) {
            return;
        }
        $this->guardTargets($column, $targets);
        $column->allow(new Transitions($targets));
    }

    /** @param list<ColumnId> $targets */
    private function guardTargets(Column $column, array $targets): void
    {
        foreach ($targets as $target) {
            if ($target->equals($column->id())) {
                throw InvalidTransitionException::forbidden($column->id(), $target);
            }
            if ($this->columns->find($target) === null) {
                throw ColumnNotFoundException::forId($target);
            }
        }
    }
}
