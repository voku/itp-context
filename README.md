[![Latest Stable Version](https://poser.pugx.org/voku/itp-context/v/stable)](https://packagist.org/packages/voku/itp-context)
[![Total Downloads](https://poser.pugx.org/voku/itp-context/downloads)](https://packagist.org/packages/voku/itp-context)
[![License](https://poser.pugx.org/voku/itp-context/license)](https://packagist.org/packages/voku/itp-context)

# itp-context

A small PHP library for attaching architecture rules to code via PHP attributes and resolving those rules from a matching catalog.

It gives you:
- typed rule identifiers via enums
- repeatable `#[Rule(...)]` attributes for classes and methods
- rule catalogs with ownership, rationale, references and proof metadata
- validation helpers for stale or orphaned catalog entries
- summary output for annotated PHP symbols
- compact markdown context exports for coding agents and repository assistants
- a small generator for bootstrapping new rule enums and catalogs

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

`itp-context` keeps the rule identifier in the code, the rule definition in a nearby catalog and the proof references in one typed structure. That gives you a compact way to:
- attach architecture intent to classes and methods
- validate whether enum cases and catalog entries still match
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

use ItpContext\Contract\RuleIdentifier;

enum ArchitectureRules implements RuleIdentifier
{
    case ViewAbstraction;
    case I18n;
}
```

### 2. Add the matching catalog

Convention:
- `ArchitectureRules.php` -> `ArchitectureCatalog.php`

```php
<?php

declare(strict_types=1);

namespace Acme\Context;

use ItpContext\Enum\Tier;
use ItpContext\Model\RuleDef;

return [
    'ViewAbstraction' => new RuleDef(
        statement: 'Use a dedicated view abstraction for rendering.',
        tier: Tier::Standard,
        owner: 'Team-Architecture',
        refs: ['docs/adr/view-abstraction.md'],
    ),
    'I18n' => new RuleDef(
        statement: 'Use translated labels and locale-aware formatting.',
        tier: Tier::Standard,
        owner: 'Team-Architecture',
        verifiedBy: ['tests/Unit/I18nTest.php'],
        refs: ['docs/adr/i18n.md'],
    ),
];
```

### 3. Annotate your code

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

### 4. Validate the enum/catalog integrity

```php
<?php

declare(strict_types=1);

use Acme\Context\ArchitectureRules;
use ItpContext\Service\Validator;

$errors = (new Validator())->validateEnumClass(ArchitectureRules::class);
```

### 5. Summarize one file

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
- **Refs:** docs/adr/view-abstraction.md

## Method: `render`
### [INFO] Use translated labels and locale-aware formatting.
- **ID:** `Acme\Context\ArchitectureRules::I18n`
- **Owner:** Team-Architecture
- **Proof:** tests/Unit/I18nTest.php
- **Refs:** docs/adr/i18n.md
```

### 6. Export agent-friendly context for a source tree

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

That `docs/package-export/` tree is meant to be a ready-made reference for coding agents: it shows the compact export shape, the small set of package rules and the level of abstraction that keeps context useful without wasting tokens.

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
```

## Project Structure

This package only contains generic framework code under the `ItpContext\\` namespace.

Your project-specific files stay in your own codebase, for example:
- `src/Context/ArchitectureRules.php`
- `src/Context/ArchitectureCatalog.php`

A minimal example project is included under `examples/basic-domain`, and the repository's self-export snapshot lives under `docs/package-export`.

## CLI Tools

The package ships with four small CLI helpers.

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
- `src/Context/ArchitectureCatalog.php`

### `itp-context-export`

```shell
vendor/bin/itp-context-export var/itp-context src --exclude=vendor --exclude=tests
```

The export contains:
- `index.md` with an overview of all exported symbols
- one markdown file per annotated PHP symbol under `php/`
- compact frontmatter fields for `id`, `title`, `source_path`, `kind` and `rule_ids`

For small libraries, prefer a tiny export with a few high-value symbols over exhaustive annotation. The goal is context density, not full documentation coverage.

## Tests

```shell
composer test
```

There is also a package smoke check in `tests/smoke/package_smoke.php`.

## License

MIT
