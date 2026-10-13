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
