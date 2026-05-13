<?php

declare(strict_types=1);

namespace ItpContext\Service;

use ItpContext\Attribute\Rule;
use ItpContext\Context\PackageRules;

#[Rule(PackageRules::DegradedDiscovery)]
final class Summarizer
{
    public function __construct(
        private ContextReader $reader = new ContextReader(),
    ) {
    }

    public function summarize(string $filePath): string
    {
        return implode(
            "\n",
            array_map(
                static fn (\ItpContext\Model\ContextDocument $document): string => $document->body,
                $this->reader->read($filePath)
            )
        );
    }

    public function handle(?string $filePath): void
    {
        try {
            echo $this->summarize((string)$filePath);
        } catch (\Throwable $throwable) {
            fwrite(STDERR, $throwable->getMessage() . "\n");
            exit(1);
        }
    }
}
