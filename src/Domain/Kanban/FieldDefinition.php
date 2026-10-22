<?php

declare(strict_types=1);

namespace App\Domain\Kanban;

use App\Domain\Exception\ValidationException;
use App\Domain\Tenancy\WorkspaceId;
use DateTimeImmutable;
use Phprise\Common\ValueObject\StringObject;

final class FieldDefinition
{
    private StringObject $label;
    private FieldType $type;
    private FieldOptions $options;
    private bool $required;
    private Position $position;
    private ?DateTimeImmutable $updatedAt;

    public function __construct(
        private readonly FieldDefinitionId $id,
        private readonly WorkspaceId $workspaceId,
        private readonly IssueTypeId $issueTypeId,
        private readonly Slug $key,
        string $label,
        FieldType $type,
        FieldOptions $options,
        bool $required = false,
        Position $position = new Position(0),
        private readonly ?DateTimeImmutable $createdAt = null,
        ?DateTimeImmutable $updatedAt = null,
    ) {
        $this->label = new StringObject($this->validLabel($label));
        $this->type = $type;
        $this->options = $options;
        $this->required = $required;
        $this->position = $position;
        $this->updatedAt = $updatedAt;
        $this->guardOptions();
    }

    public function id(): FieldDefinitionId
    {
        return $this->id;
    }

    public function workspaceId(): WorkspaceId
    {
        return $this->workspaceId;
    }

    public function issueTypeId(): IssueTypeId
    {
        return $this->issueTypeId;
    }

    public function key(): Slug
    {
        return $this->key;
    }

    public function label(): string
    {
        return $this->label->getValue();
    }

    public function type(): FieldType
    {
        return $this->type;
    }

    public function options(): FieldOptions
    {
        return $this->options;
    }

    public function isRequired(): bool
    {
        return $this->required;
    }

    public function position(): Position
    {
        return $this->position;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt ?? new DateTimeImmutable();
    }

    public function updatedAt(): DateTimeImmutable
    {
        return $this->updatedAt ?? $this->createdAt();
    }

    public function rename(string $label): void
    {
        $this->label = new StringObject($this->validLabel($label));
    }

    public function changeType(FieldType $type, FieldOptions $options): void
    {
        $this->type = $type;
        $this->options = $options;
        $this->guardOptions();
    }

    public function require(bool $required): void
    {
        $this->required = $required;
    }

    public function placeAt(Position $position): void
    {
        $this->position = $position;
    }

    public function touch(DateTimeImmutable $at): void
    {
        $this->updatedAt = $at;
    }

    private function guardOptions(): void
    {
        if ($this->type->allowsOptions() && $this->options->isEmpty()) {
            throw new ValidationException('options', 'Select fields require at least one option.');
        }
        if (!$this->type->allowsOptions() && !$this->options->isEmpty()) {
            throw new ValidationException('options', 'Only select fields accept options.');
        }
    }

    private function validLabel(string $label): string
    {
        $normalized = trim($label);
        if ($normalized === '' || mb_strlen($normalized) > 80) {
            throw new ValidationException('label', 'Field label must have 1 to 80 characters.');
        }

        return $normalized;
    }
}
