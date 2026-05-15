---
id: "PHP:ItpContext\\Service\\Validator"
title: "ItpContext\\Service\\Validator"
source_path: "src/Service/Validator.php"
kind: "class"
rule_ids:
  - "ItpContext\\Context\\PackageRules::CatalogByConvention"
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

### [INFO] Match *Rules.php enums with sibling *Catalog.php files by convention.
- **ID:** `ItpContext\Context\PackageRules::CatalogByConvention`
- **Why:** A fixed filename convention keeps rule lookup predictable without extra configuration.
- **Owner:** Team-ItpContext
- **Proof:** ItpContext\Tests\ContextResolverTest, ItpContext\Tests\ValidatorTest
- **Refs:** docs/skills/itp-context.md, README.md

