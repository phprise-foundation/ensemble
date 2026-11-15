<?php

declare(strict_types=1);

namespace App\Domain\Tenancy;

use App\Domain\Exception\ValidationException;
use DateTimeImmutable;
use Phprise\Common\ValueObject\StringObject;

final class Workspace
{
    private StringObject $name;
    private WorkspacePrefix $prefix;
    private ?int $creditMicros;

    public function __construct(
        private readonly WorkspaceId $id,
        string $name,
        WorkspacePrefix $prefix,
        private readonly ?DateTimeImmutable $createdAt = null,
        ?int $creditMicros = null,
    ) {
        $this->name = new StringObject($this->validName($name));
        $this->prefix = $prefix;
        $this->creditMicros = $this->validCredit($creditMicros);
    }

    public function id(): WorkspaceId
    {
        return $this->id;
    }

    public function name(): string
    {
        return $this->name->getValue();
    }

    public function prefix(): WorkspacePrefix
    {
        return $this->prefix;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt ?? new DateTimeImmutable();
    }

    public function creditMicros(): ?int
    {
        return $this->creditMicros;
    }

    public function changeCredit(?int $creditMicros): void
    {
        $this->creditMicros = $this->validCredit($creditMicros);
    }

    public function rename(string $name): void
    {
        $this->name = new StringObject($this->validName($name));
    }

    public function changePrefix(WorkspacePrefix $prefix): void
    {
        $this->prefix = $prefix;
    }

    private function validCredit(?int $creditMicros): ?int
    {
        if ($creditMicros !== null && $creditMicros < 0) {
            throw new ValidationException('creditMicros', 'Workspace credit must not be negative.');
        }

        return $creditMicros;
    }

    private function validName(string $name): string
    {
        $normalized = trim($name);
        if ($normalized === '' || mb_strlen($normalized) > 60) {
            throw new ValidationException('name', 'Workspace name must have 1 to 60 characters.');
        }

        return $normalized;
    }
}
