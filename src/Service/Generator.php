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
        $this->ensureEnumExists($domain, $ruleName, $baseNamespace, $enumPath);

        echo "Created rule: {$baseNamespace}\\{$domain}Rules::{$ruleName}\n";
    }

    private function ensureEnumExists(string $domain, string $ruleName, string $baseNamespace, string $path): void
    {
        $this->ensureFilePathIsReadable($path);

        if (!file_exists($path)) {
            $this->writeFile($path, $this->renderEnum($domain, $ruleName, $baseNamespace));
            return;
        }

        $content = file_get_contents($path);
        if ($content === false) {
            throw new RuntimeException("Failed to read: {$path}");
        }

        $updated = $this->ensureUseStatement($content, 'ItpContext\\Enum\\Tier');
        $updated = $this->ensureUseStatement($updated, 'ItpContext\\Model\\RuleDef');

        if (!str_contains($updated, "case {$ruleName};")) {
            $updated = $this->appendEnumCase($updated, $ruleName, $path);
        }

        if (!str_contains($updated, 'public function getDefinition(): RuleDef')) {
            $updated = $this->appendDefinitionMethod($updated, $ruleName, $domain, $path);
        } elseif (!str_contains($updated, "self::{$ruleName} =>")) {
            $updated = $this->appendDefinitionArm($updated, $ruleName, $domain, $path);
        }

        $this->writeFile($path, $updated);
    }

    private function renderEnum(string $domain, string $ruleName, string $baseNamespace): string
    {
        $arm = $this->renderDefinitionArm($ruleName, $domain);

        return <<<PHP
<?php

declare(strict_types=1);

namespace {$baseNamespace};

use ItpContext\Contract\RuleIdentifier;
use ItpContext\Enum\Tier;
use ItpContext\Model\RuleDef;

enum {$domain}Rules implements RuleIdentifier
{
    case {$ruleName};

    public function getDefinition(): RuleDef
    {
        return match (\$this) {
{$arm}        };
    }
}
PHP;
    }

    private function appendEnumCase(string $content, string $ruleName, string $path): string
    {
        if (str_contains($content, 'public function getDefinition(): RuleDef')) {
            $updated = preg_replace(
                '/\n(\s*)public function getDefinition\(\): RuleDef/',
                "\n    case {$ruleName};\n\n$1public function getDefinition(): RuleDef",
                $content,
                1
            );

            if (is_string($updated)) {
                return $updated;
            }
        }

        return $this->insertBeforeFinalBrace($content, "    case {$ruleName};\n\n", $path);
    }

    private function appendDefinitionMethod(string $content, string $ruleName, string $domain, string $path): string
    {
        $method = <<<PHP
    public function getDefinition(): RuleDef
    {
        return match (\$this) {
{$this->renderDefinitionArm($ruleName, $domain)}        };
    }

PHP;

        return $this->insertBeforeFinalBrace($content, $method, $path);
    }

    private function appendDefinitionArm(string $content, string $ruleName, string $domain, string $path): string
    {
        $needle = "return match (\$this) {\n";
        $position = strpos($content, $needle);
        if ($position === false) {
            throw new RuntimeException("Malformed enum definition match: {$path}");
        }

        $insertAt = $position + strlen($needle);

        return substr($content, 0, $insertAt)
            . $this->renderDefinitionArm($ruleName, $domain)
            . substr($content, $insertAt);
    }

    private function renderDefinitionArm(string $ruleName, string $domain): string
    {
        $kebab = $this->toKebabCase($ruleName);

        return <<<PHP
            self::{$ruleName} => new RuleDef(
                statement: 'TODO: Define rule statement.',
                tier: Tier::Standard,
                owner: 'Team-{$domain}',
                rationale: 'TODO: Explain why this rule exists.',
                verifiedBy: ['tests/Architecture/{$ruleName}Test.php'],
                refs: ['docs/adr/{$kebab}.md'],
            ),
PHP;
    }

    private function ensureUseStatement(string $content, string $fqcn): string
    {
        if (str_contains($content, "use {$fqcn};")) {
            return $content;
        }

        $updated = preg_replace(
            '/^(namespace [^;]+;\n(?:\n?use [^;]+;\n)*)/m',
            "$1use {$fqcn};\n",
            $content,
            1
        );

        if (!is_string($updated)) {
            throw new RuntimeException("Failed to update enum file imports for {$fqcn}.");
        }

        return $updated;
    }

    private function insertBeforeFinalBrace(string $content, string $insertion, string $path): string
    {
        $trimmed = rtrim($content);
        $position = strrpos($trimmed, '}');
        if ($position === false) {
            throw new RuntimeException("Malformed enum (missing closing brace): {$path}");
        }

        return substr($trimmed, 0, $position) . $insertion . "}\n";
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
