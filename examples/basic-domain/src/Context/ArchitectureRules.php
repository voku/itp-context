<?php

declare(strict_types=1);

namespace ItpContextExample\Context;

use ItpContext\Contract\RuleIdentifier;
use ItpContext\Enum\Tier;
use ItpContext\Model\RuleDef;
use ItpContextExample\Tests\I18nTest;

enum ArchitectureRules implements RuleIdentifier
{
    case ViewAbstraction;
    case I18n;

    public function getDefinition(): RuleDef
    {
        return match ($this) {
            self::ViewAbstraction => new RuleDef(
                statement: 'Use a dedicated view abstraction for rendering.',
                tier: Tier::Standard,
                owner: 'Team-Architecture',
                rationale: 'A dedicated view layer keeps rendering concerns isolated from domain and controller code.',
                refs: ['docs/adr/view-abstraction.md', 'docs/ui/rendering.md'],
            ),
            self::I18n => new RuleDef(
                statement: 'Use locale-aware formatting and translated UI labels.',
                tier: Tier::Standard,
                owner: 'Team-Architecture',
                rationale: 'Locale-aware rendering avoids user-facing regressions once the UI contains translated labels and formatted values.',
                verifiedBy: [I18nTest::class],
                refs: ['docs/adr/i18n.md'],
            ),
        };
    }
}
