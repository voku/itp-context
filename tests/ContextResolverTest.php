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
        $this->fixturePath = sys_get_temp_dir() . '/itp-context-resolver-test-' . bin2hex(random_bytes(8));
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

    public function testResolveReturnsRuleDefinitionFromCatalog(): void
    {
        $definition = (new ContextResolver())->resolve(ArchitectureRules::ViewAbstraction);

        self::assertInstanceOf(RuleDef::class, $definition);
        self::assertSame('Use a dedicated view abstraction for rendering.', $definition->statement);
        self::assertSame('Team-Architecture', $definition->owner);
    }

    public function testResolveReturnsPackageRuleDefinitionFromCatalog(): void
    {
        $definition = (new ContextResolver())->resolve(PackageRules::CatalogByConvention);

        self::assertInstanceOf(RuleDef::class, $definition);
        self::assertSame('Match *Rules.php enums with sibling *Catalog.php files by convention.', $definition->statement);
        self::assertNull($definition->owner);
    }

    public function testResolveRejectsOrphanedRuleIds(): void
    {
        $enumClass = $this->defineFixture(
            'Orphaned',
            "enum OrphanedRules implements \\ItpContext\\Contract\\RuleIdentifier { case Missing; }\n",
            "return [];\n"
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Orphaned rule ID');

        (new ContextResolver())->resolve($enumClass::Missing);
    }

    public function testResolveRejectsMissingCatalogFiles(): void
    {
        $enumClass = $this->defineFixture(
            'MissingCatalog',
            "enum MissingCatalogRules implements \\ItpContext\\Contract\\RuleIdentifier { case Example; }\n",
            null
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Missing context catalog');

        (new ContextResolver())->resolve($enumClass::Example);
    }

    public function testResolveRejectsNonArrayCatalogs(): void
    {
        $enumClass = $this->defineFixture(
            'NonArray',
            "enum NonArrayRules implements \\ItpContext\\Contract\\RuleIdentifier { case Example; }\n",
            "return 42;\n"
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Catalog must return an array');

        (new ContextResolver())->resolve($enumClass::Example);
    }

    public function testResolveRejectsCatalogsWithNonStringKeys(): void
    {
        $enumClass = $this->defineFixture(
            'InvalidKey',
            "enum InvalidKeyRules implements \\ItpContext\\Contract\\RuleIdentifier { case Example; }\n",
            "return [0 => new \\ItpContext\\Model\\RuleDef('Example rule')];\n"
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('keys must be strings');

        (new ContextResolver())->resolve($enumClass::Example);
    }

    public function testResolveRejectsCatalogsWithInvalidValues(): void
    {
        $enumClass = $this->defineFixture(
            'InvalidValue',
            "enum InvalidValueRules implements \\ItpContext\\Contract\\RuleIdentifier { case Example; }\n",
            "return ['Example' => 'nope'];\n"
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('values must be RuleDef instances');

        (new ContextResolver())->resolve($enumClass::Example);
    }

    public function testResolveRejectsStaleCatalogEntries(): void
    {
        $enumClass = $this->defineFixture(
            'Stale',
            "enum StaleRules implements \\ItpContext\\Contract\\RuleIdentifier { case Example; }\n",
            "return ['Other' => new \\ItpContext\\Model\\RuleDef('Other rule')];\n"
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Stale catalog entry');

        (new ContextResolver())->resolve($enumClass::Example);
    }

    public function testResolveRejectsEvaluatedEnumsThatDoNotFollowTheRulesSuffixConvention(): void
    {
        $namespace = 'EvalFixture' . bin2hex(random_bytes(4));
        eval("namespace {$namespace}; enum EvalRules implements \\ItpContext\\Contract\\RuleIdentifier { case Example; }");
        $enumClass = $namespace . '\\EvalRules';

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Rule enum file must end with 'Rules.php'");

        (new ContextResolver())->resolve($enumClass::Example);
    }

    public function testResolveRejectsEnumsNotFollowingRulesSuffixConvention(): void
    {
        $namespace = 'ItpContext\\Tests\\BadName' . bin2hex(random_bytes(4));
        $enumPath = $this->fixturePath . '/CustomEnum.php';
        file_put_contents($enumPath, <<<PHP
<?php

declare(strict_types=1);

namespace {$namespace};

enum CustomEnum implements \ItpContext\Contract\RuleIdentifier
{
    case Example;
}
PHP);
        require_once $enumPath;
        $enumClass = $namespace . '\\CustomEnum';

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Rule enum file must end with 'Rules.php'");

        (new ContextResolver())->resolve($enumClass::Example);
    }

    private function defineFixture(string $name, string $enumBody, ?string $catalogBody): string
    {
        $namespace = 'ItpContext\\Tests\\ResolverFixture' . bin2hex(random_bytes(4));
        $enumClass = $namespace . '\\' . $name . 'Rules';
        $enumPath = $this->fixturePath . '/' . $name . 'Rules.php';

        file_put_contents($enumPath, <<<PHP
<?php

declare(strict_types=1);

namespace {$namespace};

{$enumBody}
PHP);
        require_once $enumPath;

        if ($catalogBody !== null) {
            file_put_contents($this->fixturePath . '/' . $name . 'Catalog.php', <<<PHP
<?php

declare(strict_types=1);

namespace {$namespace};

{$catalogBody}
PHP);
        }

        return $enumClass;
    }
}
