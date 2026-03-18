<?php

declare(strict_types=1);

namespace ItpContext\Attribute;

use Attribute;
use ItpContext\Contract\RuleIdentifier;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::TARGET_FUNCTION)]
final readonly class Rule
{
    public function __construct(public RuleIdentifier $id)
    {
    }
}
