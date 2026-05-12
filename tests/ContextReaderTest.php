<?php

declare(strict_types=1);

namespace ItpContext\Tests;

use ItpContext\Service\ContextReader;
use PHPUnit\Framework\TestCase;

final class ContextReaderTest extends TestCase
{
    private string $fixturePath;

    protected function setUp(): void
    {
        $this->fixturePath = sys_get_temp_dir() . '/' . uniqid('itp-context-reader-test-');
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

    public function testReadBuildsDocumentsForFunctionsAndClasses(): void
    {
        $path = $this->writeFixtureFile('MultiDocument.php', <<<'PHP'
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

#[Rule(ArchitectureRules::ViewAbstraction)]
final class DashboardView
{
    #[Rule(ArchitectureRules::I18n)]
    public function render(): string
    {
        return 'ok';
    }
}
PHP);

        $documents = (new ContextReader())->read($path);

        self::assertCount(2, $documents);
        self::assertSame('PHP:function:ItpContext\\Tests\\Fixtures\\helper', $documents[0]->id);
        self::assertSame('function', $documents[0]->kind);
        self::assertSame(['ItpContextExample\\Context\\ArchitectureRules::ViewAbstraction'], $documents[0]->ruleIds);

        self::assertSame('PHP:ItpContext\\Tests\\Fixtures\\DashboardView', $documents[1]->id);
        self::assertSame(
            [
                'ItpContextExample\\Context\\ArchitectureRules::I18n',
                'ItpContextExample\\Context\\ArchitectureRules::ViewAbstraction',
            ],
            $documents[1]->ruleIds
        );
        self::assertSame(['Team-Architecture'], $documents[1]->owners);
        self::assertSame(['render'], $documents[1]->annotatedMethods);
        self::assertStringContainsString('## Method: `render`', $documents[1]->body);
    }

    public function testReadKeepsRawRuleIdsWhenEnumCannotBeResolved(): void
    {
        $path = $this->writeFixtureFile('UnknownRules.php', <<<'PHP'
<?php

declare(strict_types=1);

namespace ItpContext\Tests\Fixtures;

use ItpContext\Attribute\Rule;

#[Rule(UnknownRules::Example)]
final class UnknownRulesSubject
{
}
PHP);

        $documents = (new ContextReader())->read($path);

        self::assertCount(1, $documents);
        self::assertSame(['ItpContext\Tests\Fixtures\UnknownRules::Example'], $documents[0]->ruleIds);
        self::assertStringContainsString('Raw rule annotation.', $documents[0]->body);
    }

    private function writeFixtureFile(string $name, string $content): string
    {
        $path = $this->fixturePath . '/' . $name;
        file_put_contents($path, $content);

        return $path;
    }
}
