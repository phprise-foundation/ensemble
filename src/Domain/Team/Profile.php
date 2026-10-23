<?php

declare(strict_types=1);

namespace App\Domain\Team;

use App\Domain\Exception\ValidationException;
use App\Domain\Tenancy\WorkspaceId;
use DateTimeImmutable;
use Phprise\Common\ValueObject\StringObject;

final class Profile
{
    private StringObject $name;
    private StringObject $style;
    private StringObject $tone;
    private EmojiUsage $emojiUsage;

    /** @var array<string, mixed> */
    private array $advanced;

    private ?DateTimeImmutable $updatedAt;

    /**
     * @param array<string, mixed> $advanced
     */
    public function __construct(
        private readonly ProfileId $id,
        private readonly WorkspaceId $workspaceId,
        private readonly TeamId $teamId,
        string $name,
        string $style = '',
        string $tone = '',
        EmojiUsage $emojiUsage = EmojiUsage::Never,
        array $advanced = [],
        private readonly ?DateTimeImmutable $createdAt = null,
        ?DateTimeImmutable $updatedAt = null,
    ) {
        $this->name = new StringObject($this->validName($name));
        $this->style = new StringObject($this->validStyle($style));
        $this->tone = new StringObject($this->validTone($tone));
        $this->emojiUsage = $emojiUsage;
        $this->advanced = $this->validAdvanced($advanced);
        $this->updatedAt = $updatedAt;
    }

    public function id(): ProfileId
    {
        return $this->id;
    }

    public function workspaceId(): WorkspaceId
    {
        return $this->workspaceId;
    }

    public function teamId(): TeamId
    {
        return $this->teamId;
    }

    public function name(): string
    {
        return $this->name->getValue();
    }

    public function style(): string
    {
        return $this->style->getValue();
    }

    public function tone(): string
    {
        return $this->tone->getValue();
    }

    public function emojiUsage(): EmojiUsage
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
        return $this->createdAt ?? new DateTimeImmutable();
    }

    public function updatedAt(): DateTimeImmutable
    {
        return $this->updatedAt ?? $this->createdAt();
    }

    public function rename(string $name): void
    {
        $this->name = new StringObject($this->validName($name));
    }

    public function restyle(string $style): void
    {
        $this->style = new StringObject($this->validStyle($style));
    }

    public function retone(string $tone): void
    {
        $this->tone = new StringObject($this->validTone($tone));
    }

    public function useEmoji(EmojiUsage $emojiUsage): void
    {
        $this->emojiUsage = $emojiUsage;
    }

    /** @param array<string, mixed> $advanced */
    public function advance(array $advanced): void
    {
        $this->advanced = $this->validAdvanced($advanced);
    }

    public function touch(DateTimeImmutable $at): void
    {
        $this->updatedAt = $at;
    }

    private function validName(string $name): string
    {
        $normalized = trim($name);
        if ($normalized === '' || mb_strlen($normalized) > 80) {
            throw new ValidationException('name', 'Profile name must have 1 to 80 characters.');
        }

        return $normalized;
    }

    private function validStyle(string $style): string
    {
        $normalized = trim($style);
        if (mb_strlen($normalized) > 2000) {
            throw new ValidationException('style', 'Profile style must have at most 2000 characters.');
        }

        return $normalized;
    }

    private function validTone(string $tone): string
    {
        $normalized = trim($tone);
        if (mb_strlen($normalized) > 80) {
            throw new ValidationException('tone', 'Profile tone must have at most 80 characters.');
        }

        return $normalized;
    }

    /**
     * @param array<array-key, mixed> $advanced
     * @return array<string, mixed>
     */
    private function validAdvanced(array $advanced): array
    {
        foreach (array_keys($advanced) as $key) {
            if (!is_string($key)) {
                throw new ValidationException('advanced', 'Profile advanced attributes must be a JSON object.');
            }
        }

        /** @var array<string, mixed> $advanced */
        return $advanced;
    }
}
