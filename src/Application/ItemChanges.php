<?php

declare(strict_types=1);

namespace App\Application;

use App\Domain\Agent\AgentId;
use App\Domain\Board\Item;
use App\Domain\Workflow\ColumnId;
use DateTimeImmutable;

final class ItemChanges
{
    public function __construct(
        private ?ColumnId $column = null,
        private ?AgentId $agent = null,
        private bool $agentChanged = false,
        private ?string $worker = null,
        private bool $workerChanged = false,
        private ?bool $blocked = null,
        private ?string $blockedBy = null,
        private bool $blockedByChanged = false,
        private ?string $title = null,
        private ?string $body = null,
    ) {
    }

    public function applyTo(Item $item, DateTimeImmutable $at): void
    {
        $this->applyColumn($item, $at);
        $this->applyAgent($item, $at);
        $this->applyWorker($item, $at);
        $this->applyBlocked($item, $at);
        $this->applyTitle($item, $at);
        $this->applyBody($item, $at);
    }

    private function applyColumn(Item $item, DateTimeImmutable $at): void
    {
        if ($this->column !== null) {
            $item->moveTo($this->column, $at);
        }
    }

    private function applyAgent(Item $item, DateTimeImmutable $at): void
    {
        if ($this->agentChanged) {
            $item->assignAgent($this->agent, $at);
        }
    }

    private function applyWorker(Item $item, DateTimeImmutable $at): void
    {
        if (!$this->workerChanged) {
            return;
        }
        if ($this->worker === null || $this->worker === '') {
            $item->clearWorker($at);

            return;
        }
        $item->assignWorker($this->worker, $at);
    }

    private function applyBlocked(Item $item, DateTimeImmutable $at): void
    {
        if ($this->blocked === true) {
            $item->block($this->blockedBy ?? '', $at);

            return;
        }
        if ($this->blocked === false) {
            $item->unblock($at);

            return;
        }
        if ($this->blockedByChanged && $this->blockedBy !== null && $this->blockedBy !== '') {
            $item->block($this->blockedBy, $at);
        }
    }

    private function applyTitle(Item $item, DateTimeImmutable $at): void
    {
        if ($this->title !== null) {
            $item->rename($this->title, $at);
        }
    }

    private function applyBody(Item $item, DateTimeImmutable $at): void
    {
        if ($this->body !== null) {
            $item->describe($this->body, $at);
        }
    }
}
