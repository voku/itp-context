<?php

declare(strict_types=1);

namespace ItpContext\Service;

use ItpContext\Attribute\Rule;
use ItpContext\Context\PackageRules;
use ItpContext\Model\ExportReport;
use ReflectionAttribute;
use ReflectionClass;
use RuntimeException;

#[Rule(PackageRules::AgentFriendlyMarkdown)]
final class ContextExporter
{
    public function __construct(
        private Summarizer $summarizer = new Summarizer(),
        private ContextResolver $resolver = new ContextResolver(),
        private TokenParser $parser = new TokenParser(),
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
                $document = $this->buildDocument($filePath);
            } catch (\Throwable $throwable) {
                $errors[] = $filePath . ': ' . $throwable->getMessage();
                continue;
            }

            if ($document === null) {
                $skippedFileCount++;
                continue;
            }

            $relativePath = $this->toRelativePath($filePath);
            $writtenFiles[] = $writer->writeMarkdown(
                area: 'php',
                slug: str_replace('\\', '_', $document['fqcn']),
                meta: [
                    'id' => 'PHP:' . $document['fqcn'],
                    'title' => $document['fqcn'],
                    'source_path' => $relativePath,
                    'kind' => $document['kind'],
                    'rule_ids' => $document['rule_ids'],
                ],
                body: $document['body'],
            );

            $documents[] = [
                'fqcn' => $document['fqcn'],
                'kind' => $document['kind'],
                'source_path' => $relativePath,
                'file_name' => 'php/' . str_replace('\\', '_', $document['fqcn']) . '.md',
            ];
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
     * @return array{
     *     fqcn: class-string,
     *     kind: string,
     *     rule_ids: list<string>,
     *     owners: list<string>,
     *     refs: list<string>,
     *     verified_by: list<string>,
     *     annotated_methods: list<string>,
     *     body: string
     * }|null
     */
    private function buildDocument(string $filePath): ?array
    {
        $symbol = $this->parser->getFirstSymbolFromFile($filePath);
        if ($symbol === null) {
            return null;
        }

        $exists = match ($symbol->kind) {
            'class', 'enum' => class_exists($symbol->fqcn),
            'interface' => interface_exists($symbol->fqcn),
            'trait' => trait_exists($symbol->fqcn),
            default => false,
        };

        if (!$exists) {
            throw new RuntimeException("Symbol not autoloadable: {$symbol->fqcn}");
        }

        $reflection = new ReflectionClass($symbol->fqcn);
        $metadata = $this->collectMetadata($reflection);
        if ($metadata['rule_ids'] === []) {
            return null;
        }

        return [
            'fqcn' => $symbol->fqcn,
            'kind' => $symbol->kind,
            'rule_ids' => $metadata['rule_ids'],
            'owners' => $metadata['owners'],
            'refs' => $metadata['refs'],
            'verified_by' => $metadata['verified_by'],
            'annotated_methods' => $metadata['annotated_methods'],
            'body' => $this->summarizer->summarize($filePath),
        ];
    }

    /**
     * @param ReflectionClass<object> $reflection
     * @return array{
     *     rule_ids: list<string>,
     *     owners: list<string>,
     *     refs: list<string>,
     *     verified_by: list<string>,
     *     annotated_methods: list<string>
     * }
     */
    private function collectMetadata(ReflectionClass $reflection): array
    {
        $ruleIds = [];
        $owners = [];
        $refs = [];
        $verifiedBy = [];
        $annotatedMethods = [];

        foreach ($reflection->getAttributes(Rule::class) as $attribute) {
            $this->appendDefinitionMetadata($attribute, $ruleIds, $owners, $refs, $verifiedBy);
        }

        foreach ($reflection->getMethods() as $method) {
            if ($method->getDeclaringClass()->getName() !== $reflection->getName()) {
                continue;
            }

            $attributes = $method->getAttributes(Rule::class);
            if ($attributes === []) {
                continue;
            }

            $annotatedMethods[] = $method->getName();

            foreach ($attributes as $attribute) {
                $this->appendDefinitionMetadata($attribute, $ruleIds, $owners, $refs, $verifiedBy);
            }
        }

        sort($annotatedMethods);

        return [
            'rule_ids' => $this->uniqueSorted($ruleIds),
            'owners' => $this->uniqueSorted($owners),
            'refs' => $this->uniqueSorted($refs),
            'verified_by' => $this->uniqueSorted($verifiedBy),
            'annotated_methods' => $annotatedMethods,
        ];
    }

    /**
     * @param ReflectionAttribute<Rule> $attribute
     * @param list<string> $ruleIds
     * @param list<string> $owners
     * @param list<string> $refs
     * @param list<string> $verifiedBy
     */
    private function appendDefinitionMetadata(
        ReflectionAttribute $attribute,
        array &$ruleIds,
        array &$owners,
        array &$refs,
        array &$verifiedBy,
    ): void {
        $instance = $attribute->newInstance();
        $id = $instance->id;
        $ruleIds[] = $id::class . "::{$id->name}";

        $definition = $this->resolver->resolve($id);
        if ($definition->owner !== null && trim($definition->owner) !== '') {
            $owners[] = $definition->owner;
        }

        foreach ($definition->refs as $ref) {
            if ($ref !== '') {
                $refs[] = $ref;
            }
        }

        foreach ($definition->verifiedBy as $proof) {
            $verifiedBy[] = $proof;
        }
    }

    /**
     * @param list<array{fqcn:string,kind:string,source_path:string,file_name:string}> $documents
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
            $lines[] = '- [' . $document['fqcn'] . '](' . $document['file_name'] . ')'
                . ' (' . $document['kind'] . ')'
                . ' - `' . $document['source_path'] . '`';
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

    /**
     * @param list<string> $values
     * @return list<string>
     */
    private function uniqueSorted(array $values): array
    {
        $values = array_values(array_unique(array_filter($values, static fn (string $value): bool => $value !== '')));
        sort($values);

        return $values;
    }

    private function toRelativePath(string $path): string
    {
        $cwd = getcwd();
        if (!is_string($cwd)) {
            return $path;
        }

        $prefix = rtrim($cwd, '/') . '/';

        if (!str_starts_with($path, $prefix)) {
            return $path;
        }

        return substr($path, strlen($prefix));
    }
}
