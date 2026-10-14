[![Latest Stable Version](https://poser.pugx.org/voku/itp-context/v/stable)](https://packagist.org/packages/voku/itp-context)
[![Total Downloads](https://poser.pugx.org/voku/itp-context/downloads)](https://packagist.org/packages/voku/itp-context)
[![License](https://poser.pugx.org/voku/itp-context/license)](https://packagist.org/packages/voku/itp-context)

# 🎯 itp-context

A small PHP library for attaching architecture rules to code via PHP attributes and resolving those rules directly from typed enums.

It gives you:
- typed rule identifiers via enums
- repeatable `#[Rule(...)]` attributes for classes, methods and functions
- inline enum rule definitions with ownership, rationale, references and proof metadata
- validation helpers for broken or incomplete rule definitions
- summary output for annotated PHP symbols, including multiple symbols per file
- compact markdown context exports with searchable metadata for coding agents and repository assistants
- a small query helper for searching exported context by rule, owner, proof, refs or free text
- a small generator for bootstrapping new rule enums

## Index

- [Install](#install-via-composer-require)
- [Why?](#why)
- [Usage](#usage)
- [Local Development](#local-development)
- [Project Structure](#project-structure)
- [CLI Tools](#cli-tools)
- [Tests](#tests)
- [License](#license)

## Install via "composer require"

```shell
composer require voku/itp-context
```

## Why?

When architecture guidance only lives in ADRs and wikis, it drifts away from the code that is supposed to follow it.

`itp-context` keeps the rule identifier, definition, and supporting context references together in one typed enum instead of splitting them across sibling catalog files. That gives you a compact way to:
- attach architecture intent to classes, methods and functions
- validate whether enum cases still expose usable definitions
- summarize relevant architecture context for one PHP file

The goal is to add context **without burning tokens**:
- prefer a few broad, high-signal rules over many narrow ones
- annotate central symbols, not every class in the tree
- export compact markdown that is easy for humans and LLMs to scan

## Usage

### 1. Create a rule enum in your project

```php
<?php

declare(strict_types=1);

namespace Acme\Context;

use Acme\Tests\I18nTest;
use ItpContext\Contract\RuleIdentifier;
use ItpContext\Enum\Tier;
use ItpContext\Model\RuleDef;

enum ArchitectureRules implements RuleIdentifier
{
    case ViewAbstraction;
    case I18n;

    public function getDefinition(): RuleDef
    {
        return match ($this) {
            self::ViewAbstraction => new RuleDef(
                statement: 'Use a dedicated view abstraction for rendering.',
                tier: Tier::Standard,
                owner: 'Team-Architecture',
                rationale: 'A dedicated view layer keeps rendering concerns isolated from domain and controller code.',
                refs: ['docs/adr/view-abstraction.md', 'docs/ui/rendering.md'],
                changeGuidance: 'Read docs/adr/view-abstraction.md before changing rendering boundaries.',
            ),
            self::I18n => new RuleDef(
                statement: 'Use locale-aware formatting and translated UI labels.',
                tier: Tier::Standard,
                owner: 'Team-Architecture',
                rationale: 'Locale-aware rendering avoids user-facing regressions once the UI contains translated labels and formatted values.',
                verifiedBy: [I18nTest::class],
                refs: ['docs/adr/i18n.md'],
            ),
        };
    }
}
```

Use `refs` for the context you want nearby: ADRs, docs, design notes, diagrams, tickets or related code.

Optionally set `changeGuidance` on a rule to tell a reader what to do before changing the protected symbol (for example which ADR to read). It is advisory text, shown as `**When changing:**`, and never an enforcement mechanism.

### 2. Annotate your code

Keep annotations selective: tag the classes or methods where architecture context changes decisions, not every file.

```php
<?php

declare(strict_types=1);

namespace Acme\Ui;

use Acme\Context\ArchitectureRules;
use ItpContext\Attribute\Rule;

#[Rule(ArchitectureRules::ViewAbstraction)]
final class DashboardView
{
    #[Rule(ArchitectureRules::I18n)]
    public function render(): string
    {
        return 'ok';
    }
}
```

### 3. Validate the enum definitions

```php
<?php

declare(strict_types=1);

use Acme\Context\ArchitectureRules;
use ItpContext\Service\Validator;

$errors = (new Validator())->validateEnumClass(ArchitectureRules::class);
```

Validation walks every enum case and calls `getDefinition()`, so broken match arms or incomplete inline definitions fail in one place.

### 4. Summarize one file

```php
<?php

declare(strict_types=1);

use ItpContext\Service\Summarizer;

$output = (new Summarizer())->summarize(__DIR__ . '/src/Ui/DashboardView.php');
```

Example output:

```text
# Context: DashboardView

### [INFO] Use a dedicated view abstraction for rendering.
- **ID:** `Acme\Context\ArchitectureRules::ViewAbstraction`
- **Owner:** Team-Architecture
- **Why:** A dedicated view layer keeps rendering concerns isolated from domain and controller code.
- **Refs:** docs/adr/view-abstraction.md, docs/ui/rendering.md
- **When changing:** Keep rendering behind the view abstraction. If you must change how this symbol renders, read docs/adr/view-abstraction.md first and update the ADR together with the code.

## Method: `render`
### [INFO] Use locale-aware formatting and translated UI labels.
- **ID:** `Acme\Context\ArchitectureRules::I18n`
- **Owner:** Team-Architecture
- **Why:** Locale-aware rendering avoids user-facing regressions once the UI contains translated labels and formatted values.
- **Proof:** Acme\Tests\I18nTest
- **Refs:** docs/adr/i18n.md
```

### Detect changes to protected code

```bash
vendor/bin/itp-context-changes --base=origin/main > architecture-notices.json
```

Compares the explicitly chosen, trusted `--base` revision with the working tree (or `--head=<ref>`) and prints advisory notices as JSON. Notices are emitted for modified or removed annotated symbols, removed `#[Rule]` annotations and changed rule definitions (including `changeGuidance`). Annotations and rule definitions come from the base revision, so a change cannot silently remove its own protection. Whitespace and comment changes are ignored. Notice ids are deterministic for a given base, rule, symbol and change. Rule definitions are read statically and need named arguments with string literals (`statement:`, `changeGuidance:`, `refs:`).

Locally, add `--once` to show each notice only once: ids of already shown notices are stored per repository under `$XDG_STATE_HOME` (or `~/.local/state`, override with `--state-dir=`), outside the repository and outside PHPStan's cache. A changed implementation or changed guidance yields a new id and is shown again. When the `CI` environment variable is set, `--once` is ignored and every notice is reported on every run.

The output is the `noticesFile` input of [`voku/phpstan-agent-format`](https://github.com/voku/phpstan-agent-format); neither package depends on the other.

#### Show architecture notices in the PHPStan agent report

Install [`voku/phpstan-agent-format`](https://github.com/voku/phpstan-agent-format) alongside this package in your host project and enable its existing formatter:

```neon
# phpstan.neon, in addition to the project's existing PHPStan config
includes:
    - vendor/voku/phpstan-agent-format/extension.neon

parameters:
    agentFormat:
        noticesFile: .architecture-notices.json
```

Then run both tools from the **host project's** root:

```bash
# Ensure the chosen baseline ref exists and is trusted.
vendor/bin/itp-context-changes --base=origin/main --once > .architecture-notices.json
vendor/bin/phpstan analyse --error-format=agent
```

The first command creates the temporary JSON input. The second emits both ordinary PHPStan findings and advisory change notices in one report. Notices do **not** count as PHPStan errors or change PHPStan's exit code. In CI, omit `--once` and optionally pass `--head=<commit>` to compare two fixed revisions. Fetch the trusted baseline explicitly, and do not commit `.architecture-notices.json`. See the formatter's [advisory notices documentation](https://github.com/voku/phpstan-agent-format#Advisory-notices) for redaction and output formats.

**Limitation:** The formatter reads an existing notices file. It does not run `itp-context-changes` automatically; `--error-format=agent` must be selected for this combined report.

### 5. Export agent-friendly context for a source tree

```php
<?php

declare(strict_types=1);

use ItpContext\Service\ContextExporter;

$report = (new ContextExporter())->export(
    outputDir: __DIR__ . '/var/itp-context',
    sourceDirs: [__DIR__ . '/src'],
    excludePaths: ['vendor', 'tests'],
);
```

This writes:
- `var/itp-context/index.md`
- one markdown file per annotated PHP symbol under `var/itp-context/php/`

The export is intentionally lean:
- annotate only the few symbols that carry important architecture context
- use broad rules that stay stable as the code evolves
- keep frontmatter small: `id`, `title`, `source_path`, `kind` and `rule_ids`

This repository dogfoods that approach with a few high-signal `ItpContext\Context\PackageRules` annotations on core services, and a committed self-export snapshot lives under `docs/package-export/`.

That `docs/package-export/` tree is meant to be a ready-made reference for coding agents: it shows the compact export shape, the searchable metadata (`owners`, `refs`, `verified_by`, `annotated_methods`) and the level of abstraction that keeps context useful without wasting tokens.

## Local Development

If you want to test the package before publishing it, use a Composer path repository in a separate project:

```json
{
    "require": {
        "voku/itp-context": "*@dev"
    },
    "repositories": [
        {
            "type": "path",
            "url": "../itp-context",
            "options": {
                "symlink": false
            }
        }
    ],
    "minimum-stability": "dev",
    "prefer-stable": true
}
```

Then install it via Composer:

```shell
composer install
```

After that the package CLIs are available in the consumer project via:

```shell
vendor/bin/itp-context-validate 'Acme\Context\ArchitectureRules'
vendor/bin/itp-context-summarize src/Ui/DashboardView.php
vendor/bin/itp-context-export var/itp-context src --exclude=vendor --exclude=tests
vendor/bin/itp-context-query var/itp-context --rule-id='Acme\Context\ArchitectureRules::I18n'
```

## Project Structure

This package only contains generic framework code under the `ItpContext\\` namespace.

Your project-specific files stay in your own codebase, for example:
- `src/Context/ArchitectureRules.php`

A minimal example project is included under `examples/basic-domain`, and the repository's self-export snapshot lives under `docs/package-export`.

The example project also includes sample context docs under `examples/basic-domain/docs/`, including ADR-style notes referenced from the enum definitions.

## Portable agent skills

This repository keeps one shared, repo-owned coding-agent skill under `docs/skills/itp-context.md`.

Agent-specific entrypoints stay thin and point back to that file:
- `AGENTS.md`
- `.github/copilot-instructions.md`
- `CODEX.md`
- `CLAUDE.md`
- `GEMINI.md`

That keeps the actual guidance in one place while still exposing it to different agent runtimes.

## CLI Tools

The package ships with six small CLI helpers.

### `itp-context-summarize`

```shell
vendor/bin/itp-context-summarize path/to/src/DashboardView.php
```

### `itp-context-validate`

```shell
vendor/bin/itp-context-validate 'Acme\Context\ArchitectureRules'
```

### `itp-context-generate`

```shell
vendor/bin/itp-context-generate Architecture SecurityBoundary src/Context Acme\\Context
```

This creates or extends:
- `src/Context/ArchitectureRules.php`

The generator only updates the enum: it adds the new case plus a matching `getDefinition()` arm and seeds `statement`, `owner`, `rationale`, `verifiedBy`, and `refs` placeholders so new rules start with a fuller definition.

### `itp-context-export`

```shell
vendor/bin/itp-context-export var/itp-context src --exclude=vendor --exclude=tests
```

The export contains:
- `index.md` with an overview of all exported symbols
- one markdown file per annotated PHP symbol under `php/`
- compact frontmatter fields for `id`, `title`, `source_path`, `kind`, `rule_ids`, `owners`, `refs`, `verified_by`, `annotated_methods` and `rule_count`

### `itp-context-changes`

```shell
vendor/bin/itp-context-changes --base=origin/main --once > .architecture-notices.json
```

Generates advisory JSON notices for changes to annotated symbols and rule definitions relative to an explicitly chosen Git baseline. Run it before the optional PHPStan agent formatter. The `--once` deduplication is local-only; CI always reports the notices deterministically.

### `itp-context-query`

```shell
vendor/bin/itp-context-query var/itp-context --rule-id='Acme\Context\ArchitectureRules::I18n'
vendor/bin/itp-context-query var/itp-context --owner='Team-Architecture'
vendor/bin/itp-context-query var/itp-context --text='i18n'
```

For small libraries, prefer a tiny export with a few high-value symbols over exhaustive annotation. The goal is context density, not full documentation coverage.

## Tests

```shell
composer phpstan
composer test
# strict mutation run with uncovered code and example sources included
composer infection
```

There is also a package smoke check in `tests/smoke/package_smoke.php`.

## License

MIT
