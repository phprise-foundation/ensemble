<?php

declare(strict_types=1);

namespace App\Domain\Workflow;

use App\Domain\Agent\AgentId;
use Phprise\Common\ValueObject\StringObject;

final class Column
{
    private StringObject $name;

    public function __construct(
        private readonly ColumnId $id,
        string $name,
        private Position $position,
        private bool $final = false,
        private ?AgentId $role = null,
        private Transitions $transitions = new Transitions(),
    ) {
        $this->name = new StringObject($name);
    }

    public function id(): ColumnId
    {
        return $this->id;
    }

    public function name(): string
    {
        return $this->name->getValue();
    }

    public function position(): Position
    {
        return $this->position;
    }

    public function isFinal(): bool
    {
        return $this->final;
    }

    public function role(): ?AgentId
    {
        return $this->role;
    }

    public function transitions(): Transitions
    {
        return $this->transitions;
    }

    public function rename(string $name): void
    {
        $this->name = new StringObject($name);
    }

    public function placeAt(Position $position): void
    {
        $this->position = $position;
    }

    public function markFinal(bool $final): void
    {
        $this->final = $final;
    }

    public function assignRole(?AgentId $role): void
    {
        $this->role = $role;
    }

    public function allow(Transitions $transitions): void
    {
        $this->transitions = $transitions;
    }
}
