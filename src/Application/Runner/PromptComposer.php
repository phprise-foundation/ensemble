<?php

declare(strict_types=1);

namespace App\Application\Runner;

use App\Domain\Kanban\Issue;
use App\Domain\Kanban\Status;
use App\Domain\Team\Agent;
use App\Domain\Team\Role;
use App\Domain\Team\RoleCapabilities;
use App\Domain\Tenancy\WorkspaceId;

final readonly class PromptComposer
{
    public function compose(
        WorkspaceId $_workspaceId,
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
            $this->whoBlock($agent),
            'Always write user-facing comments, notes and messages in ' . $agent->language() . '.',
            'For questions about your appearance or personal details, run `ens profile physical` (or `communication`, `personality`, `interests`). Do not load your profile for technical work; user-facing comments and descriptions are stylized by the platform.',
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
        if ($role->instructions() !== '') {
            $lines[] = $role->instructions();
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

        $lines[] = 'To leave a note or refinement on the issue, run `ens comment --body "..."`. Do not use the board API or act as an administrator.';
        if ($capabilities->interactive()) {
            $lines[] = 'To update the issue description, run `ens description --body "..."`; the history records you.';
        }
        $lines[] = 'If you are blocked, run `ens block --reason "..."`. Do not move the issue to another status yourself.';

        return implode("\n", $lines);
    }

    private function whoBlock(Agent $agent): string
    {
        $topic = 'You are ' . $agent->name();
        $topic .= $agent->age() === null ? '' : ', ' . $agent->age() . ' years old';
        $topic .= ', working as an autonomous agent.';

        return $topic;
    }
}
