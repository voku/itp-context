---
id: "PHP:ItpContext\\Service\\Summarizer"
title: "ItpContext\\Service\\Summarizer"
source_path: "src/Service/Summarizer.php"
kind: "class"
rule_ids:
  - "ItpContext\\Context\\PackageRules::DegradedDiscovery"
rule_count: 1
---

# Context: Summarizer

### [INFO] Keep context discovery useful even when source symbols are not autoloadable yet.
- **ID:** `ItpContext\Context\PackageRules::DegradedDiscovery`
- **Why:** Token-derived context lets agents inspect partial, generated or in-progress code without waiting for a clean runtime.

