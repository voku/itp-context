<?php

declare(strict_types=1);

namespace ItpContext\Attribute;

use Attribute;
use ItpContext\Contract\RuleIdentifier;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::TARGET_FUNCTION | Attribute::IS_REPEATABLE)]
final readonly class Rule
{
    public function __construct(public RuleIdentifier $id)
    {
    }
}
