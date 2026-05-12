<?php

declare(strict_types=1);

namespace ItpContext\Service;

use ItpContext\Attribute\Rule;
use ItpContext\Context\PackageRules;
use ItpContext\Model\RuleTarget;

#[Rule(PackageRules::TokenFirstDiscovery)]
final class TokenParser
{
    public function getFirstSymbolFromFile(string $filePath): ?ParsedSymbol
    {
        $symbols = $this->getSymbolsFromFile($filePath);

        return $symbols[0] ?? null;
    }

    /**
     * @return list<ParsedSymbol>
     */
    public function getSymbolsFromFile(string $filePath): array
    {
        return $this->scanFile($filePath)['symbols'];
    }

    /**
     * @return list<RuleTarget>
     */
    public function getRuleTargetsFromFile(string $filePath): array
    {
        return $this->scanFile($filePath)['targets'];
    }

    /**
     * @return array{symbols:list<ParsedSymbol>, targets:list<RuleTarget>}
     */
    private function scanFile(string $filePath): array
    {
        if (!is_file($filePath) || !is_readable($filePath)) {
            return ['symbols' => [], 'targets' => []];
        }

        $code = file_get_contents($filePath);
        if ($code === false) {
            return ['symbols' => [], 'targets' => []];
        }

        $tokens = token_get_all($code);
        $namespace = '';
        $aliases = [];
        $symbols = [];
        $targets = [];
        $pendingRuleIds = [];
        $braceDepth = 0;
        $currentClass = null;
        $pendingClass = null;
        $count = count($tokens);

        for ($index = 0; $index < $count; $index++) {
            $token = $tokens[$index];

            if ($token === '{') {
                $braceDepth++;

                if (is_array($pendingClass)) {
                    $pendingClass['brace_depth'] = $braceDepth;
                    $currentClass = $pendingClass;
                    $pendingClass = null;
                }

                continue;
            }

            if ($token === '}') {
                if (is_array($currentClass) && $braceDepth === $currentClass['brace_depth']) {
                    $currentClass = null;
                }

                $braceDepth = max(0, $braceDepth - 1);
                $pendingRuleIds = [];
                continue;
            }

            if ($token === ';') {
                $pendingRuleIds = [];
                $pendingClass = null;
                continue;
            }

            if (!is_array($token)) {
                continue;
            }

            if ($token[0] === T_NAMESPACE) {
                [$namespace, $index] = $this->parseNamespace($tokens, $index);
                continue;
            }

            if ($token[0] === T_USE && $currentClass === null && !$this->isClosureUse($tokens, $index)) {
                [$imports, $index] = $this->parseUseStatement($tokens, $index);
                $aliases = array_replace($aliases, $imports);
                continue;
            }

            if ($token[0] === T_ATTRIBUTE) {
                [$ruleIds, $index] = $this->parseRuleAttributeGroup($tokens, $index, $namespace, $aliases);
                $pendingRuleIds = array_values(array_unique(array_merge($pendingRuleIds, $ruleIds)));
                continue;
            }

            if (in_array($token[0], [T_CLASS, T_INTERFACE, T_TRAIT, T_ENUM], true)) {
                $kind = match ($token[0]) {
                    T_CLASS => 'class',
                    T_INTERFACE => 'interface',
                    T_TRAIT => 'trait',
                    T_ENUM => 'enum',
                };

                if (
                    $kind === 'class'
                    &&
                    (
                        $this->isAnonymousClass($tokens, $index)
                        ||
                        $this->isClassConstantReference($tokens, $index)
                    )
                ) {
                    $pendingRuleIds = [];
                    continue;
                }

                $name = $this->parseDeclarationName($tokens, $index);
                if ($name === null) {
                    $pendingRuleIds = [];
                    continue;
                }

                $fqcn = $namespace !== '' ? $namespace . '\\' . $name : $name;
                $symbols[] = new ParsedSymbol($kind, $fqcn);
                $targets[] = new RuleTarget(
                    kind: $kind,
                    name: $name,
                    fqcn: $fqcn,
                    ruleIds: $this->uniqueSorted($pendingRuleIds),
                );
                $pendingClass = [
                    'fqcn' => $fqcn,
                    'brace_depth' => null,
                ];
                $pendingRuleIds = [];
                continue;
            }

            if ($token[0] === T_FUNCTION) {
                $name = $this->parseDeclarationName($tokens, $index);
                if ($name === null) {
                    $pendingRuleIds = [];
                    continue;
                }

                if (is_array($currentClass)) {
                    $targets[] = new RuleTarget(
                        kind: 'method',
                        name: $name,
                        fqcn: $currentClass['fqcn'] . '::' . $name,
                        ownerFqcn: $currentClass['fqcn'],
                        ruleIds: $this->uniqueSorted($pendingRuleIds),
                    );
                    $pendingRuleIds = [];
                    continue;
                }

                $fqcn = $namespace !== '' ? $namespace . '\\' . $name : $name;
                $symbols[] = new ParsedSymbol('function', $fqcn);
                $targets[] = new RuleTarget(
                    kind: 'function',
                    name: $name,
                    fqcn: $fqcn,
                    ruleIds: $this->uniqueSorted($pendingRuleIds),
                );
                $pendingRuleIds = [];
                continue;
            }

            if (in_array($token[0], [T_VARIABLE, T_CONST, T_CASE], true)) {
                $pendingRuleIds = [];
            }
        }

        return [
            'symbols' => $symbols,
            'targets' => $targets,
        ];
    }

    /**
     * @param array<int, array{0:int,1:string,2?:int}|string> $tokens
     * @return array{0:string,1:int}
     */
    private function parseNamespace(array $tokens, int $index): array
    {
        $namespace = '';
        $count = count($tokens);

        for ($cursor = $index + 1; $cursor < $count; $cursor++) {
            $token = $tokens[$cursor];

            if ($token === ';' || $token === '{') {
                return [$namespace, $cursor];
            }

            if (!is_array($token)) {
                continue;
            }

            if ($token[0] === T_STRING || $token[0] === T_NAME_QUALIFIED) {
                $namespace .= $token[1];
                continue;
            }

            if ($token[0] === T_NS_SEPARATOR) {
                $namespace .= '\\';
            }
        }

        return [$namespace, $index];
    }

    /**
     * @param array<int, array{0:int,1:string,2?:int}|string> $tokens
     */
    private function parseDeclarationName(array $tokens, int $index): ?string
    {
        $count = count($tokens);

        for ($cursor = $index + 1; $cursor < $count; $cursor++) {
            $token = $tokens[$cursor];
            if (is_array($token) && $token[0] === T_STRING) {
                return $token[1];
            }
        }

        return null;
    }

    /**
     * @param array<int, array{0:int,1:string,2?:int}|string> $tokens
     * @return array{0: array<string, string>, 1: int}
     */
    private function parseUseStatement(array $tokens, int $index): array
    {
        $buffer = '';
        $count = count($tokens);

        for ($cursor = $index + 1; $cursor < $count; $cursor++) {
            $token = $tokens[$cursor];

            if ($token === ';') {
                return [$this->extractUseAliases($buffer), $cursor];
            }

            $buffer .= is_array($token) ? $token[1] : $token;
        }

        return [$this->extractUseAliases($buffer), $index];
    }

    /**
     * @param array<int, array{0:int,1:string,2?:int}|string> $tokens
     * @param array<string, string> $aliases
     * @return array{0: list<string>, 1: int}
     */
    private function parseRuleAttributeGroup(array $tokens, int $index, string $namespace, array $aliases): array
    {
        $buffer = '';
        $depth = 1;
        $count = count($tokens);

        for ($cursor = $index + 1; $cursor < $count; $cursor++) {
            $token = $tokens[$cursor];
            $buffer .= is_array($token) ? $token[1] : $token;

            if ($token === '[') {
                $depth++;
                continue;
            }

            if ($token === ']') {
                $depth--;

                if ($depth === 0) {
                    return [$this->extractRuleIds($buffer, $namespace, $aliases), $cursor];
                }
            }
        }

        return [$this->extractRuleIds($buffer, $namespace, $aliases), $index];
    }

    /**
     * @param array<string, string> $aliases
     * @return list<string>
     */
    private function extractRuleIds(string $buffer, string $namespace, array $aliases): array
    {
        $matches = [];
        preg_match_all(
            '/(?:^|,)\s*([\\\\A-Za-z_][\\\\A-Za-z0-9_]*)\s*\(\s*(?:id\s*:\s*)?([\\\\A-Za-z_][\\\\A-Za-z0-9_]*)::([A-Za-z_][A-Za-z0-9_]*)/m',
            $buffer,
            $matches,
            PREG_SET_ORDER
        );

        $ruleIds = [];

        foreach ($matches as $match) {
            $attributeName = $this->resolveName($match[1], $namespace, $aliases);
            if ($attributeName !== Rule::class) {
                continue;
            }

            $ruleIds[] = $this->resolveName($match[2], $namespace, $aliases) . '::' . $match[3];
        }

        return $this->uniqueSorted($ruleIds);
    }

    /**
     * @return array<string, string>
     */
    private function extractUseAliases(string $buffer): array
    {
        $buffer = trim($buffer);
        if ($buffer === '' || str_starts_with($buffer, 'function ') || str_starts_with($buffer, 'const ')) {
            return [];
        }

        $imports = [];
        $groupMatches = [];

        if (preg_match('/^([\\\\A-Za-z_][\\\\A-Za-z0-9_]*)\\\\\{(.+)\}$/', $buffer, $groupMatches) === 1) {
            $prefix = $groupMatches[1];

            foreach (explode(',', $groupMatches[2]) as $entry) {
                $entry = trim($entry);
                if ($entry === '') {
                    continue;
                }

                $imports[$this->aliasFromImport($entry)] = $prefix . '\\' . $this->importTarget($entry);
            }

            return $imports;
        }

        foreach (explode(',', $buffer) as $entry) {
            $entry = trim($entry);
            if ($entry === '' || str_starts_with($entry, 'function ') || str_starts_with($entry, 'const ')) {
                continue;
            }

            $imports[$this->aliasFromImport($entry)] = $this->importTarget($entry);
        }

        return $imports;
    }

    private function aliasFromImport(string $entry): string
    {
        if (preg_match('/^(.+?)\s+as\s+([A-Za-z_][A-Za-z0-9_]*)$/i', $entry, $matches) === 1) {
            return $matches[2];
        }

        $entry = $this->importTarget($entry);
        $position = strrpos($entry, '\\');

        return $position === false ? $entry : substr($entry, $position + 1);
    }

    private function importTarget(string $entry): string
    {
        if (preg_match('/^(.+?)\s+as\s+[A-Za-z_][A-Za-z0-9_]*$/i', $entry, $matches) === 1) {
            return trim($matches[1], '\\ ');
        }

        return trim($entry, '\\ ');
    }

    /**
     * @param array<string, string> $aliases
     */
    private function resolveName(string $name, string $namespace, array $aliases): string
    {
        $name = trim($name);
        if ($name === '') {
            return '';
        }

        if (str_starts_with($name, '\\')) {
            return ltrim($name, '\\');
        }

        $parts = explode('\\', $name);
        $firstSegment = $parts[0];

        if (isset($aliases[$firstSegment])) {
            $suffix = substr($name, strlen($firstSegment));

            return $aliases[$firstSegment] . $suffix;
        }

        return $namespace !== '' ? $namespace . '\\' . $name : $name;
    }

    /**
     * @param array<int, array{0:int,1:string,2?:int}|string> $tokens
     */
    private function isAnonymousClass(array $tokens, int $classTokenIndex): bool
    {
        for ($cursor = $classTokenIndex - 1; $cursor >= 0; $cursor--) {
            $token = $tokens[$cursor];
            if (!is_array($token)) {
                continue;
            }

            if (in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }

            return $token[0] === T_NEW;
        }

        return false;
    }

    /**
     * @param array<int, array{0:int,1:string,2?:int}|string> $tokens
     */
    private function isClassConstantReference(array $tokens, int $classTokenIndex): bool
    {
        for ($cursor = $classTokenIndex - 1; $cursor >= 0; $cursor--) {
            $token = $tokens[$cursor];
            if (!is_array($token)) {
                continue;
            }

            if (in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }

            return $token[0] === T_DOUBLE_COLON;
        }

        return false;
    }

    /**
     * @param array<int, array{0:int,1:string,2?:int}|string> $tokens
     */
    private function isClosureUse(array $tokens, int $useTokenIndex): bool
    {
        for ($cursor = $useTokenIndex - 1; $cursor >= 0; $cursor--) {
            $token = $tokens[$cursor];

            if (!is_array($token)) {
                if ($token === ')') {
                    return true;
                }

                continue;
            }

            if (in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }

            return false;
        }

        return false;
    }

    /**
     * @param list<string> $values
     * @return list<string>
     */
    private function uniqueSorted(array $values): array
    {
        $values = array_values(array_unique(array_filter($values, static fn (string $value): bool => trim($value) !== '')));
        sort($values);

        return $values;
    }
}
