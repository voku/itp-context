# Changelog

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
