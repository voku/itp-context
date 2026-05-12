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

    public function testSearchRejectsMissingExportDirectories(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Export directory not found');

        (new ContextQuery())->search($this->exportPath . '-missing');
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
