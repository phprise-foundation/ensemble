<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Runner;

use App\Application\Runner\MergePullRequest;
use App\Domain\Kanban\Issue;
use App\Domain\Kanban\IssueId;
use App\Domain\Kanban\IssueKey;
use App\Domain\Kanban\IssueTypeId;
use App\Domain\Kanban\StatusId;
use App\Domain\Tenancy\WorkspaceId;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class MergePullRequestTest extends TestCase
{
    public function testBuildsTheMergeCommitTitleWithTheAgentAndConventionalType(): void
    {
        $issue = new Issue(
            IssueId::generate(),
            new IssueKey('KAN-19'),
            WorkspaceId::generate(),
            IssueTypeId::generate(),
            StatusId::generate(),
            'Novo teste de integração',
            '',
            'admin',
        );

        $reflection = new ReflectionClass(MergePullRequest::class);
        $merge = $reflection->newInstanceWithoutConstructor();
        $method = $reflection->getMethod('mergeCommitTitle');
        $title = $method->invoke($merge, $issue, 'Kaio', 'feat/KAN-19-novo-teste-de-integracao', 7);

        self::assertSame('feat(Kaio/KAN-19): Novo teste de integração (#7)', $title);
    }

    public function testLegacyAgentBranchesFallBackToFeat(): void
    {
        $issue = new Issue(
            IssueId::generate(),
            new IssueKey('KAN-20'),
            WorkspaceId::generate(),
            IssueTypeId::generate(),
            StatusId::generate(),
            'Ship it',
            '',
            'admin',
        );

        $reflection = new ReflectionClass(MergePullRequest::class);
        $merge = $reflection->newInstanceWithoutConstructor();
        $method = $reflection->getMethod('mergeCommitTitle');
        self::assertSame('feat(Kaio/KAN-20): Ship it (#8)', $method->invoke($merge, $issue, 'Kaio', 'agent/KAN-20', 8));
    }
}
