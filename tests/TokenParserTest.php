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
        $this->tmpPath = sys_get_temp_dir() . '/' . uniqid('itp-context-token-parser-test-');
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
        $path = $this->writePhpFile(<<<'PHP'
return [
    \ItpContext\Tests\TokenParserTest::class,
    \ItpContext\Tests\ContextResolverTest::class,
];
PHP);

        $symbol = (new TokenParser())->getFirstSymbolFromFile($path);

        self::assertNull($symbol);
    }

    public function testGetFirstSymbolFromFileReturnsNullForMissingFiles(): void
    {
        self::assertNull((new TokenParser())->getFirstSymbolFromFile($this->tmpPath . '/missing.php'));
    }

    public function testPublicAccessorsReturnEmptyListsForUnreadableFiles(): void
    {
        $path = $this->tmpPath . '/missing.php';

        self::assertSame([], (new TokenParser())->getSymbolsFromFile($path));
        self::assertSame([], (new TokenParser())->getRuleTargetsFromFile($path));
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

    public function testGetSymbolsFromFileIncludesFunctionsAndMultipleDeclarations(): void
    {
        $path = $this->writePhpFile(<<<'PHP'
namespace Multi\Example;

function helper(): string
{
    return 'ok';
}

final class Demo
{
}
PHP);

        $symbols = (new TokenParser())->getSymbolsFromFile($path);

        self::assertCount(2, $symbols);
        self::assertSame('Multi\\Example\\helper', $symbols[0]->fqcn);
        self::assertSame('function', $symbols[0]->kind);
        self::assertSame('Multi\\Example\\Demo', $symbols[1]->fqcn);
        self::assertSame('class', $symbols[1]->kind);
    }

    public function testGetRuleTargetsFromFileParsesClassMethodAndFunctionRules(): void
    {
        $path = $this->writePhpFile(<<<'PHP'
namespace Multi\Example;

use ItpContext\Attribute\Rule;
use ItpContextExample\Context\ArchitectureRules;

#[Rule(ArchitectureRules::ViewAbstraction)]
function helper(): string
{
    return 'ok';
}

#[Rule(ArchitectureRules::ViewAbstraction)]
final class Demo
{
    #[Rule(ArchitectureRules::I18n)]
    public function render(): string
    {
        return 'ok';
    }
}
PHP);

        $targets = (new TokenParser())->getRuleTargetsFromFile($path);

        self::assertCount(3, $targets);
        self::assertSame('function', $targets[0]->kind);
        self::assertSame(['ItpContextExample\\Context\\ArchitectureRules::ViewAbstraction'], $targets[0]->ruleIds);
        self::assertSame('class', $targets[1]->kind);
        self::assertSame('Multi\\Example\\Demo', $targets[1]->fqcn);
        self::assertSame('method', $targets[2]->kind);
        self::assertSame('Multi\\Example\\Demo::render', $targets[2]->fqcn);
        self::assertSame(['ItpContextExample\\Context\\ArchitectureRules::I18n'], $targets[2]->ruleIds);
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
        $path = $this->tmpPath . '/' . uniqid('fixture-') . '.php';
        file_put_contents($path, "<?php\n" . $code . "\n");

        return $path;
    }
}
