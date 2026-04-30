<?php

declare(strict_types=1);

namespace ItpContext\Model;

final readonly class ExportReport
{
    /**
     * @param list<string> $writtenFiles
     * @param list<string> $errors
     */
    public function __construct(
        public string $outputDir,
        public int $scannedFileCount,
        public int $exportedDocumentCount,
        public int $skippedFileCount,
        public array $writtenFiles,
        public array $errors = [],
    ) {
    }

    public function hasErrors(): bool
    {
        return $this->errors !== [];
    }
}
