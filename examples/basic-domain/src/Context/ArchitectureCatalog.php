<?php

declare(strict_types=1);

namespace ItpContextExample\Context;

use ItpContext\Enum\Tier;
use ItpContext\Model\RuleDef;
use ItpContextExample\Tests\I18nTest;

return [
    'ViewAbstraction' => new RuleDef(
        statement: 'Use a dedicated view abstraction for rendering.',
        tier: Tier::Standard,
        owner: 'Team-Architecture',
        refs: ['docs/adr/view-abstraction.md'],
    ),
    'I18n' => new RuleDef(
        statement: 'Use locale-aware formatting and translated UI labels.',
        tier: Tier::Standard,
        owner: 'Team-Architecture',
        verifiedBy: [I18nTest::class],
        refs: ['docs/adr/i18n.md'],
    ),
];
