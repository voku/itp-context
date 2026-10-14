<?php

declare(strict_types=1);

namespace ItpContext\Model;

/**
 * A rule that applies to at least one of the resolved files, listed once per rule id.
 */
final readonly class ApplicableRule
{
    /**
     * @param list<string> $refs
     * @param list<string> $subjects annotated symbols (`Class`, `Class::method`, `function`) carrying this rule, sorted
     */
    public function __construct(
        public string $ruleId,
        public string $statement,
        public ?string $changeGuidance,
        public array $refs,
        public array $subjects,
    ) {
    }
}
