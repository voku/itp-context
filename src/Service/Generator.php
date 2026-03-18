<?php

declare(strict_types=1);

namespace ItpContext\Service;

use RuntimeException;

final class Generator
{
    public function handle(?string $domain, ?string $ruleName, ?string $baseDir = null, ?string $baseNamespace = null): void
    {
        if ($domain === null || $domain === '' || $ruleName === null || $ruleName === '') {
            throw new RuntimeException('Missing arguments. Use: <Domain> <RuleName> [base-dir] [base-namespace]');
        }

        $baseDir = $baseDir !== null && $baseDir !== '' ? rtrim($baseDir, '/') : getcwd() . '/src/Context';
        $baseNamespace = $baseNamespace !== null && $baseNamespace !== '' ? trim($baseNamespace, '\\') : 'App\\Context';

        if (!is_dir($baseDir) && !mkdir($baseDir, 0777, true) && !is_dir($baseDir)) {
            throw new RuntimeException("Failed to create directory: {$baseDir}");
        }

        $enumPath = $baseDir . '/' . $domain . 'Rules.php';
        $catalogPath = $baseDir . '/' . $domain . 'Catalog.php';

        $this->ensureEnumExists($domain, $baseNamespace, $enumPath);
        $this->appendEnumCase($enumPath, $ruleName);

        $this->ensureCatalogExists($baseNamespace, $catalogPath);
        $this->appendCatalogEntry($catalogPath, $ruleName, $domain);

        echo "Created rule: {$baseNamespace}\\{$domain}Rules::{$ruleName}\n";
    }

    private function ensureEnumExists(string $domain, string $baseNamespace, string $path): void
    {
        if (file_exists($path)) {
            return;
        }

        $content = <<<PHP
<?php

declare(strict_types=1);

namespace {$baseNamespace};

use ItpContext\Contract\RuleIdentifier;

enum {$domain}Rules implements RuleIdentifier
{
}
PHP;

        file_put_contents($path, $content . "\n");
    }

    private function appendEnumCase(string $path, string $ruleName): void
    {
        $content = file_get_contents($path);
        if ($content === false) {
            throw new RuntimeException("Failed to read: {$path}");
        }

        if (str_contains($content, "case {$ruleName};")) {
            return;
        }

        $newContent = preg_replace('/(\}\s*$)/', "    case {$ruleName};\n$1", $content);
        if ($newContent === null) {
            throw new RuntimeException("Failed to update enum file: {$path}");
        }

        file_put_contents($path, $newContent);
    }

    private function ensureCatalogExists(string $baseNamespace, string $path): void
    {
        if (file_exists($path)) {
            return;
        }

        $content = <<<PHP
<?php

declare(strict_types=1);

namespace {$baseNamespace};

use ItpContext\Enum\Tier;
use ItpContext\Model\RuleDef;

return [
];
PHP;

        file_put_contents($path, $content . "\n");
    }

    private function appendCatalogEntry(string $path, string $ruleName, string $domain): void
    {
        $content = file_get_contents($path);
        if ($content === false) {
            throw new RuntimeException("Failed to read: {$path}");
        }

        if (str_contains($content, "'{$ruleName}' =>")) {
            return;
        }

        $entry = <<<PHP

    '{$ruleName}' => new RuleDef(
        statement: 'TODO: Define rule statement.',
        tier: Tier::Standard,
        owner: 'Team-{$domain}',
    ),
PHP;

        $position = strrpos($content, '];');
        if ($position === false) {
            throw new RuntimeException("Malformed catalog (missing '];'): {$path}");
        }

        $newContent = substr($content, 0, $position) . $entry . "\n" . substr($content, $position);
        file_put_contents($path, $newContent);
    }
}
