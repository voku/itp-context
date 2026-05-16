<?php

declare(strict_types=1);

namespace ItpContext\Service;

use ItpContext\Attribute\Rule;
use ItpContext\Context\PackageRules;
use ItpContext\Contract\RuleIdentifier;
use ItpContext\Model\RuleDef;

#[Rule(PackageRules::FrameworkAgnostic)]
#[Rule(PackageRules::InlineRuleDefinitions)]
final class ContextResolver
{
    public function resolve(RuleIdentifier $id): RuleDef
    {
        return $id->getDefinition();
    }
}
