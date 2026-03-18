<?php

declare(strict_types=1);

namespace ItpContext\Model;

use ItpContext\Enum\Tier;
use LogicException;

final readonly class RuleDef
{
    /**
     * @param array<class-string> $verifiedBy
     * @param list<string> $refs
     */
    public function __construct(
        public string $statement,
        public Tier $tier = Tier::Standard,
        public ?string $rationale = null,
        public ?string $owner = null,
        public array $verifiedBy = [],
        public array $refs = [],
    ) {
        if (trim($statement) === '') {
            throw new LogicException('Rule statement cannot be empty.');
        }

        if ($tier->requiresProof() && $verifiedBy === []) {
            throw new LogicException("Integrity violation: critical rule '{$statement}' must have proof (verifiedBy).");
        }

        if ($tier->isEnforcedStrictly() && ($owner === null || trim($owner) === '')) {
            throw new LogicException("Integrity violation: strict rule '{$statement}' must have an explicitly assigned owner.");
        }
    }
}
