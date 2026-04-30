---
id: "PHP:ItpContext\\Service\\TokenParser"
title: "ItpContext\\Service\\TokenParser"
source_path: "src/Service/TokenParser.php"
kind: "class"
rule_ids:
  - "ItpContext\\Context\\PackageRules::TokenFirstDiscovery"
---

# Context: TokenParser

### [INFO] Discover symbols with tokens before reflection.
- **ID:** `ItpContext\Context\PackageRules::TokenFirstDiscovery`
- **Why:** Cheap token scanning narrows the work and avoids false positives from non-declaration code such as ::class references.

