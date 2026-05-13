<?php

declare(strict_types=1);

namespace ItpContext\Tests;

use ItpContext\Service\ContextExporter;
use ItpContext\Service\ContextQuery;
use PHPUnit\Framework\TestCase;

final class ContextQueryTest extends TestCase
{
    private string $exportPath;

    protected function setUp(): void
    {
        $this->exportPath = sys_get_temp_dir() . '/' . uniqid('itp-context-query-test-');
        $this->removeDirectory($this->exportPath);

        (new ContextExporter())->export($this->exportPath, [dirname(__DIR__) . '/src']);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->exportPath);
    }

    public function testSearchFiltersByRuleId(): void
    {
        $results = (new ContextQuery())->search($this->exportPath, [
            'rule_id' => 'ItpContext\\Context\\PackageRules::DiscoveryMetadata',
        ]);

        self::assertCount(2, $results);
        self::assertSame('ItpContext\\Service\\ContextExporter', $results[0]->title);
        self::assertSame('ItpContext\\Service\\ContextQuery', $results[1]->title);
    }

    public function testSearchFiltersByFreeText(): void
    {
        $results = (new ContextQuery())->search($this->exportPath, [
            'text' => 'autoloadable',
        ]);

        self::assertCount(2, $results);
        self::assertSame('ItpContext\\Service\\ContextReader', $results[0]->title);
        self::assertSame('ItpContext\\Service\\Summarizer', $results[1]->title);
    }

    /**
     * @dataProvider mismatchedFilterProvider
     * @param array<string, string> $filters
     */
    public function testSearchRejectsMismatchedFilters(array $filters): void
    {
        $results = (new ContextQuery())->search($this->exportPath, $filters);

        self::assertSame([], $results);
    }

    public function testSearchIgnoresBlankFilters(): void
    {
        $results = (new ContextQuery())->search($this->exportPath, [
            'owner' => '   ',
            'rule_id' => 'ItpContext\\Context\\PackageRules::DiscoveryMetadata',
        ]);

        self::assertCount(2, $results);
    }

    public function testNormalizeListReturnsEmptyArrayForScalarValues(): void
    {
        $method = new \ReflectionMethod(ContextQuery::class, 'normalizeList');
        $method->setAccessible(true);

        self::assertSame([], $method->invoke(new ContextQuery(), 'not-a-list'));
    }

    public function testSearchRejectsMissingExportDirectories(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Export directory not found');

        (new ContextQuery())->search($this->exportPath . '-missing');
    }

    /**
     * @return iterable<string, array{0: array<string, string>}>
     */
    public static function mismatchedFilterProvider(): iterable
    {
        yield 'owner' => [[
            'owner' => 'Team-Does-Not-Exist',
        ]];
        yield 'ref' => [[
            'ref' => 'docs/adr/does-not-exist.md',
        ]];
        yield 'verified_by' => [[
            'verified_by' => 'tests/DoesNotExist.php',
        ]];
        yield 'source_path' => [[
            'source_path' => 'src/Service/DoesNotExist.php',
        ]];
        yield 'kind' => [[
            'kind' => 'function',
            'rule_id' => 'ItpContext\\Context\\PackageRules::DiscoveryMetadata',
        ]];
    }

    private function removeDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $item) {
            if ($item->isDir()) {
                rmdir($item->getPathname());
                continue;
            }

            unlink($item->getPathname());
        }

        rmdir($path);
    }
}
