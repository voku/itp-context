<?php

declare(strict_types=1);

namespace ItpContext\Service;

use ItpContext\Attribute\Rule;
use ItpContext\Context\PackageRules;
use ItpContext\Model\ContextQueryResult;

#[Rule(PackageRules::DiscoveryMetadata)]
final class ContextQuery
{
    /**
     * @param array{
     *     rule_id?: string,
     *     owner?: string,
     *     ref?: string,
     *     verified_by?: string,
     *     source_path?: string,
     *     kind?: string,
     *     text?: string
     * } $filters
     * @return list<ContextQueryResult>
     */
    public function search(string $exportDir, array $filters = []): array
    {
        if ($exportDir === '' || !is_dir($exportDir)) {
            throw new \RuntimeException("Export directory not found: {$exportDir}");
        }

        $matches = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($exportDir, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $item) {
            if (!$item->isFile() || $item->getExtension() !== 'md' || $item->getFilename() === 'index.md') {
                continue;
            }

            $path = $item->getPathname();
            $parsed = Frontmatter::parse((string) file_get_contents($path));
            if (!$this->isDocumentMatch($parsed, $filters)) {
                continue;
            }

            $matches[] = new ContextQueryResult(
                path: $path,
                id: (string) ($parsed['id'] ?? ''),
                title: (string) ($parsed['title'] ?? ''),
                kind: (string) ($parsed['kind'] ?? ''),
                sourcePath: (string) ($parsed['source_path'] ?? ''),
                ruleIds: $this->normalizeList($parsed['rule_ids'] ?? []),
                owners: $this->normalizeList($parsed['owners'] ?? []),
                refs: $this->normalizeList($parsed['refs'] ?? []),
                verifiedBy: $this->normalizeList($parsed['verified_by'] ?? []),
                annotatedMethods: $this->normalizeList($parsed['annotated_methods'] ?? []),
                body: (string) ($parsed['body'] ?? ''),
            );
        }

        usort(
            $matches,
            static fn (ContextQueryResult $left, ContextQueryResult $right): int => strcmp($left->path, $right->path)
        );

        return $matches;
    }

    /**
     * @param array<string, mixed> $document
     * @param array<string, string> $filters
     */
    private function isDocumentMatch(array $document, array $filters): bool
    {
        foreach ($filters as $key => $value) {
            if (trim($value) === '') {
                continue;
            }

            $value = trim($value);

            if ($key === 'rule_id' && !$this->containsValue($document['rule_ids'] ?? [], $value)) {
                return false;
            }

            if ($key === 'owner' && !$this->containsValue($document['owners'] ?? [], $value)) {
                return false;
            }

            if ($key === 'ref' && !$this->containsValue($document['refs'] ?? [], $value)) {
                return false;
            }

            if ($key === 'verified_by' && !$this->containsValue($document['verified_by'] ?? [], $value)) {
                return false;
            }

            if ($key === 'source_path' && (string) ($document['source_path'] ?? '') !== $value) {
                return false;
            }

            if ($key === 'kind' && (string) ($document['kind'] ?? '') !== $value) {
                return false;
            }

            if ($key === 'text' && !$this->containsText($document, $value)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param mixed $values
     */
    private function containsValue(mixed $values, string $expected): bool
    {
        foreach ($this->normalizeList($values) as $value) {
            if ($value === $expected) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $document
     */
    private function containsText(array $document, string $needle): bool
    {
        $haystacks = array_merge(
            [
                (string) ($document['id'] ?? ''),
                (string) ($document['title'] ?? ''),
                (string) ($document['kind'] ?? ''),
                (string) ($document['source_path'] ?? ''),
                (string) ($document['body'] ?? ''),
            ],
            $this->normalizeList($document['rule_ids'] ?? []),
            $this->normalizeList($document['owners'] ?? []),
            $this->normalizeList($document['refs'] ?? []),
            $this->normalizeList($document['verified_by'] ?? []),
            $this->normalizeList($document['annotated_methods'] ?? []),
        );

        foreach ($haystacks as $haystack) {
            if (mb_stripos($haystack, $needle) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param mixed $values
     * @return list<string>
     */
    private function normalizeList(mixed $values): array
    {
        if (!is_array($values)) {
            return [];
        }

        $items = array_values(array_filter(
            $values,
            static fn (mixed $value): bool => is_scalar($value) && trim((string) $value) !== ''
        ));

        return array_map(static fn (string|int|float|bool $value): string => (string) $value, $items);
    }
}
