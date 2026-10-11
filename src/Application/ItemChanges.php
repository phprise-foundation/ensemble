<?php

declare(strict_types=1);

namespace App\Application;

use App\Domain\Board\Item;
use App\Domain\Board\Role;
use App\Domain\Board\Status;

final class ItemChanges
{
    public function __construct(
        private ?Status $status = null,
        private ?Role $role = null,
        private bool $roleChanged = false,
        private ?string $worker = null,
        private bool $workerChanged = false,
        private ?bool $blocked = null,
        private ?string $blockedBy = null,
        private bool $blockedByChanged = false,
        private ?string $title = null,
        private ?string $body = null,
    ) {
    }

    public function applyTo(Item $item): void
    {
        $this->applyStatus($item);
        $this->applyRole($item);
        $this->applyWorker($item);
        $this->applyBlocked($item);
        $this->applyTitle($item);
        $this->applyBody($item);
    }

    private function applyStatus(Item $item): void
    {
        if ($this->status !== null) {
            $item->moveTo($this->status);
        }
    }

    private function applyRole(Item $item): void
    {
        if ($this->roleChanged) {
            $item->assignRole($this->role);
        }
    }

    private function applyWorker(Item $item): void
    {
        if (!$this->workerChanged) {
            return;
        }
        if ($this->worker === null || $this->worker === '') {
            $item->clearWorker();

            return;
        }
        $item->assignWorker($this->worker);
    }

    private function applyBlocked(Item $item): void
    {
        if ($this->blocked === true) {
            $item->block($this->blockedBy ?? '');

            return;
        }
        if ($this->blocked === false) {
            $item->unblock();

            return;
        }
        if ($this->blockedByChanged && $this->blockedBy !== null && $this->blockedBy !== '') {
            $item->block($this->blockedBy);
        }
    }

    private function applyTitle(Item $item): void
    {
        if ($this->title !== null) {
            $item->rename($this->title);
        }
    }

    private function applyBody(Item $item): void
    {
        if ($this->body !== null) {
            $item->describe($this->body);
        }
    }
}
