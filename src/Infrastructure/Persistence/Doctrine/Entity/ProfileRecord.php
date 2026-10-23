<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Entity;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'profiles')]
#[ORM\Index(name: 'idx_profiles_workspace', columns: ['workspace_id'])]
#[ORM\Index(name: 'idx_profiles_team', columns: ['team_id'])]
final class ProfileRecord
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 32)]
    private string $id;

    #[ORM\Column(name: 'workspace_id', type: 'string', length: 32)]
    private string $workspaceId;

    #[ORM\Column(name: 'team_id', type: 'string', length: 32)]
    private string $teamId;

    #[ORM\Column(type: 'string', length: 80)]
    private string $name;

    #[ORM\Column(type: 'text')]
    private string $style;

    #[ORM\Column(type: 'string', length: 80)]
    private string $tone;

    #[ORM\Column(name: 'emoji_usage', type: 'string', length: 16)]
    private string $emojiUsage;

    /** @var array<string, mixed> */
    #[ORM\Column(type: 'json')]
    private array $advanced;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: 'datetime_immutable')]
    private DateTimeImmutable $updatedAt;

    /**
     * @param array<string, mixed> $advanced
     */
    public function __construct(
        string $id,
        string $workspaceId,
        string $teamId,
        string $name,
        string $style,
        string $tone,
        string $emojiUsage,
        array $advanced,
        DateTimeImmutable $createdAt,
        DateTimeImmutable $updatedAt,
    ) {
        $this->id = $id;
        $this->workspaceId = $workspaceId;
        $this->teamId = $teamId;
        $this->name = $name;
        $this->style = $style;
        $this->tone = $tone;
        $this->emojiUsage = $emojiUsage;
        $this->advanced = $advanced;
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

    public function teamId(): string
    {
        return $this->teamId;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function style(): string
    {
        return $this->style;
    }

    public function tone(): string
    {
        return $this->tone;
    }

    public function emojiUsage(): string
    {
        return $this->emojiUsage;
    }

    /** @return array<string, mixed> */
    public function advanced(): array
    {
        return $this->advanced;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /** @param array<string, mixed> $advanced */
    public function sync(
        string $name,
        string $style,
        string $tone,
        string $emojiUsage,
        array $advanced,
        DateTimeImmutable $updatedAt,
    ): void {
        $this->name = $name;
        $this->style = $style;
        $this->tone = $tone;
        $this->emojiUsage = $emojiUsage;
        $this->advanced = $advanced;
        $this->updatedAt = $updatedAt;
    }
}
