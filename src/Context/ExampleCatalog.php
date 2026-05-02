<?php

declare(strict_types=1);

namespace Smoke\Context;

use ItpContext\Enum\Tier;
use ItpContext\Model\RuleDef;

return [

    'SecurityBoundary' => new RuleDef(
        statement: 'TODO: Define rule statement.',
        tier: Tier::Standard,
        owner: 'Team-Example',
    ),
];
