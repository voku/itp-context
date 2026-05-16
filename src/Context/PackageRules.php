<?php

declare(strict_types=1);

namespace ItpContext\Context;

use ItpContext\Contract\RuleIdentifier;
use ItpContext\Enum\Tier;
use ItpContext\Model\RuleDef;
use ItpContext\Tests\ContextExporterTest;
use ItpContext\Tests\ContextQueryTest;
use ItpContext\Tests\ContextReaderTest;
use ItpContext\Tests\ContextResolverTest;
use ItpContext\Tests\SummarizerTest;
use ItpContext\Tests\TokenParserTest;
use ItpContext\Tests\ValidatorTest;

enum PackageRules implements RuleIdentifier
{
    case FrameworkAgnostic;
    case InlineRuleDefinitions;
    case TokenFirstDiscovery;
    case AgentFriendlyMarkdown;
    case DegradedDiscovery;
    case DiscoveryMetadata;

    public function getDefinition(): RuleDef
    {
        return match ($this) {
            self::FrameworkAgnostic => new RuleDef(
                statement: 'Keep the public services framework-agnostic and dependency-light.',
                tier: Tier::Standard,
                owner: 'Team-ItpContext',
                rationale: 'The package stays easy to embed across host projects when core services depend only on PHP and local types.',
                verifiedBy: [ContextResolverTest::class],
                refs: ['docs/skills/itp-context.md', 'AGENTS.md'],
            ),
            self::InlineRuleDefinitions => new RuleDef(
                statement: 'Keep rule identifiers and definitions together on the enum.',
                tier: Tier::Standard,
                owner: 'Team-ItpContext',
                rationale: 'Inline RuleDef match arms avoid string-key drift and keep each rule typo-safe at the declaration site.',
                verifiedBy: [ContextResolverTest::class, ValidatorTest::class],
                refs: ['docs/skills/itp-context.md', 'README.md'],
            ),
            self::TokenFirstDiscovery => new RuleDef(
                statement: 'Discover symbols with tokens before reflection.',
                tier: Tier::Standard,
                owner: 'Team-ItpContext',
                rationale: 'Cheap token scanning narrows the work and avoids false positives from non-declaration code such as ::class references.',
                verifiedBy: [TokenParserTest::class],
                refs: ['README.md'],
            ),
            self::AgentFriendlyMarkdown => new RuleDef(
                statement: 'Export compact markdown summaries with minimal frontmatter.',
                tier: Tier::Standard,
                owner: 'Team-ItpContext',
                rationale: 'Coding agents need stable structure more than exhaustive metadata, especially in a small package.',
                verifiedBy: [ContextExporterTest::class],
                refs: ['docs/package-export/index.md', 'README.md'],
            ),
            self::DegradedDiscovery => new RuleDef(
                statement: 'Keep context discovery useful even when source symbols are not autoloadable yet.',
                tier: Tier::Standard,
                owner: 'Team-ItpContext',
                rationale: 'Token-derived context lets agents inspect partial, generated or in-progress code without waiting for a clean runtime.',
                verifiedBy: [ContextReaderTest::class, SummarizerTest::class],
                refs: ['README.md'],
            ),
            self::DiscoveryMetadata => new RuleDef(
                statement: 'Export searchable ownership, reference and proof metadata with each context document.',
                tier: Tier::Standard,
                owner: 'Team-ItpContext',
                rationale: 'Agents can rank and query context faster when key retrieval fields are available in frontmatter and index views.',
                verifiedBy: [ContextExporterTest::class, ContextQueryTest::class],
                refs: ['docs/package-export/index.md', 'README.md'],
            ),
        };
    }
}
