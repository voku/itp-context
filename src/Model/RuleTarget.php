<?php

declare(strict_types=1);

namespace ItpContext\Model;

final readonly class RuleTarget
{
    /**
     * @param 'class'|'enum'|'function'|'interface'|'method'|'trait' $kind
     * @param list<string> $ruleIds
     * @param string $fingerprint hash of the whitespace- and comment-insensitive declaration tokens
     */
    public function __construct(
        public string $kind,
        public string $name,
        public string $fqcn,
        public ?string $ownerFqcn = null,
        public array $ruleIds = [],
        public string $fingerprint = '',
    ) {
    }

    public function hasRules(): bool
    {
        return $this->ruleIds !== [];
    }
}
