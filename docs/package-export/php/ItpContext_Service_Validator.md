---
id: "PHP:ItpContext\\Service\\Validator"
title: "ItpContext\\Service\\Validator"
source_path: "src/Service/Validator.php"
kind: "class"
rule_ids:
  - "ItpContext\\Context\\PackageRules::InlineRuleDefinitions"
owners:
  - "Team-ItpContext"
refs:
  - "README.md"
  - "docs/skills/itp-context.md"
verified_by:
  - "ItpContext\\Tests\\ContextResolverTest"
  - "ItpContext\\Tests\\ValidatorTest"
rule_count: 1
---

# Context: Validator

### [INFO] Keep rule identifiers and definitions together on the enum.
- **ID:** `ItpContext\Context\PackageRules::InlineRuleDefinitions`
- **Why:** Inline RuleDef match arms avoid string-key drift and keep each rule typo-safe at the declaration site.
- **Owner:** Team-ItpContext
- **Proof:** ItpContext\Tests\ContextResolverTest, ItpContext\Tests\ValidatorTest
- **Refs:** docs/skills/itp-context.md, README.md

