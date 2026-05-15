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
        $this->generatedPath = sys_get_temp_dir() . '/' . uniqid('itp-context-generator-test-');
        $this->removeDirectory($this->generatedPath);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->generatedPath);
    }

    public function testHandleCreatesEnumFileWithInlineDefinition(): void
    {
        $this->expectOutputRegex('/Created rule: Smoke\\\\Context\\\\ExampleRules::SecurityBoundary/');

        (new Generator())->handle('Example', 'SecurityBoundary', $this->generatedPath, 'Smoke\\Context');

        self::assertFileExists($this->generatedPath . '/ExampleRules.php');

        $content = (string) file_get_contents($this->generatedPath . '/ExampleRules.php');
        self::assertStringContainsString('case SecurityBoundary;', $content);
        self::assertStringContainsString('public function getDefinition(): RuleDef', $content);
        self::assertStringContainsString('self::SecurityBoundary => new RuleDef(', $content);
        self::assertStringContainsString("rationale: 'TODO: Explain why this rule exists.'", $content);
        self::assertStringContainsString("verifiedBy: ['tests/Architecture/SecurityBoundaryTest.php']", $content);
        self::assertStringContainsString("refs: ['docs/adr/security-boundary.md']", $content);
    }

    public function testHandleRejectsMissingArguments(): void
    {
        foreach ([[null, 'Rule'], ['', 'Rule'], ['Example', null], ['Example', '']] as [$domain, $ruleName]) {
            try {
                (new Generator())->handle($domain, $ruleName, $this->generatedPath, 'Smoke\\Context');
                self::fail('Expected RuntimeException was not thrown.');
            } catch (\RuntimeException $exception) {
                self::assertStringContainsString('Missing arguments.', $exception->getMessage());
            }
        }
    }

    public function testHandleIsIdempotentForExistingRules(): void
    {
        $this->expectOutputRegex('/Created rule: Smoke\\\\Context\\\\ExampleRules::SecurityBoundary/');

        (new Generator())->handle('Example', 'SecurityBoundary', $this->generatedPath, 'Smoke\\Context');
        (new Generator())->handle('Example', 'SecurityBoundary', $this->generatedPath, 'Smoke\\Context');

        $enumContent = (string) file_get_contents($this->generatedPath . '/ExampleRules.php');

        self::assertSame(1, substr_count($enumContent, 'case SecurityBoundary;'));
        self::assertSame(1, substr_count($enumContent, 'self::SecurityBoundary => new RuleDef('));
    }

    public function testHandlePreservesExistingEnumFiles(): void
    {
        $this->expectOutputRegex('/Created rule: Smoke\\\\Context\\\\ExampleRules::SecurityBoundary/');

        mkdir($this->generatedPath, 0777, true);
        file_put_contents($this->generatedPath . '/ExampleRules.php', <<<PHP
<?php

declare(strict_types=1);

namespace Smoke\Context;

use ItpContext\Contract\RuleIdentifier;

enum ExampleRules implements RuleIdentifier
{
    case ExistingRule;
}
PHP);

        (new Generator())->handle('Example', 'SecurityBoundary', $this->generatedPath, 'Smoke\\Context');

        $content = (string) file_get_contents($this->generatedPath . '/ExampleRules.php');
        self::assertStringContainsString('case ExistingRule;', $content);
        self::assertStringContainsString('case SecurityBoundary;', $content);
        self::assertStringContainsString('self::SecurityBoundary => new RuleDef(', $content);
    }

    public function testHandleAppendsDefinitionsForAdditionalRules(): void
    {
        $this->expectOutputRegex('/Created rule: Smoke\\\\Context\\\\ExampleRules::SecurityBoundary/');

        mkdir($this->generatedPath, 0777, true);
        file_put_contents($this->generatedPath . '/ExampleRules.php', <<<PHP
<?php

declare(strict_types=1);

namespace Smoke\Context;

use ItpContext\Contract\RuleIdentifier;
use ItpContext\Enum\Tier;
use ItpContext\Model\RuleDef;

enum ExampleRules implements RuleIdentifier
{
    case ExistingRule;

    public function getDefinition(): RuleDef
    {
        return match (\$this) {
            self::ExistingRule => new RuleDef(
                statement: 'Existing rule.',
                tier: Tier::Standard,
                owner: 'Team-Example',
            ),
        };
    }
}
PHP);

        (new Generator())->handle('Example', 'SecurityBoundary', $this->generatedPath, 'Smoke\\Context');

        $content = (string) file_get_contents($this->generatedPath . '/ExampleRules.php');
        self::assertStringContainsString('self::ExistingRule => new RuleDef(', $content);
        self::assertStringContainsString('self::SecurityBoundary => new RuleDef(', $content);
    }

    public function testHandleRejectsMalformedEnumFiles(): void
    {
        mkdir($this->generatedPath, 0777, true);
        file_put_contents($this->generatedPath . '/ExampleRules.php', "<?php\n\nenum ExampleRules implements \\ItpContext\\Contract\\RuleIdentifier\n{\n");

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Malformed enum');

        (new Generator())->handle('Example', 'SecurityBoundary', $this->generatedPath, 'Smoke\\Context');
    }

    public function testHandleRejectsDirectoriesThatCannotBeCreated(): void
    {
        $blockedPath = $this->generatedPath . '/blocked';
        mkdir($this->generatedPath, 0777, true);
        file_put_contents($blockedPath, 'blocker');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Failed to create directory');

        (new Generator())->handle('Example', 'SecurityBoundary', $blockedPath . '/child', 'Smoke\\Context');
    }

    public function testHandleRejectsUnreadableEnumFiles(): void
    {
        mkdir($this->generatedPath . '/ExampleRules.php', 0777, true);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Failed to read');

        (new Generator())->handle('Example', 'SecurityBoundary', $this->generatedPath, 'Smoke\\Context');
    }

    /**
     * @runInSeparateProcess
     */
    public function testHandleRejectsEnumFilesThatCannotBeReadAfterLookup(): void
    {
        mkdir($this->generatedPath, 0777, true);
        \file_put_contents($this->generatedPath . '/ExampleRules.php', <<<PHP
<?php

declare(strict_types=1);

namespace Smoke\Context;

use ItpContext\Contract\RuleIdentifier;

enum ExampleRules implements RuleIdentifier
{
    case ExistingRule;
}
PHP);

        eval(<<<'PHP'
namespace ItpContext\Service;

function file_get_contents(string $path): string|false
{
    if (str_ends_with($path, 'ExampleRules.php')) {
        return false;
    }

    return \file_get_contents($path);
}
PHP);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Failed to read');

        (new Generator())->handle('Example', 'SecurityBoundary', $this->generatedPath, 'Smoke\\Context');
    }

    public function testHandleRejectsMalformedEnumDefinitionMatches(): void
    {
        mkdir($this->generatedPath, 0777, true);
        file_put_contents($this->generatedPath . '/ExampleRules.php', <<<PHP
<?php

declare(strict_types=1);

namespace Smoke\Context;

use ItpContext\Contract\RuleIdentifier;
use ItpContext\Model\RuleDef;

enum ExampleRules implements RuleIdentifier
{
    case ExistingRule;

    public function getDefinition(): RuleDef
    {
        return new RuleDef('Existing rule.');
    }
}
PHP);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Malformed enum definition match');

        (new Generator())->handle('Example', 'SecurityBoundary', $this->generatedPath, 'Smoke\\Context');
    }

    /**
     * @runInSeparateProcess
     */
    public function testHandleRejectsImportUpdatesWhenPregReplaceFails(): void
    {
        mkdir($this->generatedPath, 0777, true);
        \file_put_contents($this->generatedPath . '/ExampleRules.php', <<<PHP
<?php

declare(strict_types=1);

namespace Smoke\Context;

use ItpContext\Contract\RuleIdentifier;

enum ExampleRules implements RuleIdentifier
{
    case ExistingRule;
}
PHP);

        eval(<<<'PHP'
namespace ItpContext\Service;

function preg_replace($pattern, $replacement, $subject, $limit = -1)
{
    if ($pattern === '/^(namespace [^;]+;\n(?:\n?use [^;]+;\n)*)/m') {
        return null;
    }

    return \preg_replace($pattern, $replacement, $subject, $limit);
}
PHP);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Failed to update enum file imports');

        (new Generator())->handle('Example', 'SecurityBoundary', $this->generatedPath, 'Smoke\\Context');
    }

    /**
     * @runInSeparateProcess
     */
    public function testHandleRejectsEnumWritesThatFail(): void
    {
        mkdir($this->generatedPath, 0777, true);

        eval(<<<'PHP'
namespace ItpContext\Service;

function file_put_contents(string $path, string $content): int|false
{
    if (str_ends_with($path, 'ExampleRules.php')) {
        return false;
    }

    return \file_put_contents($path, $content);
}
PHP);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Failed to write');

        (new Generator())->handle('Example', 'SecurityBoundary', $this->generatedPath, 'Smoke\\Context');
    }

    public function testFormatLastErrorMessageReturnsEmptyStringForNonArrayInput(): void
    {
        $method = new \ReflectionMethod(Generator::class, 'formatLastErrorMessage');
        $method->setAccessible(true);

        self::assertSame('', $method->invoke(null, 'nope'));
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
}
