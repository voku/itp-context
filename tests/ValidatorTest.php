<?php

declare(strict_types=1);

namespace ItpContext\Tests;

use ItpContext\Context\PackageRules;
use ItpContext\Service\Validator;
use ItpContextExample\Context\ArchitectureRules;
use PHPUnit\Framework\TestCase;

final class ValidatorTest extends TestCase
{
    private string $fixturePath;

    protected function setUp(): void
    {
        $this->fixturePath = sys_get_temp_dir() . '/' . uniqid('itp-context-validator-test-');
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

    public function testValidateEnumClassReturnsNoErrorsForMatchingDefinitions(): void
    {
        $errors = (new Validator())->validateEnumClass(ArchitectureRules::class);

        self::assertSame([], $errors);
    }

    public function testValidateEnumClassReturnsNoErrorsForPackageRules(): void
    {
        $errors = (new Validator())->validateEnumClass(PackageRules::class);

        self::assertSame([], $errors);
    }

    public function testValidateEnumClassRejectsNonRuleIdentifierClasses(): void
    {
        $errors = (new Validator())->validateEnumClass(\stdClass::class);

        self::assertSame(['Not a RuleIdentifier enum: stdClass'], $errors);
    }

    public function testValidateEnumClassReportsBrokenDefinitions(): void
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

        $errors = (new Validator())->validateEnumClass($enumClass);

        self::assertSame(['❌ [Example] Broken definition.'], $errors);
    }

    /**
     * @return class-string
     */
    private function defineFixture(string $name, string $enumBody): string
    {
        $namespace = 'ItpContext\\Tests\\' . uniqid('ValidatorFixture');
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
