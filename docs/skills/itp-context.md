# itp-context skill

Use this package to keep architecture context close to PHP code in a compact, typed form that humans and coding agents can both scan quickly.

## Core model

- Define a project-owned enum that implements `ItpContext\Contract\RuleIdentifier`.
- Add the matching `*Catalog.php` file next to the enum.
- Attach `#[ItpContext\Attribute\Rule(...)]` to the few classes, methods or functions where architecture context changes implementation choices.
- Keep rules broad, stable and high-signal instead of tagging every symbol.

## What to reach for

- `ItpContext\Service\Validator` checks that the enum and sibling catalog still match.
- `ItpContext\Service\Summarizer` renders the relevant rules for one PHP file.
- `ItpContext\Service\ContextExporter` writes a compact markdown tree for annotated symbols.
- `ItpContext\Service\ContextQuery` searches exported markdown context by rule IDs, owners, refs, proof and free text.
- The CLI wrappers in `bin/` are thin entrypoints around those services.

## Authoring guidance

- Keep host-project enums and catalogs in the host project, not inside this package's `src/`.
- Use `refs` for nearby context such as ADRs, docs, diagrams, tickets or related code.
- Use `verifiedBy` when a test or proof artifact should stay near the rule definition.
- Prefer small stable rule catalogs over exhaustive documentation dumps.

## Working examples

- `examples/basic-domain/` shows a minimal consumer project with a rule enum, catalog, refs and annotations.
- `docs/package-export/` is this repository's committed self-export and the canonical example of compact agent-facing markdown output.

## In this repository

- Public APIs are typed and conservative.
- CLI tools stay thin and framework-agnostic.
- Update docs and tests for public API changes.
- Update `CHANGELOG.md` for released changes.
