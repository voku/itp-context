# Changelog

## Unreleased

- moved rule definitions onto `RuleIdentifier` enums via `getDefinition()` and removed sibling catalog files from the example, generator and resolver flow
- added `ContextReader` and token-based degraded summaries so multi-symbol files, functions and non-autoloadable code still expose context
- added richer export metadata (`owners`, `refs`, `verified_by`, `annotated_methods`, `rule_count`) plus a queryable export index
- added `ContextQuery` and the `itp-context-query` CLI for searching exported context documents
- synced package, example and exported rule metadata with the generator scaffold defaults for owners, refs and proof fields
- expanded package dogfooding annotations and refreshed the committed self-export snapshot
- added a shared `docs/skills/itp-context.md` agent skill plus thin Copilot, Codex, Claude and Gemini entrypoint files
- clarified that `refs` can point to any useful architecture context, including ADRs, docs and design notes
- updated the generator and example inline rule definitions to use ADR-style paths as helpful default references
- refreshed README guidance and example docs to show ADRs as one lightweight way to capture missing context

## 0.2.0

- added `ContextExporter`, `ExportWriter`, `Frontmatter` and `ExportReport` for exporting agent-friendly markdown context files
- added the `itp-context-export` CLI for batch exporting annotated PHP symbol context trees
- improved `TokenParser` so export scans ignore `::class` references instead of treating them as declarations
- expanded PHPUnit and smoke coverage for frontmatter parsing, export filtering and the export CLI flow
- added package-owned `PackageRules` dogfood annotations to a few core library services
- added a committed self-export snapshot under `docs/package-export` and a regression test to keep it in sync
- made `#[Rule]` repeatable so one symbol can carry multiple architecture rules

## 0.1.0

- initial extraction of the generic `ItpContext` framework from IT-Portal
- added rule attribute, enum contract, catalog model and resolver
- added validator, summarizer and generator services
- added CLI wrappers and a minimal example project
- added PHPUnit scaffolding and smoke coverage
