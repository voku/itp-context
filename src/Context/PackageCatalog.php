<?php

declare(strict_types=1);

namespace ItpContext\Context;

use ItpContext\Enum\Tier;
use ItpContext\Model\RuleDef;

return [
    'FrameworkAgnostic' => new RuleDef(
        statement: 'Keep the public services framework-agnostic and dependency-light.',
        tier: Tier::Standard,
        owner: 'Team-ItpContext',
        rationale: 'The package stays easy to embed across host projects when core services depend only on PHP and local types.',
        verifiedBy: ['tests/ContextResolverTest.php'],
        refs: ['docs/skills/itp-context.md', 'AGENTS.md'],
    ),
    'CatalogByConvention' => new RuleDef(
        statement: 'Match *Rules.php enums with sibling *Catalog.php files by convention.',
        tier: Tier::Standard,
        owner: 'Team-ItpContext',
        rationale: 'A fixed filename convention keeps rule lookup predictable without extra configuration.',
        verifiedBy: ['tests/ContextResolverTest.php', 'tests/ValidatorTest.php'],
        refs: ['docs/skills/itp-context.md', 'README.md'],
    ),
    'TokenFirstDiscovery' => new RuleDef(
        statement: 'Discover symbols with tokens before reflection.',
        tier: Tier::Standard,
        owner: 'Team-ItpContext',
        rationale: 'Cheap token scanning narrows the work and avoids false positives from non-declaration code such as ::class references.',
        verifiedBy: ['tests/TokenParserTest.php'],
        refs: ['README.md'],
    ),
    'AgentFriendlyMarkdown' => new RuleDef(
        statement: 'Export compact markdown summaries with minimal frontmatter.',
        tier: Tier::Standard,
        owner: 'Team-ItpContext',
        rationale: 'Coding agents need stable structure more than exhaustive metadata, especially in a small package.',
        verifiedBy: ['tests/ContextExporterTest.php'],
        refs: ['docs/package-export/index.md', 'README.md'],
    ),
    'DegradedDiscovery' => new RuleDef(
        statement: 'Keep context discovery useful even when source symbols are not autoloadable yet.',
        tier: Tier::Standard,
        owner: 'Team-ItpContext',
        rationale: 'Token-derived context lets agents inspect partial, generated or in-progress code without waiting for a clean runtime.',
        verifiedBy: ['tests/ContextReaderTest.php', 'tests/SummarizerTest.php'],
        refs: ['README.md'],
    ),
    'DiscoveryMetadata' => new RuleDef(
        statement: 'Export searchable ownership, reference and proof metadata with each context document.',
        tier: Tier::Standard,
        owner: 'Team-ItpContext',
        rationale: 'Agents can rank and query context faster when key retrieval fields are available in frontmatter and index views.',
        verifiedBy: ['tests/ContextExporterTest.php', 'tests/ContextQueryTest.php'],
        refs: ['docs/package-export/index.md', 'README.md'],
    ),
];
