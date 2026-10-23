<?php

declare(strict_types=1);

namespace App\Application\Runner;

use App\Domain\Kanban\Issue;
use App\Domain\Team\Agent;
use App\Domain\Team\ProfileId;
use App\Domain\Team\Profile;
use App\Domain\Tenancy\WorkspaceId;

final readonly class PromptComposer
{
    public function __construct(
        private ResolveProfile $resolveProfile,
    ) {
    }

    public function compose(WorkspaceId $workspaceId, Agent $agent, Issue $issue): string
    {
        return rtrim(implode("\n", array_filter([
            '# ' . $issue->key()->value() . ' — ' . $issue->summary(),
            $issue->description(),
            $agent->whoAmI(),
            $this->whoBlock($agent, $this->resolveProfile->execute($workspaceId, $agent->profileId())),
        ])));
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
