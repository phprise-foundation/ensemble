<?php

declare(strict_types=1);

namespace App\Domain\Agent;

use Phprise\Common\ValueObject\StringObject;

final class Agent
{
    private StringObject $name;
    private StringObject $description;
    private StringObject $profile;
    private StringObject $model;
    private StringObject $fallbackModel;
    private StringObject $workspace;

    public function __construct(
        private readonly AgentId $id,
        string $name,
        string $description = '',
        private bool $active = true,
        string $profile = '',
        string $model = '',
        string $fallbackModel = '',
        private int $interval = 300,
        string $workspace = '/workspace',
    ) {
        $this->name = new StringObject($name);
        $this->description = new StringObject($description);
        $this->profile = new StringObject($profile);
        $this->model = new StringObject($model);
        $this->fallbackModel = new StringObject($fallbackModel);
        $this->workspace = new StringObject($workspace);
    }

    public function id(): AgentId
    {
        return $this->id;
    }

    public function name(): string
    {
        return $this->name->getValue();
    }

    public function description(): string
    {
        return $this->description->getValue();
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function profile(): string
    {
        return $this->profile->getValue();
    }

    public function model(): string
    {
        return $this->model->getValue();
    }

    public function fallbackModel(): string
    {
        return $this->fallbackModel->getValue();
    }

    public function interval(): int
    {
        return $this->interval;
    }

    public function workspace(): string
    {
        return $this->workspace->getValue();
    }

    public function rename(string $name): void
    {
        $this->name = new StringObject($name);
    }

    public function describe(string $description): void
    {
        $this->description = new StringObject($description);
    }

    public function activate(bool $active): void
    {
        $this->active = $active;
    }

    public function configure(string $profile, string $model, string $fallbackModel, int $interval, string $workspace): void
    {
        $this->profile = new StringObject($profile);
        $this->model = new StringObject($model);
        $this->fallbackModel = new StringObject($fallbackModel);
        $this->interval = $interval;
        $this->workspace = new StringObject($workspace);
    }
}
