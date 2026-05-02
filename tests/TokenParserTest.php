<?php

declare(strict_types=1);

namespace ItpContext\Tests;

use ItpContext\Service\TokenParser;
use PHPUnit\Framework\TestCase;

final class TokenParserTest extends TestCase
{
    private string $tmpPath;

    protected function setUp(): void
    {
        $this->tmpPath = sys_get_temp_dir() . '/itp-context-token-parser-test-' . str_replace('.', '', uniqid('', true));
        mkdir($this->tmpPath, 0777, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->tmpPath . '/*.php') ?: [] as $file) {
            unlink($file);
        }

        if (is_dir($this->tmpPath)) {
            rmdir($this->tmpPath);
        }
    }

    public function testGetFirstSymbolFromFileSkipsClassConstantReferences(): void
    {
        $symbol = (new TokenParser())->getFirstSymbolFromFile(
            dirname(__DIR__) . '/examples/basic-domain/src/Context/ArchitectureCatalog.php'
        );

        self::assertNull($symbol);
    }

    public function testGetFirstSymbolFromFileReturnsNullForMissingFiles(): void
    {
        self::assertNull((new TokenParser())->getFirstSymbolFromFile($this->tmpPath . '/missing.php'));
    }

    public function testGetFirstSymbolFromFileParsesMultiSegmentNamespaces(): void
    {
        $path = $this->writePhpFile('namespace Foo\\Bar\\Baz; final class Example {}');

        $symbol = (new TokenParser())->getFirstSymbolFromFile($path);

        self::assertNotNull($symbol);
        self::assertSame('Foo\\Bar\\Baz\\Example', $symbol->fqcn);
    }

    public function testGetFirstSymbolFromFileSkipsAnonymousClasses(): void
    {
        $path = $this->writePhpFile('$value = new class() {}; final class NamedExample {}');

        $symbol = (new TokenParser())->getFirstSymbolFromFile($path);

        self::assertNotNull($symbol);
        self::assertSame('NamedExample', $symbol->fqcn);
    }

    public function testGetFirstSymbolFromFileHandlesBracketedNamespaces(): void
    {
        $path = $this->writePhpFile('namespace Bracketed\\Example { final class Demo {} }');

        $symbol = (new TokenParser())->getFirstSymbolFromFile($path);

        self::assertNotNull($symbol);
        self::assertSame('Bracketed\\Example\\Demo', $symbol->fqcn);
    }

    public function testParseNamespaceHandlesMixedTokenStreams(): void
    {
        $method = new \ReflectionMethod(TokenParser::class, 'parseNamespace');
        $method->setAccessible(true);

        $result = $method->invoke(
            new TokenParser(),
            [
                [T_NAMESPACE, 'namespace'],
                ' ',
                [T_STRING, 'Foo'],
                [T_NS_SEPARATOR, '\\'],
                [T_STRING, 'Bar'],
                ';',
            ],
            0
        );

        self::assertSame(['Foo\\Bar', 5], $result);
    }

    public function testParseNamespaceFallsBackWhenNoTerminatorExists(): void
    {
        $method = new \ReflectionMethod(TokenParser::class, 'parseNamespace');
        $method->setAccessible(true);

        $result = $method->invoke(
            new TokenParser(),
            [
                [T_NAMESPACE, 'namespace'],
                [T_STRING, 'Foo'],
            ],
            0
        );

        self::assertSame(['Foo', 0], $result);
    }

    public function testAnonymousClassDetectionHandlesParentheses(): void
    {
        $method = new \ReflectionMethod(TokenParser::class, 'isAnonymousClass');
        $method->setAccessible(true);

        self::assertTrue($method->invoke(
            new TokenParser(),
            ['(', ')', [T_WHITESPACE, ' '], [T_NEW, 'new'], [T_CLASS, 'class']],
            4
        ));
    }

    public function testClassConstantDetectionHandlesNonArrayTokens(): void
    {
        $method = new \ReflectionMethod(TokenParser::class, 'isClassConstantReference');
        $method->setAccessible(true);

        self::assertTrue($method->invoke(
            new TokenParser(),
            ['(', ')', [T_WHITESPACE, ' '], [T_DOUBLE_COLON, '::'], [T_CLASS, 'class']],
            4
        ));
    }

    private function writePhpFile(string $code): string
    {
        $path = $this->tmpPath . '/' . str_replace('.', '', uniqid('', true)) . '.php';
        file_put_contents($path, "<?php\n" . $code . "\n");

        return $path;
    }
}
