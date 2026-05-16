---
id: "PHP:ItpContext\\Service\\Generator"
title: "ItpContext\\Service\\Generator"
source_path: "src/Service/Generator.php"
kind: "class"
rule_ids:
  - "ItpContext\\Context\\PackageRules::FrameworkAgnostic"
  - "ItpContext\\Context\\PackageRules::InlineRuleDefinitions"
owners:
  - "Team-ItpContext"
refs:
  - "AGENTS.md"
  - "README.md"
  - "docs/skills/itp-context.md"
verified_by:
  - "ItpContext\\Tests\\ContextResolverTest"
  - "ItpContext\\Tests\\ValidatorTest"
rule_count: 2
---

# Context: Generator

### [INFO] Keep the public services framework-agnostic and dependency-light.
- **ID:** `ItpContext\Context\PackageRules::FrameworkAgnostic`
- **Why:** The package stays easy to embed across host projects when core services depend only on PHP and local types.
- **Owner:** Team-ItpContext
- **Proof:** ItpContext\Tests\ContextResolverTest
- **Refs:** docs/skills/itp-context.md, AGENTS.md

### [INFO] Keep rule identifiers and definitions together on the enum.
- **ID:** `ItpContext\Context\PackageRules::InlineRuleDefinitions`
- **Why:** Inline RuleDef match arms avoid string-key drift and keep each rule typo-safe at the declaration site.
- **Owner:** Team-ItpContext
- **Proof:** ItpContext\Tests\ContextResolverTest, ItpContext\Tests\ValidatorTest
- **Refs:** docs/skills/itp-context.md, README.md

