<?php

declare(strict_types=1);

namespace ItpContext\Tests;

use ItpContext\Context\PackageRules;
use ItpContext\Model\RuleDef;
use ItpContext\Service\ContextResolver;
use ItpContextExample\Context\ArchitectureRules;
use PHPUnit\Framework\TestCase;

final class ContextResolverTest extends TestCase
{
    private string $fixturePath;

    protected function setUp(): void
    {
        $this->fixturePath = sys_get_temp_dir() . '/' . uniqid('itp-context-resolver-test-');
        mkdir($this->fixturePath, 0777, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->fixturePath . '/*.php') ?: [] as $file) {
            unlink($file);
        }

        if (is_dir($this->fixturePath)) {
            rmdir($this->fixturePath);
        }
    }

    public function testResolveReturnsRuleDefinitionFromEnum(): void
    {
        $definition = (new ContextResolver())->resolve(ArchitectureRules::ViewAbstraction);

        self::assertInstanceOf(RuleDef::class, $definition);
        self::assertSame('Use a dedicated view abstraction for rendering.', $definition->statement);
        self::assertSame('Team-Architecture', $definition->owner);
        self::assertSame(['docs/adr/view-abstraction.md', 'docs/ui/rendering.md'], $definition->refs);
    }

    public function testResolveReturnsPackageRuleDefinitionFromEnum(): void
    {
        $definition = (new ContextResolver())->resolve(PackageRules::InlineRuleDefinitions);

        self::assertInstanceOf(RuleDef::class, $definition);
        self::assertSame('Keep rule identifiers and definitions together on the enum.', $definition->statement);
        self::assertSame('Team-ItpContext', $definition->owner);
        self::assertSame([self::class, ValidatorTest::class], $definition->verifiedBy);
        self::assertSame(['docs/skills/itp-context.md', 'README.md'], $definition->refs);
    }

    public function testResolveSurfacesDefinitionErrors(): void
    {
        $enumClass = $this->defineFixture(
            'Broken',
            <<<'PHP'
use ItpContext\Model\RuleDef;

enum BrokenRules implements \ItpContext\Contract\RuleIdentifier
{
    case Example;

    public function getDefinition(): RuleDef
    {
        throw new \RuntimeException('Broken definition.');
    }
}
PHP
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Broken definition.');

        (new ContextResolver())->resolve($enumClass::Example);
    }

    /**
     * @return class-string
     */
    private function defineFixture(string $name, string $enumBody): string
    {
        $namespace = 'ItpContext\\Tests\\' . uniqid('ResolverFixture');
        $enumClass = $namespace . '\\' . $name . 'Rules';
        $enumPath = $this->fixturePath . '/' . $name . 'Rules.php';

        file_put_contents($enumPath, <<<PHP
<?php

declare(strict_types=1);

namespace {$namespace};

{$enumBody}
PHP);
        require_once $enumPath;

        /** @var class-string $enumClass */
        return $enumClass;
    }
}
