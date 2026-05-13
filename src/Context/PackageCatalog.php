<?php

declare(strict_types=1);

namespace ItpContext\Context;

use ItpContext\Enum\Tier;
use ItpContext\Model\RuleDef;

return [
    'FrameworkAgnostic' => new RuleDef(
        statement: 'Keep the public services framework-agnostic and dependency-light.',
        tier: Tier::Standard,
        rationale: 'The package stays easy to embed across host projects when core services depend only on PHP and local types.',
    ),
    'CatalogByConvention' => new RuleDef(
        statement: 'Match *Rules.php enums with sibling *Catalog.php files by convention.',
        tier: Tier::Standard,
        rationale: 'A fixed filename convention keeps rule lookup predictable without extra configuration.',
    ),
    'TokenFirstDiscovery' => new RuleDef(
        statement: 'Discover symbols with tokens before reflection.',
        tier: Tier::Standard,
        rationale: 'Cheap token scanning narrows the work and avoids false positives from non-declaration code such as ::class references.',
    ),
    'AgentFriendlyMarkdown' => new RuleDef(
        statement: 'Export compact markdown summaries with minimal frontmatter.',
        tier: Tier::Standard,
        rationale: 'Coding agents need stable structure more than exhaustive metadata, especially in a small package.',
    ),
    'DegradedDiscovery' => new RuleDef(
        statement: 'Keep context discovery useful even when source symbols are not autoloadable yet.',
        tier: Tier::Standard,
        rationale: 'Token-derived context lets agents inspect partial, generated or in-progress code without waiting for a clean runtime.',
    ),
    'DiscoveryMetadata' => new RuleDef(
        statement: 'Export searchable ownership, reference and proof metadata with each context document.',
        tier: Tier::Standard,
        rationale: 'Agents can rank and query context faster when key retrieval fields are available in frontmatter and index views.',
    ),
];
