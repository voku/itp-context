<?php

declare(strict_types=1);

namespace ItpContext\Service;

use ItpContext\Attribute\Rule;
use ItpContext\Context\PackageRules;
use ItpContext\Contract\ContextDocumentReader;
use ItpContext\Model\ContextDocument;
use ItpContext\Model\ExportReport;
use RuntimeException;

#[Rule(PackageRules::AgentFriendlyMarkdown)]
#[Rule(PackageRules::DiscoveryMetadata)]
final class ContextExporter
{
    public function __construct(
        private ContextDocumentReader $reader = new ContextReader(),
    ) {
    }

    /**
     * @param list<string> $sourceDirs
     * @param list<string> $excludePaths
     */
    public function export(string $outputDir, array $sourceDirs, array $excludePaths = []): ExportReport
    {
        if ($sourceDirs === []) {
            throw new RuntimeException('At least one source directory is required.');
        }

        $files = $this->findPhpFiles($sourceDirs, $excludePaths);
        if ($files === []) {
            throw new RuntimeException('No PHP files found in the given source directories.');
        }

        $writer = new ExportWriter($outputDir);
        $writtenFiles = [];
        $errors = [];
        $skippedFileCount = 0;
        $documents = [];

        foreach ($files as $filePath) {
            try {
                $fileDocuments = $this->reader->read($filePath);
            } catch (\Throwable $throwable) {
                if ($throwable instanceof RuntimeException && $throwable->getMessage() === 'No class/interface/trait/enum/function found in file.') {
                    $skippedFileCount++;
                    continue;
                }

                $errors[] = $filePath . ': ' . $throwable->getMessage();
                continue;
            }

            foreach ($fileDocuments as $document) {
                if (!$document->hasRules()) {
                    $skippedFileCount++;
                    continue;
                }

                $fileName = $this->documentFileName($document);
                $writtenFiles[] = $writer->writeMarkdown(
                    area: 'php',
                    slug: substr($fileName, 4, -3),
                    meta: [
                        'id' => $document->id,
                        'title' => $document->title,
                        'source_path' => $document->sourcePath,
                        'kind' => $document->kind,
                        'rule_ids' => $document->ruleIds,
                        'owners' => $document->owners,
                        'refs' => $document->refs,
                        'verified_by' => $document->verifiedBy,
                        'annotated_methods' => $document->annotatedMethods,
                        'rule_count' => count($document->ruleIds),
                    ],
                    body: $document->body,
                );

                $documents[] = [
                    'title' => $document->title,
                    'kind' => $document->kind,
                    'source_path' => $document->sourcePath,
                    'file_name' => $fileName,
                    'rule_ids' => $document->ruleIds,
                    'owners' => $document->owners,
                    'refs' => $document->refs,
                    'verified_by' => $document->verifiedBy,
                    'annotated_methods' => $document->annotatedMethods,
                ];
            }
        }

        $writtenFiles[] = $writer->writeMarkdown(
            area: '',
            slug: 'index',
            meta: [
                'title' => 'Architecture context index',
            ],
            body: $this->renderIndex($documents),
        );

        return new ExportReport(
            outputDir: rtrim($outputDir, '/'),
            scannedFileCount: count($files),
            exportedDocumentCount: count($documents),
            skippedFileCount: $skippedFileCount,
            writtenFiles: $writtenFiles,
            errors: $errors,
        );
    }

    /**
     * @param list<array{
     *     title: string,
     *     kind: string,
     *     source_path: string,
     *     file_name: string,
     *     rule_ids: list<string>,
     *     owners: list<string>,
     *     refs: list<string>,
     *     verified_by: list<string>,
     *     annotated_methods: list<string>
     * }> $documents
     */
    private function renderIndex(array $documents): string
    {
        if ($documents === []) {
            return "# Architecture context export\n\nNo annotated PHP symbols were found.\n";
        }

        $lines = [
            '# Architecture context export',
            '',
            'Exported context documents for PHP symbols annotated with `#[Rule(...)]`.',
            '',
        ];

        foreach ($documents as $document) {
            $lines[] = '- [' . $document['title'] . '](' . $document['file_name'] . ')'
                . ' (' . $document['kind'] . ')'
                . ' - `' . $document['source_path'] . '`';

            if ($document['rule_ids'] !== []) {
                $lines[] = '  - rules: ' . $this->inlineCodeList($document['rule_ids']);
            }
            if ($document['owners'] !== []) {
                $lines[] = '  - owners: ' . implode(', ', $document['owners']);
            }
            if ($document['refs'] !== []) {
                $lines[] = '  - refs: ' . implode(', ', $document['refs']);
            }
            if ($document['verified_by'] !== []) {
                $lines[] = '  - proof: ' . implode(', ', $document['verified_by']);
            }
            if ($document['annotated_methods'] !== []) {
                $lines[] = '  - annotated methods: ' . $this->inlineCodeList($document['annotated_methods']);
            }
        }

        $lines[] = '';

        return implode("\n", $lines);
    }

    /**
     * @param list<string> $sourceDirs
     * @param list<string> $excludePaths
     * @return list<string>
     */
    private function findPhpFiles(array $sourceDirs, array $excludePaths): array
    {
        $files = [];

        foreach ($sourceDirs as $sourceDir) {
            if (!is_dir($sourceDir)) {
                continue;
            }

            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($sourceDir, \FilesystemIterator::SKIP_DOTS)
            );

            foreach ($iterator as $item) {
                if (!$item->isFile() || $item->getExtension() !== 'php') {
                    continue;
                }

                $path = $item->getPathname();
                if ($this->isExcluded($path, $excludePaths)) {
                    continue;
                }

                $files[] = $path;
            }
        }

        sort($files);

        return array_values(array_unique($files));
    }

    /**
     * @param list<string> $excludePaths
     */
    private function isExcluded(string $path, array $excludePaths): bool
    {
        $normalizedPath = str_replace('\\', '/', $path);

        foreach ($excludePaths as $excludePath) {
            $excludePath = trim(str_replace('\\', '/', $excludePath), '/');
            if ($excludePath !== '' && str_contains($normalizedPath, '/' . $excludePath . '/')) {
                return true;
            }
        }

        return false;
    }

    private function documentFileName(ContextDocument $document): string
    {
        if ($document->kind === 'function') {
            return 'php/function_' . str_replace('\\', '_', $document->title) . '.md';
        }

        return 'php/' . str_replace('\\', '_', $document->title) . '.md';
    }

    /**
     * @param list<string> $values
     */
    private function inlineCodeList(array $values): string
    {
        return implode(', ', array_map(static fn (string $value): string => '`' . $value . '`', $values));
    }
}
