<?php

declare(strict_types=1);

namespace ItpContext\Tests;

use ItpContext\Service\SourceRuleHintProjector;
use ItpContext\Service\TokenParser;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;

/** @internal */
final class SourceRuleHintProjectorTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/agent-loop-itp-hints-' . bin2hex(random_bytes(6));
        if (!mkdir($this->root . '/src', 0o775, true) && !is_dir($this->root . '/src')) {
            throw new RuntimeException('Cannot create architecture hints fixture.');
        }
    }

    protected function tearDown(): void
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->root, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($iterator as $file) {
            if ($file->isDir() && !$file->isLink()) {
                rmdir($file->getPathname());
            } else {
                unlink($file->getPathname());
            }
        }
        rmdir($this->root);
    }

    public function testAnnotatedSymbolsProduceBoundedHintsWithoutAutoloadingRuleDefinitions(): void
    {
        // Acme\ArchitectureRules does not exist: the projection must not try
        // to load this enum or invent its tests/ADRs from the identifier.
        file_put_contents($this->root . '/src/Widget.php', <<<'PHP'
<?php

namespace Example;

use ItpContext\Attribute\Rule;

#[Rule(\Acme\ArchitectureRules::VerifyBehavior)]
final class Widget
{
    #[Rule(\Acme\ArchitectureRules::ReviewCallers)]
    public function change(): void
    {
    }
}
PHP);

        $before = hash_file('sha256', $this->root . '/src/Widget.php');
        $result = (new SourceRuleHintProjector($this->root))->project(['src/Widget.php', 'src/Widget.php']);

        self::assertSame('itp-context', $result['owner']);
        self::assertSame('ready', $result['status']);
        self::assertSame('none', $result['authority']);
        self::assertSame('syntax_only', $result['resolution']);
        self::assertSame([
            [
                'rule_id' => 'Acme\ArchitectureRules::ReviewCallers',
                'subjects' => ['Example\Widget::change'],
                'files' => ['src/Widget.php'],
                'evidence' => 'source_attribute_only',
            ],
            [
                'rule_id' => 'Acme\ArchitectureRules::VerifyBehavior',
                'subjects' => ['Example\Widget'],
                'files' => ['src/Widget.php'],
                'evidence' => 'source_attribute_only',
            ],
        ], $result['rules']);
        self::assertSame([[
            'path' => 'src/Widget.php',
            'sha256' => 'sha256:' . $before,
        ]], $result['inspected_files']);
        self::assertSame($before, hash_file('sha256', $this->root . '/src/Widget.php'));
        self::assertArrayNotHasKey('refs', $result['rules'][0]);
    }

    public function testContentParserAndFileParserReturnTheSameTargets(): void
    {
        $source = <<<'PHP'
<?php
namespace Sample;
use ItpContext\Attribute\Rule;
#[Rule(\Acme\ArchitectureRules::Guard)]
final class Guarded {}
PHP;
        file_put_contents($this->root . '/src/Guarded.php', $source);

        $parser = new TokenParser();
        self::assertEquals(
            $parser->getRuleTargetsFromFile($this->root . '/src/Guarded.php'),
            $parser->getRuleTargetsFromCode($source),
        );
    }

    public function testSourceByteLimitIsNotJustAPreReadFilesizeGuess(): void
    {
        file_put_contents(
            $this->root . '/src/Large.php',
            '<?php #[\ItpContext\Attribute\Rule(\Acme\Rules::Huge)] class Large {}'
            . str_repeat(' ', 1048577),
        );

        $result = (new SourceRuleHintProjector($this->root))->project(['src/Large.php']);

        self::assertSame('abstained', $result['status']);
        self::assertSame('no_readable_php_files', $result['reason']);
        self::assertSame(1, $result['ignored_paths']);
        self::assertSame([], $result['inspected_files']);
    }

    public function testRejectsPathsOutsideRootIncludingSymlinkEscape(): void
    {
        $outside = tempnam(sys_get_temp_dir(), 'agent-loop-outside-');
        if ($outside === false) {
            throw new RuntimeException('Cannot create outside fixture.');
        }

        try {
            file_put_contents($outside, '<?php #[\ItpContext\Attribute\Rule(\Acme\Rules::Escape)] class OutsideRule {}');
            $link = $this->root . '/src/escape.php';
            if (!symlink($outside, $link)) {
                self::markTestSkipped('Symlinks unavailable on this runner.');
            }

            $result = (new SourceRuleHintProjector($this->root))->project([
                'src/escape.php',
                '../' . basename($outside),
                '/etc/passwd',
            ]);
            self::assertSame('abstained', $result['status']);
            self::assertSame('no_readable_php_files', $result['reason']);
            self::assertSame([], $result['inspected_files']);
            self::assertSame(3, $result['ignored_paths']);
        } finally {
            unlink($outside);
        }
    }

    public function testInspectedFileLimitIsExplicitAndDoesNotClaimCompleteDiscovery(): void
    {
        $paths = [];
        for ($i = 0; $i < 10; ++$i) {
            $name = 'src/Empty' . $i . '.php';
            file_put_contents($this->root . '/' . $name, '<?php final class Empty' . $i . ' {}');
            $paths[] = $name;
        }

        $result = (new SourceRuleHintProjector($this->root))->project($paths);

        self::assertSame('abstained', $result['status']);
        self::assertSame('no_rules_in_inspected_files', $result['reason']);
        self::assertCount(8, $result['inspected_files']);
        self::assertSame(2, $result['ignored_paths']);
    }
}
