<?php

declare(strict_types=1);

namespace ItpContext\Service;

final readonly class ParsedSymbol
{
    /**
     * @param ''|'class'|'enum'|'interface'|'trait' $kind
     * @param class-string $fqcn
     */
    public function __construct(
        public string $kind,
        public string $fqcn,
    ) {
    }
}
