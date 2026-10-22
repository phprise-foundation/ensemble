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

    public function __construct(
        private readonly WorkspaceId $id,
        string $name,
        WorkspacePrefix $prefix,
        private readonly ?DateTimeImmutable $createdAt = null,
    ) {
        $this->name = new StringObject($this->validName($name));
        $this->prefix = $prefix;
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

    public function rename(string $name): void
    {
        $this->name = new StringObject($this->validName($name));
    }

    public function changePrefix(WorkspacePrefix $prefix): void
    {
        $this->prefix = $prefix;
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
