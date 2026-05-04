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
}
