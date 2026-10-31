<?php

declare(strict_types=1);

namespace App\Application\Runner;

use App\Domain\Kanban\Issue;
use App\Domain\Kanban\Status;
use App\Domain\Team\Agent;
use App\Domain\Team\ProfileId;
use App\Domain\Team\Profile;
use App\Domain\Team\Role;
use App\Domain\Team\RoleCapabilities;
use App\Domain\Tenancy\WorkspaceId;

final readonly class PromptComposer
{
    public function __construct(
        private ResolveProfile $resolveProfile,
    ) {
    }

    public function compose(
        WorkspaceId $workspaceId,
        Agent $agent,
        Issue $issue,
        Status $status,
        Role $role,
        RoleCapabilities $capabilities,
    ): string {
        return rtrim(implode("\n", array_filter([
            '# ' . $issue->key()->value() . ' — ' . $issue->summary(),
            $issue->description(),
            $agent->whoAmI(),
            $this->whoBlock($agent, $this->resolveProfile->execute($workspaceId, $agent->profileId())),
            $this->stageBlock($status, $role, $capabilities),
        ])));
    }

    private function stageBlock(Status $status, Role $role, RoleCapabilities $capabilities): string
    {
        $lines = [
            '## Your stage',
            'Status: ' . $status->name(),
            'Role: ' . $role->name(),
        ];
        if ($role->description() !== '') {
            $lines[] = $role->description();
        }

        if ($capabilities->implement()) {
            $lines[] = 'You may edit repository files and run commands. The runner commits, pushes and opens the pull request; do not merge, deploy or create a PR yourself.';
            $lines[] = 'Definition of done: the requested change is implemented and the relevant checks pass.';
        } elseif ($capabilities->review()) {
            $lines[] = 'Review the current branch/PR. You may read the diff and run read-only commands. Do not edit files, commit, push, merge or deploy.';
            $lines[] = 'Record the decision with `ens review approve` or `ens review request-changes --reason "..."`.';
            $lines[] = 'Definition of done: an explicit review decision is recorded.';
        } elseif ($capabilities->test()) {
            $lines[] = 'Test the current branch at its HEAD. You may read files and run tests. Do not edit files, commit, push, merge or deploy.';
            $lines[] = 'Record the result with `ens test pass` or `ens test fail --reason "..."`.';
            $lines[] = 'Definition of done: an explicit pass/fail result is recorded.';
        } else {
            $lines[] = 'You work on the issue definition. Do not edit repository files, commit, push, merge or deploy.';
            $lines[] = 'Definition of done: the issue/specification you own is updated or clarified.';
        }

        $lines[] = 'If you are blocked, run `ens block --reason "..."`. Do not move the issue to another status yourself.';

        return implode("\n", $lines);
    }

    private function whoBlock(Agent $agent, ?Profile $profile): string
    {
        $topic = 'You are ' . $agent->name();
        $topic .= $agent->age() === null ? '' : ', ' . $agent->age() . ' years old';
        $topic .= ', working as an autonomous agent.';
        $topic .= $agent->profileId() instanceof ProfileId ? "\n" . $this->profileBlock($profile) : '';

        return $topic;
    }

    private function profileBlock(?Profile $profile): string
    {
        if ($profile === null) {
            return 'The referenced profile no longer exists; behave as a pragmatic colleague.';
        }

        return rtrim(implode("\n", array_filter([
            'Your profile is "' . $profile->name() . '".',
            $profile->style(),
            'Tone: ' . $profile->tone() . '. Emoji usage: ' . $profile->emojiUsage()->value . '.',
        ])));
    }
}
