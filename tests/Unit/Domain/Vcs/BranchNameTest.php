<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Vcs;

use App\Domain\Kanban\IssueKey;
use App\Domain\Kanban\IssueType;
use App\Domain\Kanban\IssueTypeId;
use App\Domain\Kanban\Level;
use App\Domain\Kanban\Slug;
use App\Domain\Kanban\WorkflowId;
use App\Domain\Tenancy\WorkspaceId;
use App\Domain\Vcs\BranchName;
use PHPUnit\Framework\TestCase;

final class BranchNameTest extends TestCase
{
    public function testBuildsAConventionalBranchFromTheIssueTypeAndTransliteratedSummary(): void
    {
        $type = new IssueType(
            IssueTypeId::generate(),
            WorkspaceId::generate(),
            WorkflowId::generate(),
            new Slug('task'),
            'Task',
            new Level(1),
        );

        $branch = BranchName::forValues(new IssueKey('KAN-19'), 'Novo teste de integração 4', $type);

        self::assertSame('feat/KAN-19-novo-teste-de-integracao-4', $branch->value());
    }

    public function testMapsBugTypesToFix(): void
    {
        $type = new IssueType(
            IssueTypeId::generate(),
            WorkspaceId::generate(),
            WorkflowId::generate(),
            new Slug('bug'),
            'Bug',
            new Level(1),
        );

        self::assertSame('fix/KAN-20-quebrou-o-login', BranchName::forValues(new IssueKey('KAN-20'), 'Quebrou o login', $type)->value());
    }
}
