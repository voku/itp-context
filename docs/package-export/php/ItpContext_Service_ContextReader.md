---
id: "PHP:ItpContext\\Service\\ContextReader"
title: "ItpContext\\Service\\ContextReader"
source_path: "src/Service/ContextReader.php"
kind: "class"
rule_ids:
  - "ItpContext\\Context\\PackageRules::DegradedDiscovery"
owners:
  - "Team-ItpContext"
refs:
  - "README.md"
verified_by:
  - "ItpContext\\Tests\\ContextReaderTest"
  - "ItpContext\\Tests\\SummarizerTest"
rule_count: 1
---

# Context: ContextReader

### [INFO] Keep context discovery useful even when source symbols are not autoloadable yet.
- **ID:** `ItpContext\Context\PackageRules::DegradedDiscovery`
- **Why:** Token-derived context lets agents inspect partial, generated or in-progress code without waiting for a clean runtime.
- **Owner:** Team-ItpContext
- **Proof:** ItpContext\Tests\ContextReaderTest, ItpContext\Tests\SummarizerTest
- **Refs:** README.md

