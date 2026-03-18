<?php

declare(strict_types=1);

namespace ItpContext\Tests;

use ItpContext\Service\Generator;
use PHPUnit\Framework\TestCase;

final class GeneratorTest extends TestCase
{
    private string $generatedPath;

    protected function setUp(): void
    {
        $this->generatedPath = sys_get_temp_dir() . '/itp-context-generator-test-' . md5(static::class);
        $this->removeDirectory($this->generatedPath);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->generatedPath);
    }

    public function testHandleCreatesEnumAndCatalogFiles(): void
    {
        $this->expectOutputRegex('/Created rule: Smoke\\\\Context\\\\ExampleRules::SecurityBoundary/');

        (new Generator())->handle('Example', 'SecurityBoundary', $this->generatedPath, 'Smoke\\Context');

        self::assertFileExists($this->generatedPath . '/ExampleRules.php');
        self::assertFileExists($this->generatedPath . '/ExampleCatalog.php');
        self::assertStringContainsString('case SecurityBoundary;', (string) file_get_contents($this->generatedPath . '/ExampleRules.php'));
        self::assertStringContainsString("'SecurityBoundary' => new RuleDef(", (string) file_get_contents($this->generatedPath . '/ExampleCatalog.php'));
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
