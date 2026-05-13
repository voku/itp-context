---
id: "PHP:ItpContext\\Service\\ContextExporter"
title: "ItpContext\\Service\\ContextExporter"
source_path: "src/Service/ContextExporter.php"
kind: "class"
rule_ids:
  - "ItpContext\\Context\\PackageRules::AgentFriendlyMarkdown"
  - "ItpContext\\Context\\PackageRules::DiscoveryMetadata"
rule_count: 2
---

# Context: ContextExporter

### [INFO] Export compact markdown summaries with minimal frontmatter.
- **ID:** `ItpContext\Context\PackageRules::AgentFriendlyMarkdown`
- **Why:** Coding agents need stable structure more than exhaustive metadata, especially in a small package.

### [INFO] Export searchable ownership, reference and proof metadata with each context document.
- **ID:** `ItpContext\Context\PackageRules::DiscoveryMetadata`
- **Why:** Agents can rank and query context faster when key retrieval fields are available in frontmatter and index views.

