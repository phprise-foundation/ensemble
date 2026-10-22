<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Entity;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'field_definitions')]
#[ORM\UniqueConstraint(name: 'uniq_field_definitions_issue_type_key', columns: ['issue_type_id', 'key'])]
#[ORM\Index(name: 'idx_field_definitions_issue_type', columns: ['issue_type_id'])]
#[ORM\Index(name: 'idx_field_definitions_workspace', columns: ['workspace_id'])]
final class FieldDefinitionRecord
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 32)]
    private string $id;

    #[ORM\Column(name: 'workspace_id', type: 'string', length: 32)]
    private string $workspaceId;

    #[ORM\Column(name: 'issue_type_id', type: 'string', length: 32)]
    private string $issueTypeId;

    #[ORM\Column(type: 'string', length: 64)]
    private string $key;

    #[ORM\Column(type: 'string', length: 80)]
    private string $label;

    #[ORM\Column(type: 'string', length: 32)]
    private string $type;

    #[ORM\Column(type: 'boolean')]
    private bool $required;

    /** @var list<string> */
    #[ORM\Column(type: 'json')]
    private array $options;

    #[ORM\Column(type: 'integer')]
    private int $position;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: 'datetime_immutable')]
    private DateTimeImmutable $updatedAt;

    /** @param list<string> $options */
    public function __construct(
        string $id,
        string $workspaceId,
        string $issueTypeId,
        string $key,
        string $label,
        string $type,
        bool $required,
        array $options,
        int $position,
        DateTimeImmutable $createdAt,
        DateTimeImmutable $updatedAt,
    ) {
        $this->id = $id;
        $this->workspaceId = $workspaceId;
        $this->issueTypeId = $issueTypeId;
        $this->key = $key;
        $this->label = $label;
        $this->type = $type;
        $this->required = $required;
        $this->options = $options;
        $this->position = $position;
        $this->createdAt = $createdAt;
        $this->updatedAt = $updatedAt;
    }

    public function id(): string
    {
        return $this->id;
    }

    public function workspaceId(): string
    {
        return $this->workspaceId;
    }

    public function issueTypeId(): string
    {
        return $this->issueTypeId;
    }

    public function key(): string
    {
        return $this->key;
    }

    public function label(): string
    {
        return $this->label;
    }

    public function type(): string
    {
        return $this->type;
    }

    public function isRequired(): bool
    {
        return $this->required;
    }

    /** @return list<string> */
    public function options(): array
    {
        return $this->options;
    }

    public function position(): int
    {
        return $this->position;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /** @param list<string> $options */
    public function sync(string $label, string $type, bool $required, array $options, int $position, DateTimeImmutable $updatedAt): void
    {
        $this->label = $label;
        $this->type = $type;
        $this->required = $required;
        $this->options = $options;
        $this->position = $position;
        $this->updatedAt = $updatedAt;
    }
}
