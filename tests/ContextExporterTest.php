<?php

declare(strict_types=1);

namespace ItpContext\Tests;

use ItpContext\Context\PackageRules;
use ItpContext\Model\ExportReport;
use ItpContext\Service\ContextExporter;
use ItpContext\Service\Frontmatter;
use PHPUnit\Framework\TestCase;

final class ContextExporterTest extends TestCase
{
    private string $exportPath;

    protected function setUp(): void
    {
        $this->exportPath = sys_get_temp_dir() . '/itp-context-export-test-' . md5(static::class);
        $this->removeDirectory($this->exportPath);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->exportPath);
    }

    public function testExportWritesMarkdownDocumentsAndIndex(): void
    {
        $report = (new ContextExporter())->export(
            $this->exportPath,
            [dirname(__DIR__) . '/examples/basic-domain/src'],
        );

        self::assertInstanceOf(ExportReport::class, $report);
        self::assertSame(4, $report->scannedFileCount);
        self::assertSame(1, $report->exportedDocumentCount);
        self::assertSame(3, $report->skippedFileCount);
        self::assertSame([], $report->errors);

        $symbolExport = $this->exportPath . '/php/ItpContextExample_DashboardView.md';
        self::assertFileExists($symbolExport);

        $symbolContent = (string) file_get_contents($symbolExport);
        self::assertStringContainsString('source_path: "examples/basic-domain/src/DashboardView.php"', $symbolContent);
        self::assertStringContainsString('rule_ids:', $symbolContent);
        self::assertStringContainsString('ArchitectureRules::ViewAbstraction', $symbolContent);
        self::assertStringContainsString('ArchitectureRules::I18n', $symbolContent);
        self::assertStringContainsString('# Context: DashboardView', $symbolContent);

        $indexContent = (string) file_get_contents($this->exportPath . '/index.md');
        self::assertStringContainsString('[ItpContextExample\\DashboardView](php/ItpContextExample_DashboardView.md)', $indexContent);
    }

    public function testExportHonorsExcludedPaths(): void
    {
        $report = (new ContextExporter())->export(
            $this->exportPath,
            [dirname(__DIR__) . '/examples/basic-domain/src'],
            ['Context', 'Tests'],
        );

        self::assertSame(1, $report->scannedFileCount);
        self::assertSame(1, $report->exportedDocumentCount);
        self::assertSame(0, $report->skippedFileCount);
        self::assertSame([], $report->errors);
        self::assertFileExists($this->exportPath . '/php/ItpContextExample_DashboardView.md');
    }

    public function testExportWritesAnnotatedPackageSources(): void
    {
        $report = (new ContextExporter())->export(
            $this->exportPath,
            [dirname(__DIR__) . '/src'],
        );

        self::assertSame([], $report->errors);
        self::assertSame(3, $report->exportedDocumentCount);
        self::assertSame(
            'src/Service/ContextExporter.php',
            Frontmatter::parse(
                (string) file_get_contents($this->exportPath . '/php/ItpContext_Service_ContextExporter.md')
            )['source_path']
        );
        self::assertStringContainsString(
            PackageRules::AgentFriendlyMarkdown->name,
            (string) file_get_contents($this->exportPath . '/php/ItpContext_Service_ContextExporter.md')
        );
    }

    public function testExportMatchesCommittedDocsFiles(): void
    {
        (new ContextExporter())->export(
            $this->exportPath,
            [dirname(__DIR__) . '/src'],
        );

        $demoFiles = $this->collectFiles(dirname(__DIR__) . '/docs/package-export');
        $generatedFiles = $this->collectFiles($this->exportPath);

        self::assertSame(array_keys($demoFiles), array_keys($generatedFiles));

        foreach ($demoFiles as $relativePath => $demoPath) {
            self::assertSame(
                (string) file_get_contents($demoPath),
                (string) file_get_contents($generatedFiles[$relativePath]),
                $relativePath
            );
        }
    }

    /**
     * @return array<string, string>
     */
    private function collectFiles(string $basePath): array
    {
        $files = [];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($basePath, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $item) {
            if (!$item->isFile()) {
                continue;
            }

            $relativePath = substr($item->getPathname(), strlen($basePath) + 1);
            $files[$relativePath] = $item->getPathname();
        }

        ksort($files);

        return $files;
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
