<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Team;

use App\Domain\Team\Role;
use App\Domain\Team\RoleCapabilities;
use App\Domain\Team\RoleId;
use App\Domain\Team\TeamId;
use App\Domain\Tenancy\WorkspaceId;
use PHPUnit\Framework\TestCase;

final class RoleCapabilitiesTest extends TestCase
{
    public function testUnionsTheCapabilitiesOfTheStageRoles(): void
    {
        $workspaceId = new WorkspaceId(bin2hex(random_bytes(16)));
        $teamId = TeamId::generate();
        $reviewer = new Role(RoleId::generate(), $workspaceId, $teamId, 'Reviewer', '', false, false, review: true);
        $qa = new Role(RoleId::generate(), $workspaceId, $teamId, 'QA', '', false, false, test: true);

        $capabilities = RoleCapabilities::fromRoles([$reviewer, $qa]);

        self::assertTrue($capabilities->review());
        self::assertTrue($capabilities->test());
        self::assertFalse($capabilities->implement());
        self::assertTrue($capabilities->requiresOutcome());
    }

    public function testNoRolesMeansNoCapabilities(): void
    {
        $capabilities = RoleCapabilities::none();

        self::assertFalse($capabilities->implement());
        self::assertFalse($capabilities->review());
        self::assertFalse($capabilities->test());
        self::assertFalse($capabilities->requiresOutcome());
    }
}
