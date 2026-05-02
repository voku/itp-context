<?php

declare(strict_types=1);

namespace Smoke\Context;

use ItpContext\Contract\RuleIdentifier;

enum ExampleRules implements RuleIdentifier
{
    case SecurityBoundary;
}
