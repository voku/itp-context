<?php

declare(strict_types=1);

namespace ItpContext\Tests;

use ItpContext\Context\PackageRules;
use ItpContext\Contract\ContextDocumentReader;
use ItpContext\Model\ExportReport;
use ItpContext\Service\ContextExporter;
use ItpContext\Service\Frontmatter;
use PHPUnit\Framework\TestCase;

final class ContextExporterTest extends TestCase
{
    private string $exportPath;

    protected function setUp(): void
    {
        $this->exportPath = sys_get_temp_dir() . '/' . uniqid('itp-context-export-test-');
        $this->removeGeneratedExampleContextFiles();
        $this->removeDirectory($this->exportPath);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->exportPath);
        $this->removeDirectory($this->exportPath . '-result');
        $this->removeGeneratedExampleContextFiles();
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
        self::assertStringContainsString('owners:', $symbolContent);
        self::assertStringContainsString('annotated_methods:', $symbolContent);
        self::assertStringContainsString('rule_count: 2', $symbolContent);
        self::assertStringContainsString('ArchitectureRules::ViewAbstraction', $symbolContent);
        self::assertStringContainsString('ArchitectureRules::I18n', $symbolContent);
        self::assertStringContainsString('**Why:** A dedicated view layer keeps rendering concerns isolated from domain and controller code.', $symbolContent);
        self::assertStringContainsString('**Proof:** ItpContextExample\\Tests\\I18nTest', $symbolContent);
        self::assertStringContainsString('**Refs:** docs/adr/view-abstraction.md, docs/ui/rendering.md', $symbolContent);
        self::assertStringContainsString('**Refs:** docs/adr/i18n.md', $symbolContent);
        self::assertStringContainsString('# Context: DashboardView', $symbolContent);

        $indexContent = (string) file_get_contents($this->exportPath . '/index.md');
        self::assertStringContainsString('[ItpContextExample\\DashboardView](php/ItpContextExample_DashboardView.md)', $indexContent);
        self::assertStringContainsString('owners: Team-Architecture', $indexContent);
        self::assertStringContainsString('annotated methods: `render`', $indexContent);
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
        self::assertSame(7, $report->exportedDocumentCount);
        self::assertSame(
            'src/Service/ContextExporter.php',
            Frontmatter::parse(
                (string) file_get_contents($this->exportPath . '/php/ItpContext_Service_ContextExporter.md')
            )['source_path']
        );
        self::assertSame(
            ['ItpContext\\Context\\PackageRules::AgentFriendlyMarkdown', 'ItpContext\\Context\\PackageRules::DiscoveryMetadata'],
            Frontmatter::parse(
                (string) file_get_contents($this->exportPath . '/php/ItpContext_Service_ContextExporter.md')
            )['rule_ids']
        );
        self::assertSame(
            ['Team-ItpContext'],
            Frontmatter::parse(
                (string) file_get_contents($this->exportPath . '/php/ItpContext_Service_ContextExporter.md')
            )['owners']
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

    public function testExportRequiresAtLeastOneSourceDirectory(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('At least one source directory is required.');

        (new ContextExporter())->export($this->exportPath, []);
    }

    public function testExportRejectsSourceDirectoriesWithoutPhpFiles(): void
    {
        mkdir($this->exportPath . '/empty', 0777, true);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No PHP files found in the given source directories.');

        (new ContextExporter())->export($this->exportPath, [$this->exportPath . '/empty']);
    }

    public function testExportReportsBrokenFilesAndContinues(): void
    {
        $brokenSourceDir = $this->exportPath . '/broken-source';
        mkdir($brokenSourceDir, 0777, true);
        $brokenFile = $brokenSourceDir . '/Broken.php';
        file_put_contents($brokenFile, "<?php\nnamespace Broken;\nfinal class Broken {\n");

        $reader = new class ($brokenFile) implements ContextDocumentReader
        {
            private \ItpContext\Service\ContextReader $reader;

            public function __construct(private string $brokenFile)
            {
                $this->reader = new \ItpContext\Service\ContextReader();
            }

            public function read(string $filePath): array
            {
                if ($filePath === $this->brokenFile) {
                    throw new \RuntimeException('Fixture failure.');
                }

                return $this->reader->read($filePath);
            }
        };

        $report = (new ContextExporter($reader))->export(
            $this->exportPath . '-result',
            [$brokenSourceDir, dirname(__DIR__) . '/src']
        );

        self::assertSame([$brokenFile . ': Fixture failure.'], $report->errors);
        self::assertGreaterThan(0, $report->exportedDocumentCount);
    }

    public function testExportCreatesEmptyIndexWhenNoAnnotatedSymbolsExist(): void
    {
        (new ContextExporter())->export(
            $this->exportPath,
            [dirname(__DIR__) . '/examples/basic-domain/src/Tests']
        );

        $indexContent = (string) file_get_contents($this->exportPath . '/index.md');

        self::assertStringContainsString('No annotated PHP symbols were found.', $indexContent);
    }

    public function testExportIgnoresMissingDirectoriesWhenOtherSourcesExist(): void
    {
        $report = (new ContextExporter())->export(
            $this->exportPath,
            ['/definitely/missing/path', dirname(__DIR__) . '/src']
        );

        self::assertSame([], $report->errors);
        self::assertGreaterThan(0, $report->exportedDocumentCount);
    }

    public function testExportCreatesSeparateDocumentsForAnnotatedFunctions(): void
    {
        $sourceDir = $this->exportPath . '/functions';
        mkdir($sourceDir, 0777, true);
        file_put_contents($sourceDir . '/Functions.php', <<<'PHP'
<?php

declare(strict_types=1);

namespace ItpContext\Tests\Fixtures;

use ItpContext\Attribute\Rule;
use ItpContextExample\Context\ArchitectureRules;

#[Rule(ArchitectureRules::ViewAbstraction)]
function helper(): string
{
    return 'ok';
}
PHP);

        $report = (new ContextExporter())->export($this->exportPath . '-result', [$sourceDir]);

        self::assertSame(1, $report->exportedDocumentCount);
        self::assertFileExists($this->exportPath . '-result/php/function_ItpContext_Tests_Fixtures_helper.md');
        self::assertStringContainsString(
            '# Context: function `ItpContext\Tests\Fixtures\helper()`',
            (string) file_get_contents($this->exportPath . '-result/php/function_ItpContext_Tests_Fixtures_helper.md')
        );
    }

    public function testFindPhpFilesSkipsNonPhpEntries(): void
    {
        $sourceDir = $this->exportPath . '/mixed';
        mkdir($sourceDir . '/nested', 0777, true);
        file_put_contents($sourceDir . '/README.txt', 'ignore');
        file_put_contents($sourceDir . '/nested/Example.php', "<?php\nfinal class MixedExample {}\n");

        $method = new \ReflectionMethod(ContextExporter::class, 'findPhpFiles');
        $method->setAccessible(true);

        /** @var list<string> $files */
        $files = $method->invoke(new ContextExporter(), [$sourceDir], []);

        self::assertSame([$sourceDir . '/nested/Example.php'], $files);
    }

    public function testReaderToRelativePathReturnsOriginalPathOutsideProjectRoot(): void
    {
        $method = new \ReflectionMethod(\ItpContext\Service\ContextReader::class, 'toRelativePath');
        $method->setAccessible(true);

        self::assertSame(
            '/tmp/outside-project.php',
            $method->invoke(new \ItpContext\Service\ContextReader(), '/tmp/outside-project.php')
        );
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
            @chmod($item->getPathname(), 0777);

            if ($item->isDir()) {
                rmdir($item->getPathname());
                continue;
            }

            unlink($item->getPathname());
        }

        @chmod($path, 0777);
        rmdir($path);
    }

    private function removeGeneratedExampleContextFiles(): void
    {
        foreach (['ExampleRules.php', 'ExampleCatalog.php'] as $file) {
            $path = dirname(__DIR__) . '/src/Context/' . $file;
            if (is_file($path)) {
                unlink($path);
            }
        }
    }
}
