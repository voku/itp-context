<?php

declare(strict_types=1);

namespace ItpContext\Tests;

use ItpContext\Service\Frontmatter;
use PHPUnit\Framework\TestCase;

final class FrontmatterTest extends TestCase
{
    public function testRenderAndParseRoundTripMetadata(): void
    {
        $content = Frontmatter::render(
            [
                'id' => 'PHP:ItpContextExample\\DashboardView',
                'title' => 'Dashboard/View',
                'rule_count' => 2,
                'strict' => true,
                'rule_ids' => [
                    'ItpContextExample\\Context\\ArchitectureRules::ViewAbstraction',
                    'ItpContextExample\\Context\\ArchitectureRules::I18n',
                ],
            ],
            "# Context: DashboardView\n"
        );

        $parsed = Frontmatter::parse($content);

        self::assertSame('PHP:ItpContextExample\\DashboardView', $parsed['id']);
        self::assertSame('Dashboard/View', $parsed['title']);
        self::assertSame(2, $parsed['rule_count']);
        self::assertTrue($parsed['strict']);
        self::assertSame(
            [
                'ItpContextExample\\Context\\ArchitectureRules::ViewAbstraction',
                'ItpContextExample\\Context\\ArchitectureRules::I18n',
            ],
            $parsed['rule_ids']
        );
        self::assertSame("# Context: DashboardView\n", $parsed['body']);
    }

    public function testRenderSkipsEmptyKeysAndNonScalarArrayItems(): void
    {
        $content = Frontmatter::render(
            [
                '' => 'skip-me',
                'ignored' => [new \stdClass()],
                'title' => 'Dashboard',
            ],
            'Body'
        );

        self::assertStringNotContainsString("skip-me", $content);
        self::assertStringNotContainsString("ignored:", $content);
        self::assertStringContainsString("title: \"Dashboard\"", $content);
    }

    public function testParseReturnsBodyWhenNoFrontmatterExists(): void
    {
        self::assertSame(
            ['body' => "Just text\n"],
            Frontmatter::parse("Just text\n")
        );
    }

    public function testParseReturnsBodyWhenFrontmatterIsMalformed(): void
    {
        $content = "---\nid: 1\nbody without closing marker";

        self::assertSame(['body' => $content], Frontmatter::parse($content));
    }

    public function testParseSkipsInvalidLinesAndPreservesValidKeys(): void
    {
        $parsed = Frontmatter::parse(<<<'TEXT'
---
id: 1

invalid line
: ignored
strict: false
---
Body
TEXT);

        self::assertSame(1, $parsed['id']);
        self::assertFalse($parsed['strict']);
        self::assertSame('Body', $parsed['body']);
        self::assertArrayNotHasKey('', $parsed);
    }

    public function testRenderWrapsJsonEncodingFailures(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Failed to encode frontmatter string.');

        Frontmatter::render(['title' => "bad\xB1"], 'Body');
    }

    public function testParseWrapsJsonDecodingFailures(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Failed to decode frontmatter string.');

        Frontmatter::parse("---\ntitle: \"\\uZZZZ\"\n---\nBody\n");
    }
}
