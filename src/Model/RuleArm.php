<?php

declare(strict_types=1);

namespace ItpContext\Model;

/**
 * Statically scanned `self::Case => new RuleDef(...)` arm of a rule enum.
 */
final readonly class RuleArm
{
    /**
     * @param list<string> $refs
     */
    public function __construct(
        public string $ruleId,
        public string $fingerprint,
        public ?string $statement,
        public ?string $changeGuidance,
        public array $refs,
    ) {
    }
}
