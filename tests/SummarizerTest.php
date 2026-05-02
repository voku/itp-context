<?php

declare(strict_types=1);

namespace ItpContext\Tests;

use ItpContext\Context\PackageRules;
use ItpContext\Service\Summarizer;
use PHPUnit\Framework\TestCase;

final class SummarizerTest extends TestCase
{
    private string $fixturePath;

    protected function setUp(): void
    {
        $this->fixturePath = sys_get_temp_dir() . '/itp-context-summarizer-test-' . bin2hex(random_bytes(8));
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

    public function testSummarizeContainsClassAndMethodRules(): void
    {
        $output = (new Summarizer())->summarize(dirname(__DIR__) . '/examples/basic-domain/src/DashboardView.php');

        self::assertStringContainsString('Context: DashboardView', $output);
        self::assertStringContainsString('ArchitectureRules::ViewAbstraction', $output);
        self::assertStringContainsString('ArchitectureRules::I18n', $output);
    }

    public function testSummarizeContainsPackageRulesForOwnServices(): void
    {
        $output = (new Summarizer())->summarize(dirname(__DIR__) . '/src/Service/ContextExporter.php');

        self::assertStringContainsString('Context: ContextExporter', $output);
        self::assertStringContainsString(PackageRules::AgentFriendlyMarkdown->name, $output);
    }

    public function testSummarizeRejectsMissingFiles(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('File not found');

        (new Summarizer())->summarize($this->fixturePath . '/missing.php');
    }

    public function testSummarizeRejectsFilesWithoutSymbols(): void
    {
        $path = $this->writeFixtureFile('NoSymbol.php', "<?php\n\$value = 1;\n");

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No class/interface/trait/enum found in file.');

        (new Summarizer())->summarize($path);
    }

    public function testSummarizeRendersCriticalAndImportantIcons(): void
    {
        [$criticalFile, $importantFile] = $this->createTierFixtures();

        self::assertStringContainsString('[CRITICAL]', (new Summarizer())->summarize($criticalFile));
        self::assertStringContainsString('[IMPORTANT]', (new Summarizer())->summarize($importantFile));
    }

    public function testSummarizeKeepsSuccessfulOutputWhenOneAttributeFails(): void
    {
        $filePath = $this->createMixedRuleFixture();

        $output = (new Summarizer())->summarize($filePath);

        self::assertStringContainsString('Valid statement.', $output);
        self::assertStringContainsString('⚠ Error:', $output);
    }

    public function testSummarizeRejectsNonAutoloadableSymbols(): void
    {
        $path = $this->writeFixtureFile('UnknownSubject.php', <<<PHP
<?php

declare(strict_types=1);

namespace UnknownFixture;

final class UnknownSubject
{
}
PHP);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Symbol not autoloadable: UnknownFixture\\UnknownSubject');

        (new Summarizer())->summarize($path);
    }

    public function testHandleIsPublic(): void
    {
        self::assertTrue((new \ReflectionMethod(Summarizer::class, 'handle'))->isPublic());
    }

    public function testCliReportsSummarizerFailuresToStderr(): void
    {
        $command = escapeshellarg(PHP_BINARY)
            . ' '
            . escapeshellarg(dirname(__DIR__) . '/bin/itp-context-summarize')
            . ' '
            . escapeshellarg($this->fixturePath . '/missing.php')
            . ' 2>&1';

        $output = [];
        $exitCode = 0;
        exec($command, $output, $exitCode);

        self::assertSame(1, $exitCode);
        self::assertSame(['File not found: ' . $this->fixturePath . '/missing.php'], $output);
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function createTierFixtures(): array
    {
        $namespace = 'ItpContext\\Tests\\SummarizerFixture' . bin2hex(random_bytes(4));

        $rulesPath = $this->writeFixtureFile('TierRules.php', <<<PHP
<?php

declare(strict_types=1);

namespace {$namespace};

enum TierRules implements \ItpContext\Contract\RuleIdentifier
{
    case CriticalRule;
    case ImportantRule;
}
PHP);
        $this->writeFixtureFile('TierCatalog.php', <<<PHP
<?php

declare(strict_types=1);

namespace {$namespace};

return [
    'CriticalRule' => new \ItpContext\Model\RuleDef(
        statement: 'Critical statement.',
        tier: \ItpContext\Enum\Tier::Critical,
        owner: 'Team-Critical',
        verifiedBy: [\ItpContext\Tests\SummarizerTest::class],
    ),
    'ImportantRule' => new \ItpContext\Model\RuleDef(
        statement: 'Important statement.',
        tier: \ItpContext\Enum\Tier::Important,
    ),
];
PHP);
        $criticalFile = $this->writeFixtureFile('CriticalSubject.php', <<<PHP
<?php

declare(strict_types=1);

namespace {$namespace};

use ItpContext\Attribute\Rule;

#[Rule(TierRules::CriticalRule)]
final class CriticalSubject
{
}
PHP);
        $importantFile = $this->writeFixtureFile('ImportantSubject.php', <<<PHP
<?php

declare(strict_types=1);

namespace {$namespace};

use ItpContext\Attribute\Rule;

#[Rule(TierRules::ImportantRule)]
final class ImportantSubject
{
}
PHP);

        require_once $rulesPath;
        require_once $criticalFile;
        require_once $importantFile;

        return [$criticalFile, $importantFile];
    }

    private function createMixedRuleFixture(): string
    {
        $namespace = 'ItpContext\\Tests\\SummarizerMixedFixture' . bin2hex(random_bytes(4));

        $rulesPath = $this->writeFixtureFile('MixedRules.php', <<<PHP
<?php

declare(strict_types=1);

namespace {$namespace};

enum MixedRules implements \ItpContext\Contract\RuleIdentifier
{
    case ValidRule;
    case MissingRule;
}
PHP);
        $this->writeFixtureFile('MixedCatalog.php', <<<PHP
<?php

declare(strict_types=1);

namespace {$namespace};

return [
    'ValidRule' => new \ItpContext\Model\RuleDef('Valid statement.'),
];
PHP);
        $filePath = $this->writeFixtureFile('MixedSubject.php', <<<PHP
<?php

declare(strict_types=1);

namespace {$namespace};

use ItpContext\Attribute\Rule;

final class MixedSubject
{
    #[Rule(MixedRules::ValidRule)]
    #[Rule(MixedRules::MissingRule)]
    public function render(): string
    {
        return 'ok';
    }
}
PHP);

        require_once $rulesPath;
        require_once $filePath;

        return $filePath;
    }

    private function writeFixtureFile(string $name, string $content): string
    {
        $path = $this->fixturePath . '/' . $name;
        file_put_contents($path, $content);

        return $path;
    }
}
