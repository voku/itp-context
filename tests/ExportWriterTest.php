<?php

declare(strict_types=1);

namespace ItpContext\Tests;

use ItpContext\Service\ExportWriter;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ExportWriterTest extends TestCase
{
    private string $outputPath;

    protected function setUp(): void
    {
        $this->outputPath = sys_get_temp_dir() . '/' . uniqid('itp-context-export-writer-test-');
    }

    protected function tearDown(): void
    {
        @chmod($this->outputPath . '/readonly/php', 0777);
        @chmod($this->outputPath . '/readonly', 0777);

        if (is_file($this->outputPath . '/readonly/php/custom-slug.md')) {
            unlink($this->outputPath . '/readonly/php/custom-slug.md');
        }

        if (is_file($this->outputPath . '/php/context.md')) {
            unlink($this->outputPath . '/php/context.md');
        }

        if (is_file($this->outputPath . '/php/custom-slug.md')) {
            unlink($this->outputPath . '/php/custom-slug.md');
        }

        if (is_dir($this->outputPath . '/php')) {
            rmdir($this->outputPath . '/php');
        }

        if (is_dir($this->outputPath . '/readonly/php')) {
            rmdir($this->outputPath . '/readonly/php');
        }

        if (is_dir($this->outputPath . '/readonly')) {
            rmdir($this->outputPath . '/readonly');
        }

        if (is_file($this->outputPath)) {
            unlink($this->outputPath);
        }

        if (is_dir($this->outputPath)) {
            rmdir($this->outputPath);
        }
    }

    public function testConstructorRejectsEmptyOutputDirectory(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Output directory cannot be empty.');

        new ExportWriter('');
    }

    public function testWriteMarkdownUsesFallbackSlugForEmptyValues(): void
    {
        $path = (new ExportWriter($this->outputPath))->writeMarkdown('php', '', ['title' => 'Context'], 'Body');

        self::assertSame($this->outputPath . '/php/context.md', $path);
        self::assertFileExists($path);
    }

    public function testWriteMarkdownReturnsWrittenPathForRegularSlug(): void
    {
        $path = (new ExportWriter($this->outputPath))->writeMarkdown('php', 'custom-slug', ['title' => 'Context'], 'Body');

        self::assertSame($this->outputPath . '/php/custom-slug.md', $path);
        self::assertFileExists($path);
    }

    public function testWriteMarkdownThrowsWhenNestedDirectoryCannotBeCreated(): void
    {
        file_put_contents($this->outputPath, 'blocker');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('was not created');

        (new ExportWriter($this->outputPath))->writeMarkdown('php', 'custom-slug', ['title' => 'Context'], 'Body');
    }

    public function testWriteMarkdownThrowsClearErrorWhenOutputPathExistsAsFile(): void
    {
        file_put_contents($this->outputPath, 'blocker');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('exists but is not a directory');

        (new ExportWriter($this->outputPath))->writeMarkdown('', 'custom-slug', ['title' => 'Context'], 'Body');
    }

    public function testWriteMarkdownThrowsWhenFileCannotBeWritten(): void
    {
        mkdir($this->outputPath . '/readonly/php', 0777, true);
        chmod($this->outputPath . '/readonly/php', 0555);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to write export file');

        (new ExportWriter($this->outputPath . '/readonly'))->writeMarkdown('php', 'custom-slug', ['title' => 'Context'], 'Body');
    }

    public function testFormatLastErrorMessageReturnsEmptyStringForNonArrays(): void
    {
        $method = new \ReflectionMethod(ExportWriter::class, 'formatLastErrorMessage');
        $method->setAccessible(true);

        self::assertSame('', $method->invoke(null, 'not-an-array'));
    }
}
