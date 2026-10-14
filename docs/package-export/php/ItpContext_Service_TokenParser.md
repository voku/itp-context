---
id: "PHP:ItpContext\\Service\\TokenParser"
title: "ItpContext\\Service\\TokenParser"
source_path: "src/Service/TokenParser.php"
kind: "class"
rule_ids:
  - "ItpContext\\Context\\PackageRules::TokenFirstDiscovery"
owners:
  - "Team-ItpContext"
refs:
  - "README.md"
  - "src/Service/TokenParser.php"
  - "tests/TokenParserTest.php"
verified_by:
  - "ItpContext\\Tests\\TokenParserTest"
rule_count: 1
---

# Context: TokenParser

### [INFO] Discover symbols with tokens before reflection.
- **ID:** `ItpContext\Context\PackageRules::TokenFirstDiscovery`
- **Why:** Cheap token scanning narrows the work and avoids false positives from non-declaration code such as ::class references.
- **Owner:** Team-ItpContext
- **Proof:** ItpContext\Tests\TokenParserTest
- **Refs:** README.md, src/Service/TokenParser.php, tests/TokenParserTest.php
- **When changing:** Fix lookup and type errors in the token scan itself. Do not replace token scanning with Reflection or autoloading: symbols must be discoverable from source text without loading the code. Check ::class references and anonymous classes against tests/TokenParserTest.php.

