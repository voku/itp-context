<?php

declare(strict_types=1);

namespace ItpContext\Service;

use ItpContext\Attribute\Rule;
use ItpContext\Context\PackageRules;
use RuntimeException;

#[Rule(PackageRules::FrameworkAgnostic)]
#[Rule(PackageRules::InlineRuleDefinitions)]
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
        $lineEnding = $this->detectLineEnding($content);

        if (str_contains($content, 'public function getDefinition(): RuleDef')) {
            $updated = preg_replace(
                '/\R(\s*)public function getDefinition\(\): RuleDef/',
                $lineEnding . "    case {$ruleName};" . $lineEnding . $lineEnding . '$1' . 'public function getDefinition(): RuleDef',
                $content,
                1
            );

            if (is_string($updated)) {
                return $updated;
            }
        }

        return $this->insertBeforeFinalBrace($content, "    case {$ruleName};{$lineEnding}{$lineEnding}", $path);
    }

    private function appendDefinitionMethod(string $content, string $ruleName, string $domain, string $path): string
    {
        $lineEnding = $this->detectLineEnding($content);
        $method = <<<PHP
    public function getDefinition(): RuleDef
    {
        return match (\$this) {
{$this->renderDefinitionArm($ruleName, $domain)}        };
    }

PHP;

        return $this->insertBeforeFinalBrace($content, str_replace("\n", $lineEnding, $method), $path);
    }

    private function appendDefinitionArm(string $content, string $ruleName, string $domain, string $path): string
    {
        $updated = preg_replace(
            '/(return\s+match\s*\(\s*\$this\s*\)\s*\{\R)/',
            '$1' . $this->renderDefinitionArm($ruleName, $domain),
            $content,
            1
        );

        if (!is_string($updated) || $updated === $content) {
            throw new RuntimeException("Malformed enum definition match: {$path}");
        }

        return $updated;
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

        $lineEnding = $this->detectLineEnding($content);
        $updated = preg_replace(
            '/^(namespace [^;]+;\R(?:\R?use [^;]+;\R)*)/m',
            '$1' . "use {$fqcn};{$lineEnding}",
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

        return substr($trimmed, 0, $position) . $insertion . '}' . $this->detectLineEnding($content);
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

    private function detectLineEnding(string $content): string
    {
        return str_contains($content, "\r\n") ? "\r\n" : "\n";
    }

    private function toKebabCase(string $value): string
    {
        $value = preg_replace('/(?<!^)[A-Z]/', '-$0', $value) ?? $value;

        return strtolower($value);
    }
}
