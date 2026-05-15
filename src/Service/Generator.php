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

        $this->ensureDirectoryExists($baseDir);

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
        $this->ensureFilePathIsReadable($path);

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

        $this->writeFile($path, $content . "\n");
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

        $this->writeFile($path, $newContent);
    }

    private function ensureCatalogExists(string $baseNamespace, string $path): void
    {
        $this->ensureFilePathIsReadable($path);

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

        $this->writeFile($path, $content . "\n");
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
        rationale: 'TODO: Explain why this rule exists.',
        verifiedBy: ['tests/Architecture/{$ruleName}Test.php'],
        refs: ['docs/adr/{$this->toKebabCase($ruleName)}.md'],
    ),
PHP;

        $position = strrpos($content, '];');
        if ($position === false) {
            throw new RuntimeException("Malformed catalog (missing '];'): {$path}");
        }

        $newContent = substr($content, 0, $position) . $entry . "\n" . substr($content, $position);
        $this->writeFile($path, $newContent);
    }

    private function ensureDirectoryExists(string $path): void
    {
        if (file_exists($path) && !is_dir($path)) {
            throw new RuntimeException("Failed to create directory: {$path} (path exists but is not a directory)");
        }

        if (is_dir($path)) {
            return;
        }

        error_clear_last();

        if (!@mkdir($path, 0755, true) && !is_dir($path)) {
            $message = self::formatLastErrorMessage(error_get_last());
            throw new RuntimeException("Failed to create directory: {$path}{$message}");
        }
    }

    private function ensureFilePathIsReadable(string $path): void
    {
        if (file_exists($path) && !is_file($path)) {
            throw new RuntimeException("Failed to read: {$path}");
        }
    }

    private function writeFile(string $path, string $content): void
    {
        error_clear_last();

        if (@file_put_contents($path, $content) === false) {
            $message = self::formatLastErrorMessage(error_get_last());
            throw new RuntimeException("Failed to write: {$path}{$message}");
        }
    }

    private static function formatLastErrorMessage(mixed $detail): string
    {
        if (!is_array($detail)) {
            return '';
        }

        $message = $detail['message'] ?? null;

        return is_string($message) ? ' (' . $message . ')' : '';
    }

    private function toKebabCase(string $value): string
    {
        $value = preg_replace('/(?<!^)[A-Z]/', '-$0', $value) ?? $value;

        return strtolower($value);
    }
}
