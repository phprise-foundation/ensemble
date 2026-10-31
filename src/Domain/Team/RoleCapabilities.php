<?php

declare(strict_types=1);

namespace App\Domain\Team;

final readonly class RoleCapabilities
{
    private function __construct(
        private bool $implement,
        private bool $review,
        private bool $test,
        private bool $merge,
        private bool $deploy,
        private bool $interactive,
        private bool $unblock,
    ) {
    }

    public static function none(): self
    {
        return new self(false, false, false, false, false, false, false);
    }

    /**
     * @param list<Role> $roles
     */
    public static function fromRoles(array $roles): self
    {
        $capabilities = self::none();
        foreach ($roles as $role) {
            $capabilities = $capabilities->withRole($role);
        }

        return $capabilities;
    }

    public function implement(): bool
    {
        return $this->implement;
    }

    public function review(): bool
    {
        return $this->review;
    }

    public function test(): bool
    {
        return $this->test;
    }

    public function merge(): bool
    {
        return $this->merge;
    }

    public function deploy(): bool
    {
        return $this->deploy;
    }

    public function interactive(): bool
    {
        return $this->interactive;
    }

    public function unblock(): bool
    {
        return $this->unblock;
    }

    public function requiresOutcome(): bool
    {
        return $this->review || $this->test;
    }

    private function withRole(Role $role): self
    {
        return new self(
            $this->implement || $role->implement(),
            $this->review || $role->review(),
            $this->test || $role->test(),
            $this->merge || $role->merge(),
            $this->deploy || $role->deploy(),
            $this->interactive || $role->interactive(),
            $this->unblock || $role->unblock(),
        );
    }
}
