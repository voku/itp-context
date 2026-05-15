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
        $this->fixturePath = sys_get_temp_dir() . '/' . uniqid('itp-context-summarizer-test-');
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
        self::assertStringContainsString('**Why:** A dedicated view layer keeps rendering concerns isolated from domain and controller code.', $output);
        self::assertStringContainsString('**Why:** Locale-aware rendering avoids user-facing regressions once the UI contains translated labels and formatted values.', $output);
        self::assertStringContainsString('**Proof:** ItpContextExample\Tests\I18nTest', $output);
        self::assertStringContainsString('**Refs:** docs/adr/view-abstraction.md, docs/ui/rendering.md', $output);
        self::assertStringContainsString('**Refs:** docs/adr/i18n.md', $output);
    }

    public function testSummarizeContainsPackageRulesForOwnServices(): void
    {
        $output = (new Summarizer())->summarize(dirname(__DIR__) . '/src/Service/ContextExporter.php');

        self::assertStringContainsString('Context: ContextExporter', $output);
        self::assertStringContainsString(PackageRules::AgentFriendlyMarkdown->name, $output);
        self::assertStringContainsString('**Owner:** Team-ItpContext', $output);
        self::assertStringContainsString('**Proof:** ItpContext\Tests\ContextExporterTest', $output);
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
        $this->expectExceptionMessage('No class/interface/trait/enum/function found in file.');

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

    public function testSummarizeFallsBackToRawAnnotationsForNonAutoloadableSymbols(): void
    {
        $path = $this->writeFixtureFile('UnknownSubject.php', <<<PHP
<?php

declare(strict_types=1);

namespace UnknownFixture;

use ItpContext\Attribute\Rule;
use ItpContextExample\Context\ArchitectureRules;

#[Rule(ArchitectureRules::ViewAbstraction)]
final class UnknownSubject
{
}
PHP);

        $output = (new Summarizer())->summarize($path);

        self::assertStringContainsString('Context: UnknownSubject', $output);
        self::assertStringContainsString('ArchitectureRules::ViewAbstraction', $output);
        self::assertStringContainsString('Use a dedicated view abstraction for rendering.', $output);
    }

    public function testSummarizeIncludesMultipleSymbolsAndFunctionsFromOneFile(): void
    {
        $path = $this->writeFixtureFile('MultiSymbol.php', <<<PHP
<?php

declare(strict_types=1);

namespace ItpContext\Tests\Fixtures;

use ItpContext\Attribute\Rule;
use ItpContextExample\Context\ArchitectureRules;

#[Rule(ArchitectureRules::ViewAbstraction)]
function render_dashboard(): string
{
    return 'ok';
}

final class Helper
{
}

#[Rule(ArchitectureRules::ViewAbstraction)]
final class DashboardPresenter
{
    #[Rule(ArchitectureRules::I18n)]
    public function render(): string
    {
        return 'ok';
    }
}
PHP);

        $output = (new Summarizer())->summarize($path);

        self::assertStringContainsString('Context: function `ItpContext\Tests\Fixtures\render_dashboard()`', $output);
        self::assertStringContainsString('Context: Helper', $output);
        self::assertStringContainsString('Context: DashboardPresenter', $output);
        self::assertStringContainsString('## Method: `render`', $output);
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

    public function testCliWritesOnlyTheErrorMessageToStderrAndExitsWithCodeOne(): void
    {
        $command = [
            PHP_BINARY,
            dirname(__DIR__) . '/bin/itp-context-summarize',
            $this->fixturePath . '/missing.php',
        ];

        $process = proc_open(
            $command,
            [
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ],
            $pipes
        );

        self::assertIsResource($process);

        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        self::assertSame('', $stdout);
        self::assertSame('File not found: ' . $this->fixturePath . "/missing.php\n", $stderr);
        self::assertSame(1, $exitCode);
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function createTierFixtures(): array
    {
        $namespace = 'ItpContext\\Tests\\' . uniqid('SummarizerFixture');

        $rulesPath = $this->writeFixtureFile('TierRules.php', <<<PHP
<?php

declare(strict_types=1);

namespace {$namespace};

enum TierRules implements \ItpContext\Contract\RuleIdentifier
{
    case CriticalRule;
    case ImportantRule;

    public function getDefinition(): \ItpContext\Model\RuleDef
    {
        return match (\$this) {
            self::CriticalRule => new \ItpContext\Model\RuleDef(
                statement: 'Critical statement.',
                tier: \ItpContext\Enum\Tier::Critical,
                owner: 'Team-Critical',
                verifiedBy: [\ItpContext\Tests\SummarizerTest::class],
            ),
            self::ImportantRule => new \ItpContext\Model\RuleDef(
                statement: 'Important statement.',
                tier: \ItpContext\Enum\Tier::Important,
            ),
        };
    }
}
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
        $namespace = 'ItpContext\\Tests\\' . uniqid('SummarizerMixedFixture');

        $rulesPath = $this->writeFixtureFile('MixedRules.php', <<<PHP
<?php

declare(strict_types=1);

namespace {$namespace};

enum MixedRules implements \ItpContext\Contract\RuleIdentifier
{
    case ValidRule;
    case MissingRule;

    public function getDefinition(): \ItpContext\Model\RuleDef
    {
        return match (\$this) {
            self::ValidRule => new \ItpContext\Model\RuleDef('Valid statement.'),
            self::MissingRule => throw new \RuntimeException('Broken definition.'),
        };
    }
}
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
