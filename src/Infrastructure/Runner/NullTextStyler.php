<?php

declare(strict_types=1);

namespace App\Infrastructure\Runner;

use App\Domain\Contract\Runner\TextStyler;
use App\Domain\Team\Agent;
use App\Domain\Team\Profile;
use Override;

final readonly class NullTextStyler implements TextStyler
{
    #[Override]
    public function styleMany(array $texts, Agent $agent, ?Profile $profile): array
    {
        return $texts;
    }
}
