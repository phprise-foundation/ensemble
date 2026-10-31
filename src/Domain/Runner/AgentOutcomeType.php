<?php

declare(strict_types=1);

namespace App\Domain\Runner;

enum AgentOutcomeType: string
{
    case None = 'none';
    case TestPassed = 'test_passed';
    case TestFailed = 'test_failed';
    case ReviewApproved = 'review_approved';
    case ChangesRequested = 'changes_requested';
    case Blocked = 'blocked';
    case Invalid = 'invalid';
}
