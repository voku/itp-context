---
id: "PHP:ItpContext\\Service\\ContextExporter"
title: "ItpContext\\Service\\ContextExporter"
source_path: "src/Service/ContextExporter.php"
kind: "class"
rule_ids:
  - "ItpContext\\Context\\PackageRules::AgentFriendlyMarkdown"
  - "ItpContext\\Context\\PackageRules::DiscoveryMetadata"
owners:
  - "Team-ItpContext"
refs:
  - "README.md"
  - "docs/package-export/index.md"
verified_by:
  - "ItpContext\\Tests\\ContextExporterTest"
  - "ItpContext\\Tests\\ContextQueryTest"
rule_count: 2
---

# Context: ContextExporter

### [INFO] Export compact markdown summaries with minimal frontmatter.
- **ID:** `ItpContext\Context\PackageRules::AgentFriendlyMarkdown`
- **Why:** Coding agents need stable structure more than exhaustive metadata, especially in a small package.
- **Owner:** Team-ItpContext
- **Proof:** ItpContext\Tests\ContextExporterTest
- **Refs:** docs/package-export/index.md, README.md

### [INFO] Export searchable ownership, reference and proof metadata with each context document.
- **ID:** `ItpContext\Context\PackageRules::DiscoveryMetadata`
- **Why:** Agents can rank and query context faster when key retrieval fields are available in frontmatter and index views.
- **Owner:** Team-ItpContext
- **Proof:** ItpContext\Tests\ContextExporterTest, ItpContext\Tests\ContextQueryTest
- **Refs:** docs/package-export/index.md, README.md

