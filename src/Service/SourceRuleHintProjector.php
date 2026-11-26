<?php

declare(strict_types=1);

namespace ItpContext\Service;

use Throwable;

/**
 * Bounded syntax-only architecture identifiers for selected PHP source files.
 *
 * Project rule definitions must never be autoloaded during discovery. A rule
 * identifier points the host to the owning enum, where references to tests and
 * ADRs can be inspected before proposing a scope change.
 */
final readonly class SourceRuleHintProjector
{
    private const int MAX_FILES = 8;
    private const int MAX_RULES = 16;
    private const int MAX_SOURCE_BYTES = 1048576;

    public function __construct(private string $rootPath)
    {
    }

    /**
     * @param list<string> $paths repository-relative paths, not approved edit scope
     * @return array<string, mixed>
     */
    public function project(array $paths): array
    {
        $root = realpath($this->rootPath);
        if ($root === false || !is_dir($root)) {
            return $this->abstained('project_root_unavailable');
        }

        $parser = new TokenParser();
        /** @var array<string, array{subjects: array<string, true>, files: array<string, true>}> $byRule */
        $byRule = [];
        $inspected = [];
        $ignored = 0;

        foreach (array_unique($paths) as $path) {
            if (count($inspected) >= self::MAX_FILES) {
                ++$ignored;
                continue;
            }
            $file = $this->safePhpFile($root, $path);
            if ($file === null) {
                ++$ignored;
                continue;
            }

            $size = filesize($file);
            if ($size === false || $size > self::MAX_SOURCE_BYTES) {
                ++$ignored;
                continue;
            }
            $before = hash_file('sha256', $file);
            if ($before === false) {
                return $this->abstained('source_unreadable');
            }
            try {
                $targets = $parser->getRuleTargetsFromFile($file);
            } catch (Throwable) {
                return $this->abstained('token_scan_failed');
            }
            $after = hash_file('sha256', $file);
            if ($after === false || !hash_equals($before, $after)) {
                return $this->abstained('source_changed_during_scan');
            }

            $inspected[] = ['path' => $path, 'sha256' => 'sha256:' . $after];
            foreach ($targets as $target) {
                foreach ($target->ruleIds as $ruleId) {
                    $byRule[$ruleId]['subjects'][$target->fqcn] = true;
                    $byRule[$ruleId]['files'][$path] = true;
                }
            }
        }

        if ($byRule === []) {
            return $this->abstained($inspected === [] ? 'no_readable_php_files' : 'no_rules_in_inspected_files', [
                'inspected_files' => $inspected,
                'ignored_paths' => $ignored,
            ]);
        }

        ksort($byRule);
        $omittedRules = max(0, count($byRule) - self::MAX_RULES);
        $rules = [];
        foreach (array_slice($byRule, 0, self::MAX_RULES, true) as $id => $record) {
            $subjects = array_keys($record['subjects']);
            $files = array_keys($record['files']);
            sort($subjects);
            sort($files);
            $rules[] = [
                'rule_id' => $id,
                'subjects' => $subjects,
                'files' => $files,
                'evidence' => 'source_attribute_only',
            ];
        }

        return [
            'owner' => 'itp-context',
            'status' => 'ready',
            'authority' => 'none',
            'resolution' => 'syntax_only',
            'rules' => $rules,
            'inspected_files' => $inspected,
            'ignored_paths' => $ignored,
            'omitted_rules' => $omittedRules,
        ];
    }

    private function safePhpFile(string $root, string $relativePath): ?string
    {
        if ($relativePath === ''
            || str_starts_with($relativePath, '/')
            || str_contains($relativePath, "\0")
            || str_contains($relativePath, '\\')
            || in_array('.', explode('/', $relativePath), true)
            || in_array('..', explode('/', $relativePath), true)
            || preg_match('/^[A-Za-z]:/', $relativePath) === 1
            || preg_match('/\.(?:php|phtml)$/i', $relativePath) !== 1
        ) {
            return null;
        }

        $resolved = realpath($root . DIRECTORY_SEPARATOR . $relativePath);
        if ($resolved === false
            || !str_starts_with($resolved, $root . DIRECTORY_SEPARATOR)
            || !is_file($resolved)
            || !is_readable($resolved)
        ) {
            return null;
        }

        return $resolved;
    }

    /**
     * @param array<string, mixed> $extra
     * @return array<string, mixed>
     */
    private function abstained(string $reason, array $extra = []): array
    {
        return [
            'owner' => 'itp-context',
            'status' => 'abstained',
            'authority' => 'none',
            'resolution' => 'syntax_only',
            'reason' => $reason,
            ...$extra,
        ];
    }
}
