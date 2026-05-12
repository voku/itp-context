---
id: "PHP:ItpContext\\Service\\ContextResolver"
title: "ItpContext\\Service\\ContextResolver"
source_path: "src/Service/ContextResolver.php"
kind: "class"
rule_ids:
  - "ItpContext\\Context\\PackageRules::CatalogByConvention"
  - "ItpContext\\Context\\PackageRules::FrameworkAgnostic"
rule_count: 2
---

# Context: ContextResolver

### [INFO] Match *Rules.php enums with sibling *Catalog.php files by convention.
- **ID:** `ItpContext\Context\PackageRules::CatalogByConvention`
- **Why:** A fixed filename convention keeps rule lookup predictable without extra configuration.

### [INFO] Keep the public services framework-agnostic and dependency-light.
- **ID:** `ItpContext\Context\PackageRules::FrameworkAgnostic`
- **Why:** The package stays easy to embed across host projects when core services depend only on PHP and local types.

