<?php

declare(strict_types=1);

namespace ItpContext\Model;

final readonly class ContextDocument
{
    /**
     * @param list<string> $ruleIds
     * @param list<string> $owners
     * @param list<string> $refs
     * @param list<string> $verifiedBy
     * @param list<string> $annotatedMethods
     */
    public function __construct(
        public string $id,
        public string $title,
        public string $kind,
        public string $sourcePath,
        public array $ruleIds,
        public array $owners,
        public array $refs,
        public array $verifiedBy,
        public array $annotatedMethods,
        public string $body,
    ) {
    }

    public function hasRules(): bool
    {
        return $this->ruleIds !== [];
    }
}
