<?php

declare(strict_types=1);

namespace App\Domain\Exception;

use RuntimeException;

final class ConflictException extends RuntimeException
{
    public static function duplicateLogin(): self
    {
        return new self('login already in use');
    }

    public static function duplicateWorkspace(): self
    {
        return new self('workspace already exists');
    }

    public static function lastOwner(): self
    {
        return new self('a workspace must keep at least one owner');
    }

    public static function duplicateKey(): self
    {
        return new self('key already in use');
    }

    public static function duplicateName(string $resource): self
    {
        return new self($resource . ' name already in use');
    }

    public static function inUse(string $resource): self
    {
        return new self($resource . ' still has children');
    }

    public static function initialStatus(): self
    {
        return new self('issue type already has an initial status');
    }

    public static function transitionExists(): self
    {
        return new self('transition already exists');
    }

    public static function workdirBound(): self
    {
        return new self('workdir is already bound to another repository');
    }

    public static function transitionNotAllowed(): self
    {
        return new self('transition not allowed for the current status');
    }
}
