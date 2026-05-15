# AGENTS.md

## Package Goal

This package contains only framework-agnostic architecture context tooling under the `ItpContext\\` namespace.

Portable coding-agent usage guidance lives in `docs/skills/itp-context.md`. Keep agent-specific entrypoint files as thin pointers to that shared repo-owned document.

## Boundaries

- Keep host-project rule enums out of `src/`.
- Do not introduce dependencies on IT-Portal helper functions or globals.
- Prefer standard PHP library functions and narrow public APIs.
- Keep CLI tools as thin wrappers around the service classes.

## Repository Style

- Follow the compact `voku/*` package style for metadata, docs and layout.
- Keep public APIs typed and conservative.
- Prefer small standalone classes over framework-specific abstractions.
- Keep README examples runnable and aligned with the shipped example project.

## Publishing Rules

- Preserve backward compatibility once the package is published.
- Keep examples under `examples/`, not in `src/`.
- Add docs and tests for every public API change.
- Update `CHANGELOG.md` for every released change.
