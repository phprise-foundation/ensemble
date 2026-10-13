<?php

declare(strict_types=1);

namespace App\Application;

use App\Domain\Agent\Agent;
use App\Domain\Agent\AgentId;
use App\Domain\Contract\AgentRegistry;
use App\Domain\Exception\AgentAlreadyExistsException;
use Phprise\Common\ValueObject\StringObject;

final readonly class CreateAgent
{
    public function __construct(private AgentRegistry $agents)
    {
    }

    public function execute(
        string $name,
        string $slug,
        string $description,
        string $rule = '',
        string $profile = '',
        string $model = '',
        string $fallbackModel = '',
        int $interval = 300,
        string $workspace = '/workspace',
    ): Agent {
        $id = new AgentId($slug !== '' ? $slug : (new StringObject($name))->toKebab());
        if ($this->agents->find($id) !== null) {
            throw AgentAlreadyExistsException::forId($id);
        }
        $agent = new Agent($id, $name, $description, true, $rule, $profile, $model, $fallbackModel, $interval, $workspace);
        $this->agents->save($agent);

        return $agent;
    }
}
