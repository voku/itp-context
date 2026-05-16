<?php

declare(strict_types=1);

namespace ItpContext\Contract;

use ItpContext\Model\RuleDef;
use UnitEnum;

interface RuleIdentifier extends UnitEnum
{
    public function getDefinition(): RuleDef;
}
