<?php

declare(strict_types=1);

namespace ItpContextExample\Context;

use ItpContext\Contract\RuleIdentifier;

enum ArchitectureRules implements RuleIdentifier
{
    case ViewAbstraction;
    case I18n;
}
