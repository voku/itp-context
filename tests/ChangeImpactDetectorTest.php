<?php

declare(strict_types=1);

namespace ItpContext\Tests;

use ItpContext\Service\ChangeImpactDetector;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ChangeImpactDetectorTest extends TestCase
{
    private string $repo;

    protected function setUp(): void
    {
        $this->repo = sys_get_temp_dir() . '/' . uniqid('itp-context-impact-');
        mkdir($this->repo . '/src', 0777, true);

        $this->git('init', '-q', '-b', 'main');
        $this->git('config', 'user.email', 'test@example.com');
        $this->git('config', 'user.name', 'Test');
        $this->git('config', 'commit.gpgsign', 'false');

        file_put_contents($this->repo . '/src/Rules.php', $this->rules('Read docs/adr/mailer.md first.'));
        file_put_contents($this->repo . '/src/Mailer.php', $this->mailer('return strtoupper($to);'));
        $this->git('add', '-A');
        $this->git('commit', '-q', '-m', 'baseline');
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->repo));
    }

    public function testNoChangeProducesNoNotices(): void
    {
        self::assertSame([], (new ChangeImpactDetector())->detect($this->repo, 'HEAD'));
    }

    public function testFormattingAndCommentOnlyChangesProduceNoNotices(): void
    {
        file_put_contents($this->repo . '/src/Mailer.php', $this->mailer("// reformatted\n        return   strtoupper( \$to );"));

        self::assertSame([], (new ChangeImpactDetector())->detect($this->repo, 'HEAD'));
    }

    public function testModifiedAnnotatedMethodNotifiesWithBaselineGuidance(): void
    {
        file_put_contents($this->repo . '/src/Mailer.php', $this->mailer('return strtolower($to);'));

        $notices = (new ChangeImpactDetector())->detect($this->repo, 'HEAD');

        self::assertCount(1, $notices);
        self::assertSame('modified', $notices[0]->kind);
        self::assertSame('Fx\Mailer::send', $notices[0]->subject);
        self::assertSame('Fx\Rules::Mailer', $notices[0]->ruleId);
        self::assertSame('Read docs/adr/mailer.md first.', $notices[0]->guidance);
        self::assertSame(['docs/adr/mailer.md'], $notices[0]->refs);
        self::assertSame('Use the mailer abstraction.', $notices[0]->statement);
    }

    public function testNoticeIdentityIsDeterministicAndFollowsTheChange(): void
    {
        $detector = new ChangeImpactDetector();

        file_put_contents($this->repo . '/src/Mailer.php', $this->mailer('return strtolower($to);'));
        $first = $detector->detect($this->repo, 'HEAD')[0]->id;
        self::assertSame($first, $detector->detect($this->repo, 'HEAD')[0]->id);

        file_put_contents($this->repo . '/src/Mailer.php', $this->mailer('return ucfirst($to);'));
        self::assertNotSame($first, $detector->detect($this->repo, 'HEAD')[0]->id);
    }

    public function testRemovedAnnotationIsResolvedFromBaseline(): void
    {
        file_put_contents($this->repo . '/src/Mailer.php', str_replace("    #[Rule(Rules::Mailer)]\n", '', $this->mailer('return strtoupper($to);')));

        $notices = (new ChangeImpactDetector())->detect($this->repo, 'HEAD');

        self::assertCount(1, $notices);
        self::assertSame('annotation-removed', $notices[0]->kind);
        self::assertSame('Read docs/adr/mailer.md first.', $notices[0]->guidance);
    }

    public function testRemovedFileNotifiesPerProtectedSymbol(): void
    {
        unlink($this->repo . '/src/Mailer.php');

        $kinds = array_map(static fn ($notice): string => $notice->kind, (new ChangeImpactDetector())->detect($this->repo, 'HEAD'));

        self::assertSame(['removed'], $kinds);
    }

    public function testChangedGuidanceNotifiesAgainWithNewGuidance(): void
    {
        file_put_contents($this->repo . '/src/Rules.php', $this->rules('Ask the owner before touching this.'));

        $notices = (new ChangeImpactDetector())->detect($this->repo, 'HEAD');

        self::assertCount(1, $notices);
        self::assertSame('rule-definition-changed', $notices[0]->kind);
        self::assertSame('Fx\Rules::Mailer', $notices[0]->subject);
        self::assertSame('Ask the owner before touching this.', $notices[0]->guidance);
    }

    public function testProposedRevisionCanBeComparedInsteadOfTheWorkingTree(): void
    {
        file_put_contents($this->repo . '/src/Mailer.php', $this->mailer('return strtolower($to);'));
        $this->git('commit', '-q', '-am', 'change');
        file_put_contents($this->repo . '/src/Mailer.php', $this->mailer('return strtoupper($to);'));

        self::assertCount(1, (new ChangeImpactDetector())->detect($this->repo, 'HEAD~1', 'HEAD'));
        self::assertSame([], (new ChangeImpactDetector())->detect($this->repo, 'HEAD~1', 'HEAD~1'));
    }

    public function testNoticesJsonMatchesTheGenericNoticeInput(): void
    {
        file_put_contents($this->repo . '/src/Mailer.php', $this->mailer('return strtolower($to);'));
        $detector = new ChangeImpactDetector();

        /** @var array{notices: list<array<string, mixed>>} $decoded */
        $decoded = json_decode($detector->toNoticesJson($detector->detect($this->repo, 'HEAD')), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame(['id', 'title', 'message', 'subject', 'refs'], array_keys($decoded['notices'][0]));
        self::assertSame('Protected symbol modified: Use the mailer abstraction.', $decoded['notices'][0]['title']);
        self::assertSame('Read docs/adr/mailer.md first.', $decoded['notices'][0]['message']);
    }

    public function testUnknownBaseFailsLoudly(): void
    {
        $this->expectException(RuntimeException::class);

        (new ChangeImpactDetector())->detect($this->repo, 'does-not-exist');
    }

    public function testNoticeIdIsAStableHashOfKindRuleSubjectBaseChangeAndGuidance(): void
    {
        unlink($this->repo . '/src/Mailer.php');

        $notice = (new ChangeImpactDetector())->detect($this->repo, 'HEAD')[0];
        $baseSha = trim((string) shell_exec('git -C ' . escapeshellarg($this->repo) . ' rev-parse HEAD'));

        self::assertSame(
            substr(sha1(implode("\0", ['removed', 'Fx\Rules::Mailer', 'Fx\Mailer::send', $baseSha, '', 'Read docs/adr/mailer.md first.'])), 0, 20),
            $notice->id,
        );
        self::assertSame('src/Mailer.php', $notice->sourcePath);
    }

    public function testAnnotatedTagResolvesToItsCommit(): void
    {
        file_put_contents($this->repo . '/src/Mailer.php', $this->mailer('return strtolower($to);'));
        $this->git('tag', '-a', 'trusted', '-m', 'trusted base', 'HEAD');

        $byTag = (new ChangeImpactDetector())->detect($this->repo, 'trusted');
        $byHead = (new ChangeImpactDetector())->detect($this->repo, 'HEAD');

        self::assertCount(1, $byTag);
        self::assertSame($byHead[0]->id, $byTag[0]->id);
    }

    public function testAddedAndNonPhpFilesAndUnprotectedChangesAreIgnored(): void
    {
        file_put_contents($this->repo . '/README.md', 'hello');
        file_put_contents($this->repo . '/src/New.php', $this->mailer('return 1;'));
        $this->git('add', '-A');
        file_put_contents($this->repo . '/src/Mailer.php', str_replace('public function other(): void
    {
    }', 'public function other(): void
    {
        echo 1;
    }', $this->mailer('return strtoupper($to);')));

        self::assertSame([], (new ChangeImpactDetector())->detect($this->repo, 'HEAD'));
    }

    public function testClassLevelRuleNotifiesWhenAnyMethodChanges(): void
    {
        file_put_contents($this->repo . '/src/Mailer.php', str_replace("final class Mailer", "#[Rule(Rules::Mailer)]\nfinal class Mailer", $this->mailer('return strtoupper($to);')));
        $this->git('add', '-A');
        $this->git('commit', '-q', '-m', 'class level');
        file_put_contents($this->repo . '/src/Mailer.php', str_replace('public function other(): void
    {
    }', 'public function other(): void
    {
        echo 1;
    }', (string) file_get_contents($this->repo . '/src/Mailer.php')));

        $subjects = array_map(static fn ($notice): string => $notice->subject, (new ChangeImpactDetector())->detect($this->repo, 'HEAD'));

        self::assertSame(['Fx\Mailer'], $subjects);
    }

    public function testRenamedFileIsReportedAsRemovedFromItsBasePath(): void
    {
        $this->git('mv', 'src/Mailer.php', 'src/Moved.php');

        $notices = (new ChangeImpactDetector())->detect($this->repo, 'HEAD');

        self::assertCount(1, $notices);
        self::assertSame('removed', $notices[0]->kind);
        self::assertSame('src/Mailer.php', $notices[0]->sourcePath);
    }

    public function testUnresolvableRuleFallsBackToItsIdWithoutGuidance(): void
    {
        $this->git('rm', '-q', 'src/Rules.php');
        $this->git('commit', '-q', '-m', 'drop rules');
        file_put_contents($this->repo . '/src/Mailer.php', $this->mailer('return strtolower($to);'));

        $notices = (new ChangeImpactDetector())->detect($this->repo, 'HEAD');

        self::assertCount(1, $notices);
        self::assertSame('Fx\Rules::Mailer', $notices[0]->statement);
        self::assertNull($notices[0]->guidance);
        self::assertSame([], $notices[0]->refs);
        self::assertStringContainsString('No changeGuidance is defined', $notices[0]->toArray()['message']);
        self::assertSame('Protected symbol modified: Fx\Rules::Mailer', $notices[0]->toArray()['title']);
    }

    public function testFormattingOnlyRuleDefinitionChangeProducesNoNotice(): void
    {
        file_put_contents($this->repo . '/src/Rules.php', str_replace("statement: 'Use", "// comment\n                statement:   'Use", $this->rules('Read docs/adr/mailer.md first.')));

        self::assertSame([], (new ChangeImpactDetector())->detect($this->repo, 'HEAD'));
    }

    public function testRemovedRuleDefinitionAndRemovedRulesFileAreReported(): void
    {
        unlink($this->repo . '/src/Rules.php');

        $notices = (new ChangeImpactDetector())->detect($this->repo, 'HEAD');

        self::assertCount(1, $notices);
        self::assertSame('rule-removed', $notices[0]->kind);
        self::assertSame('Fx\Rules::Mailer', $notices[0]->subject);
        self::assertSame('Read docs/adr/mailer.md first.', $notices[0]->guidance);
        self::assertSame(['docs/adr/mailer.md'], $notices[0]->refs);
        self::assertSame('Rule definition removed: Use the mailer abstraction.', $notices[0]->toArray()['title']);
    }

    public function testChangedGuidanceChangesTheNoticeIdentity(): void
    {
        $detector = new ChangeImpactDetector();
        file_put_contents($this->repo . '/src/Rules.php', $this->rules('One.'));
        $one = $detector->detect($this->repo, 'HEAD')[0];
        file_put_contents($this->repo . '/src/Rules.php', $this->rules('Two.'));
        $two = $detector->detect($this->repo, 'HEAD')[0];

        self::assertNotSame($one->id, $two->id);
        self::assertSame(['docs/adr/mailer.md'], $two->refs);
        self::assertSame('Rule definition changed: Use the mailer abstraction.', $two->toArray()['title']);
    }

    public function testNoticesAreOrderedById(): void
    {
        file_put_contents($this->repo . '/src/Mailer.php', $this->mailer('return strtolower($to);'));
        file_put_contents($this->repo . '/src/Rules.php', $this->rules('Changed.'));

        $ids = array_map(static fn ($notice): string => $notice->id, (new ChangeImpactDetector())->detect($this->repo, 'HEAD'));
        $sorted = $ids;
        sort($sorted);

        self::assertCount(2, $ids);
        self::assertSame($sorted, $ids);
    }

    public function testRemovedFileInProposedRevisionIsReported(): void
    {
        $this->git('rm', '-q', 'src/Mailer.php');
        $this->git('commit', '-q', '-m', 'remove');

        self::assertSame(['removed'], array_map(static fn ($notice): string => $notice->kind, (new ChangeImpactDetector())->detect($this->repo, 'HEAD~1', 'HEAD')));
    }

    public function testNoticesJsonIsPrettyPrintedWithUnescapedSlashes(): void
    {
        $detector = new ChangeImpactDetector();

        self::assertSame("{\n    \"notices\": []\n}\n", $detector->toNoticesJson([]));

        file_put_contents($this->repo . '/src/Mailer.php', $this->mailer('return strtolower($to);'));
        $json = $detector->toNoticesJson($detector->detect($this->repo, 'HEAD'));

        self::assertStringContainsString('"docs/adr/mailer.md"', $json);
        self::assertStringContainsString("\n        {\n            \"id\"", $json);
        self::assertStringEndsWith("}\n", $json);
    }

    public function testUnknownBaseNamesTheFailingGitCommand(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('git rev-parse failed: ref or path not found');

        (new ChangeImpactDetector())->detect($this->repo, 'does-not-exist');
    }

    public function testGitErrorOutputIsPartOfTheFailureWithoutTrailingWhitespace(): void
    {
        try {
            (new ChangeImpactDetector())->detect($this->repo, 'HEAD', 'no-such-head');
            self::fail('Expected a RuntimeException.');
        } catch (RuntimeException $exception) {
            self::assertMatchesRegularExpression('/^git diff failed: .*(bad revision|unknown revision)/', $exception->getMessage());
            self::assertSame(trim($exception->getMessage()), $exception->getMessage());
        }
    }

    public function testUnreadableWorkingTreeFileFailsLoudly(): void
    {
        if (function_exists('posix_geteuid') && posix_geteuid() === 0) {
            self::markTestSkipped('File permissions are not enforced for root.');
        }

        file_put_contents($this->repo . '/src/Mailer.php', $this->mailer('return strtolower($to);'));
        chmod($this->repo . '/src/Mailer.php', 0000);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cannot read src/Mailer.php.');

        try {
            (new ChangeImpactDetector())->detect($this->repo, 'HEAD');
        } finally {
            chmod($this->repo . '/src/Mailer.php', 0644);
        }
    }

    public function testRepositoryPathWithTrailingSlashWorks(): void
    {
        file_put_contents($this->repo . '/src/Mailer.php', $this->mailer('return strtolower($to);'));

        self::assertCount(1, (new ChangeImpactDetector())->detect($this->repo . '/', 'HEAD'));
    }

    public function testLaterMatchArmDoesNotOverwriteRuleDefinition(): void
    {
        $rules = str_replace(
            "    }\n}\n",
            "    }\n\n    public function label(): string\n    {\n        return match (\$this) {\n            self::Mailer => 'Mailer',\n        };\n    }\n}\n",
            $this->rules('Read docs/adr/mailer.md first.'),
        );
        file_put_contents($this->repo . '/src/Rules.php', $rules);
        file_put_contents($this->repo . '/src/Mailer.php', $this->mailer('return strtolower($to);'));

        $notices = (new ChangeImpactDetector())->detect($this->repo, 'HEAD');

        self::assertCount(1, $notices);
        self::assertSame('Use the mailer abstraction.', $notices[0]->statement);
        self::assertSame('Read docs/adr/mailer.md first.', $notices[0]->guidance);
        self::assertSame(['docs/adr/mailer.md'], $notices[0]->refs);
    }

    public function testAliasedRuleDefConstructorRetainsGuidance(): void
    {
        $rules = str_replace(
            ['use ItpContext\Model\RuleDef;', 'getDefinition(): RuleDef', 'new RuleDef('],
            ['use ItpContext\Model\RuleDef as Definition;', 'getDefinition(): Definition', 'new Definition('],
            $this->rules('Read docs/adr/mailer.md first.'),
        );
        file_put_contents($this->repo . '/src/Rules.php', $rules);
        file_put_contents($this->repo . '/src/Mailer.php', $this->mailer('return strtolower($to);'));

        $notices = (new ChangeImpactDetector())->detect($this->repo, 'HEAD');

        self::assertCount(1, $notices);
        self::assertSame('Use the mailer abstraction.', $notices[0]->statement);
        self::assertSame('Read docs/adr/mailer.md first.', $notices[0]->guidance);
        self::assertSame(['docs/adr/mailer.md'], $notices[0]->refs);
    }

    public function testFullyQualifiedRuleDefConstructorRetainsGuidance(): void
    {
        $rules = str_replace(
            ['new RuleDef(', 'getDefinition(): RuleDef'],
            ['new \ItpContext\Model\RuleDef(', 'getDefinition(): \ItpContext\Model\RuleDef'],
            $this->rules('Read docs/adr/mailer.md first.'),
        );
        file_put_contents($this->repo . '/src/Rules.php', $rules);
        file_put_contents($this->repo . '/src/Mailer.php', $this->mailer('return strtolower($to);'));

        $notices = (new ChangeImpactDetector())->detect($this->repo, 'HEAD');

        self::assertCount(1, $notices);
        self::assertSame('Use the mailer abstraction.', $notices[0]->statement);
        self::assertSame('Read docs/adr/mailer.md first.', $notices[0]->guidance);
    }

    public function testEarlierUnrelatedMatchDoesNotShadowDefinition(): void
    {
        $rules = str_replace(
            '    public function getDefinition(): RuleDef',
            "    public function label(): string\n    {\n        return match (\$this) {\n            self::Mailer => 'Mailer',\n        };\n    }\n\n    public function getDefinition(): RuleDef",
            $this->rules('Read docs/adr/mailer.md first.'),
        );
        file_put_contents($this->repo . '/src/Rules.php', $rules);
        file_put_contents($this->repo . '/src/Mailer.php', $this->mailer('return strtolower($to);'));

        $notices = (new ChangeImpactDetector())->detect($this->repo, 'HEAD');

        self::assertCount(1, $notices);
        self::assertSame('Use the mailer abstraction.', $notices[0]->statement);
        self::assertSame('Read docs/adr/mailer.md first.', $notices[0]->guidance);
    }

    private function git(string ...$arguments): void
    {
        exec(sprintf('git -C %s %s 2>&1', escapeshellarg($this->repo), implode(' ', array_map('escapeshellarg', $arguments))), $output, $code);
        self::assertSame(0, $code, implode("\n", $output));
    }

    private function rules(string $guidance): string
    {
        return <<<PHP
<?php

declare(strict_types=1);

namespace Fx;

use ItpContext\Contract\RuleIdentifier;
use ItpContext\Model\RuleDef;

enum Rules implements RuleIdentifier
{
    case Mailer;

    public function getDefinition(): RuleDef
    {
        return match (\$this) {
            self::Mailer => new RuleDef(
                statement: 'Use the mailer abstraction.',
                refs: ['docs/adr/mailer.md'],
                changeGuidance: '{$guidance}',
            ),
        };
    }
}

PHP;
    }

    private function mailer(string $body): string
    {
        return <<<PHP
<?php

declare(strict_types=1);

namespace Fx;

use ItpContext\Attribute\Rule;

final class Mailer
{
    #[Rule(Rules::Mailer)]
    public function send(string \$to): string
    {
        {$body}
    }

    public function other(): void
    {
    }
}

PHP;
    }
}
