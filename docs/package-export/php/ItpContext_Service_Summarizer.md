---
id: "PHP:ItpContext\\Service\\Summarizer"
title: "ItpContext\\Service\\Summarizer"
source_path: "src/Service/Summarizer.php"
kind: "class"
rule_ids:
  - "ItpContext\\Context\\PackageRules::DegradedDiscovery"
owners:
  - "Team-ItpContext"
refs:
  - "README.md"
  - "src/Service/ContextReader.php"
  - "tests/ContextReaderTest.php"
  - "tests/SummarizerTest.php"
verified_by:
  - "ItpContext\\Tests\\ContextReaderTest"
  - "ItpContext\\Tests\\SummarizerTest"
rule_count: 1
---

# Context: Summarizer

### [INFO] Keep context discovery useful even when source symbols are not autoloadable yet.
- **ID:** `ItpContext\Context\PackageRules::DegradedDiscovery`
- **Why:** Token-derived context lets agents inspect partial, generated or in-progress code without waiting for a clean runtime.
- **Owner:** Team-ItpContext
- **Proof:** ItpContext\Tests\ContextReaderTest, ItpContext\Tests\SummarizerTest
- **Refs:** README.md, src/Service/ContextReader.php, tests/ContextReaderTest.php, tests/SummarizerTest.php
- **When changing:** Context for annotated symbols must still come out when their classes are not autoloadable. Do not "fix" an error by requiring class_exists(), autoloading or Reflection for the annotated source symbol; fall back to the token-derived output. ContextReader skips the definition of an unresolvable rule id instead of throwing; keep it that way.

