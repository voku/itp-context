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

    public function testHandleCreatesEnumAndCatalogFiles(): void
    {
        $this->expectOutputRegex('/Created rule: Smoke\\\\Context\\\\ExampleRules::SecurityBoundary/');

        (new Generator())->handle('Example', 'SecurityBoundary', $this->generatedPath, 'Smoke\\Context');

        self::assertFileExists($this->generatedPath . '/ExampleRules.php');
        self::assertFileExists($this->generatedPath . '/ExampleCatalog.php');
        self::assertStringContainsString('case SecurityBoundary;', (string) file_get_contents($this->generatedPath . '/ExampleRules.php'));
        self::assertStringContainsString("'SecurityBoundary' => new RuleDef(", (string) file_get_contents($this->generatedPath . '/ExampleCatalog.php'));
        self::assertStringContainsString("rationale: 'TODO: Explain why this rule exists.'", (string) file_get_contents($this->generatedPath . '/ExampleCatalog.php'));
        self::assertStringContainsString("verifiedBy: ['tests/Architecture/SecurityBoundaryTest.php']", (string) file_get_contents($this->generatedPath . '/ExampleCatalog.php'));
        self::assertStringContainsString("refs: ['docs/adr/security-boundary.md']", (string) file_get_contents($this->generatedPath . '/ExampleCatalog.php'));
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
        $catalogContent = (string) file_get_contents($this->generatedPath . '/ExampleCatalog.php');

        self::assertSame(1, substr_count($enumContent, 'case SecurityBoundary;'));
        self::assertSame(1, substr_count($catalogContent, "'SecurityBoundary' => new RuleDef("));
    }

    public function testHandlePreservesExistingFiles(): void
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
        file_put_contents($this->generatedPath . '/ExampleCatalog.php', <<<PHP
<?php

declare(strict_types=1);

namespace Smoke\Context;

use ItpContext\Enum\Tier;
use ItpContext\Model\RuleDef;

return [
    'ExistingRule' => new RuleDef(
        statement: 'Existing rule.',
        tier: Tier::Standard,
        owner: 'Team-Example',
    ),
];
PHP);

        (new Generator())->handle('Example', 'SecurityBoundary', $this->generatedPath, 'Smoke\\Context');

        self::assertStringContainsString('case ExistingRule;', (string) file_get_contents($this->generatedPath . '/ExampleRules.php'));
        self::assertStringContainsString("'ExistingRule' => new RuleDef(", (string) file_get_contents($this->generatedPath . '/ExampleCatalog.php'));
    }

    public function testHandleRejectsMalformedCatalogFiles(): void
    {
        mkdir($this->generatedPath, 0777, true);
        file_put_contents($this->generatedPath . '/ExampleRules.php', <<<PHP
<?php

declare(strict_types=1);

namespace Smoke\Context;

use ItpContext\Contract\RuleIdentifier;

enum ExampleRules implements RuleIdentifier
{
}
PHP);
        file_put_contents($this->generatedPath . '/ExampleCatalog.php', "<?php\nreturn [\n");

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Malformed catalog (missing '];')");

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

    public function testHandleRejectsUnreadableCatalogFiles(): void
    {
        mkdir($this->generatedPath, 0777, true);
        file_put_contents($this->generatedPath . '/ExampleRules.php', <<<PHP
<?php

declare(strict_types=1);

namespace Smoke\Context;

use ItpContext\Contract\RuleIdentifier;

enum ExampleRules implements RuleIdentifier
{
}
PHP);
        mkdir($this->generatedPath . '/ExampleCatalog.php', 0777, true);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Failed to read');

        (new Generator())->handle('Example', 'SecurityBoundary', $this->generatedPath, 'Smoke\\Context');
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
