<?php

declare(strict_types=1);

namespace App\Interface\Http\Tenancy;

use App\Application\Security\ReadCurrentUser;
use App\Application\Tenancy\WorkspaceGuard;
use App\Domain\Contract\Runner\RunUsageRepository;
use App\Domain\Contract\Tenancy\WorkspaceRepository;
use App\Domain\Exception\NotFoundException;
use App\Domain\Runner\WorkspaceUsage;
use App\Domain\Tenancy\WorkspaceId;
use App\Interface\Http\BearerToken;
use App\Interface\Http\JsonResponder;
use DateTimeImmutable;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final readonly class UsageController
{
    public function __construct(
        private ReadCurrentUser $currentUser,
        private WorkspaceRepository $workspaces,
        private WorkspaceGuard $guard,
        private RunUsageRepository $usage,
        private JsonResponder $responder,
    ) {
    }

    public function show(ServerRequestInterface $request, WorkspaceId $workspaceId): ResponseInterface
    {
        $user = $this->currentUser->execute(BearerToken::fromRequest($request));
        $workspace = $this->workspaces->find($workspaceId) ?? throw NotFoundException::workspace();
        $this->guard->assertMember($user, $workspaceId);
        $all = $this->usage->summaryForWorkspace($workspaceId);
        $today = $this->usage->summaryForWorkspace($workspaceId, new DateTimeImmutable('today'));
        $month = $this->usage->summaryForWorkspace($workspaceId, new DateTimeImmutable('first day of this month'));
        $credit = $workspace->creditMicros();

        return $this->responder->respond([
            'creditMicros' => $credit,
            'chargedMicros' => $all->chargedMicros(),
            'remainingMicros' => $credit === null ? null : max(0, $credit - $all->chargedMicros()),
            'all' => $this->present($all),
            'today' => $this->present($today),
            'month' => $this->present($month),
        ]);
    }

    /** @return array<string, int> */
    private function present(WorkspaceUsage $usage): array
    {
        return [
            'chargedMicros' => $usage->chargedMicros(),
            'actualCostMicros' => $usage->actualCostMicros(),
            'requests' => $usage->requests(),
            'inputTokens' => $usage->inputTokens(),
            'outputTokens' => $usage->outputTokens(),
            'reasoningTokens' => $usage->reasoningTokens(),
            'cacheReadTokens' => $usage->cacheReadTokens(),
            'cacheWriteTokens' => $usage->cacheWriteTokens(),
        ];
    }
}
