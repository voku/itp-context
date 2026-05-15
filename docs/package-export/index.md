---
title: "Architecture context index"
---

# Architecture context export

Exported context documents for PHP symbols annotated with `#[Rule(...)]`.

- [ItpContext\Service\ContextExporter](php/ItpContext_Service_ContextExporter.md) (class) - `src/Service/ContextExporter.php`
  - rules: `ItpContext\Context\PackageRules::AgentFriendlyMarkdown`, `ItpContext\Context\PackageRules::DiscoveryMetadata`
  - owners: Team-ItpContext
  - refs: README.md, docs/package-export/index.md
  - proof: ItpContext\Tests\ContextExporterTest, ItpContext\Tests\ContextQueryTest
- [ItpContext\Service\ContextQuery](php/ItpContext_Service_ContextQuery.md) (class) - `src/Service/ContextQuery.php`
  - rules: `ItpContext\Context\PackageRules::DiscoveryMetadata`
  - owners: Team-ItpContext
  - refs: README.md, docs/package-export/index.md
  - proof: ItpContext\Tests\ContextExporterTest, ItpContext\Tests\ContextQueryTest
- [ItpContext\Service\ContextReader](php/ItpContext_Service_ContextReader.md) (class) - `src/Service/ContextReader.php`
  - rules: `ItpContext\Context\PackageRules::DegradedDiscovery`
  - owners: Team-ItpContext
  - refs: README.md
  - proof: ItpContext\Tests\ContextReaderTest, ItpContext\Tests\SummarizerTest
- [ItpContext\Service\ContextResolver](php/ItpContext_Service_ContextResolver.md) (class) - `src/Service/ContextResolver.php`
  - rules: `ItpContext\Context\PackageRules::CatalogByConvention`, `ItpContext\Context\PackageRules::FrameworkAgnostic`
  - owners: Team-ItpContext
  - refs: AGENTS.md, README.md, docs/skills/itp-context.md
  - proof: ItpContext\Tests\ContextResolverTest, ItpContext\Tests\ValidatorTest
- [ItpContext\Service\Summarizer](php/ItpContext_Service_Summarizer.md) (class) - `src/Service/Summarizer.php`
  - rules: `ItpContext\Context\PackageRules::DegradedDiscovery`
  - owners: Team-ItpContext
  - refs: README.md
  - proof: ItpContext\Tests\ContextReaderTest, ItpContext\Tests\SummarizerTest
- [ItpContext\Service\TokenParser](php/ItpContext_Service_TokenParser.md) (class) - `src/Service/TokenParser.php`
  - rules: `ItpContext\Context\PackageRules::TokenFirstDiscovery`
  - owners: Team-ItpContext
  - refs: README.md
  - proof: ItpContext\Tests\TokenParserTest
- [ItpContext\Service\Validator](php/ItpContext_Service_Validator.md) (class) - `src/Service/Validator.php`
  - rules: `ItpContext\Context\PackageRules::CatalogByConvention`
  - owners: Team-ItpContext
  - refs: README.md, docs/skills/itp-context.md
  - proof: ItpContext\Tests\ContextResolverTest, ItpContext\Tests\ValidatorTest
