<?php

declare(strict_types=1);

namespace ItpContext\Service;

use ItpContext\Model\RuleArm;

/**
 * Reads rule definitions from source text without loading it, so a trusted
 * baseline revision can be inspected. Only named arguments with string literals
 * are extracted; `fingerprint` still covers the complete arm.
 */
final class RuleArmScanner
{
    public function __construct(
        private TokenParser $parser = new TokenParser(),
    ) {
    }

    /**
     * @return array<string, RuleArm> keyed by rule id (`Enum\Fqcn::Case`)
     */
    public function scan(string $code): array
    {
        if (!str_contains($code, 'RuleDef')) {
            return [];
        }

        $enumFqcn = null;
        foreach ($this->parser->getRuleTargetsFromCode($code) as $target) {
            if ($target->kind === 'enum') {
                $enumFqcn = $target->fqcn;
                break;
            }
        }

        if ($enumFqcn === null) {
            return [];
        }

        $tokens = array_values(array_filter(
            token_get_all($code),
            static fn (array|string $token): bool => !is_array($token) || !in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true),
        ));
        $count = count($tokens);
        // Preserve standalone enum scans without a getDefinition() method.
        [$start, $end] = $this->definitionBody($tokens) ?? [0, $count];
        $arms = [];

        for ($index = $start; $index + 4 < $end; $index++) {
            if (
                !$this->isText($tokens[$index], ['self', 'static'])
                || !$this->isType($tokens[$index + 1], T_DOUBLE_COLON)
                || !$this->isType($tokens[$index + 2], T_STRING)
                || !$this->isType($tokens[$index + 3], T_DOUBLE_ARROW)
            ) {
                continue;
            }

            $case = $this->text($tokens[$index + 2]);
            $armTokens = $this->collectArm($tokens, $index + 4);
            $ruleId = $enumFqcn . '::' . $case;

            if (isset($arms[$ruleId])) {
                continue;
            }

            $arms[$ruleId] = new RuleArm(
                ruleId: $ruleId,
                fingerprint: sha1(implode("\x1f", array_map($this->text(...), $armTokens))),
                statement: $this->namedString($armTokens, 'statement'),
                changeGuidance: $this->namedString($armTokens, 'changeGuidance'),
                refs: $this->namedStringList($armTokens, 'refs'),
            );
        }

        return $arms;
    }

    /**
     * @param list<array{0:int,1:string,2?:int}|string> $tokens
     * @return list<array{0:int,1:string,2?:int}|string>
     */
    private function collectArm(array $tokens, int $start): array
    {
        $arm = [];
        $depth = 0;

        for ($cursor = $start, $count = count($tokens); $cursor < $count; $cursor++) {
            $token = $tokens[$cursor];

            if ($token === '(' || $token === '[' || $token === '{' || (is_array($token) && in_array($token[0], [T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES], true))) {
                $depth++;
            } elseif ($token === ')' || $token === ']' || $token === '}') {
                $depth--;
                if ($depth < 0) {
                    break;
                }
            } elseif ($token === ',' && $depth === 0) {
                break;
            }

            $arm[] = $token;
        }

        return $arm;
    }

    /**
     * Only match arms inside the enum's authoritative getDefinition() method
     * belong to RuleDef. This also supports aliased and fully qualified constructors.
     *
     * @param list<array{0:int,1:string,2?:int}|string> $tokens
     * @return array{int, int}|null first token after opening brace, closing brace index
     */
    private function definitionBody(array $tokens): ?array
    {
        $count = count($tokens);

        for ($index = 0; $index < $count; $index++) {
            if (!$this->isType($tokens[$index], T_FUNCTION)) {
                continue;
            }

            $namePosition = $index + 1;
            if (($tokens[$namePosition] ?? null) === '&') {
                $namePosition++;
            }

            if (
                !isset($tokens[$namePosition])
                || !$this->isType($tokens[$namePosition], T_STRING)
                || $this->text($tokens[$namePosition]) !== 'getDefinition'
            ) {
                continue;
            }

            for ($open = $namePosition + 1; $open < $count; $open++) {
                if ($tokens[$open] === ';') {
                    return null;
                }

                if ($tokens[$open] !== '{') {
                    continue;
                }

                $depth = 1;
                for ($close = $open + 1; $close < $count; $close++) {
                    $token = $tokens[$close];
                    if ($token === '{' || (is_array($token) && in_array($token[0], [T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES], true))) {
                        $depth++;
                    } elseif ($token === '}') {
                        $depth--;
                        if ($depth === 0) {
                            return [$open + 1, $close];
                        }
                    }
                }

                return null;
            }
        }

        return null;
    }

    /**
     * @param list<array{0:int,1:string,2?:int}|string> $arm
     */
    private function namedString(array $arm, string $name): ?string
    {
        $position = $this->findNamedArgument($arm, $name);
        if ($position === null) {
            return null;
        }

        $value = $arm[$position + 2] ?? null;
        $after = $arm[$position + 3] ?? null;
        if (!is_array($value) || $value[0] !== T_CONSTANT_ENCAPSED_STRING || ($after !== ',' && $after !== ')')) {
            return null;
        }

        return $this->decodeString($value[1]);
    }

    /**
     * @param list<array{0:int,1:string,2?:int}|string> $arm
     * @return list<string>
     */
    private function namedStringList(array $arm, string $name): array
    {
        $position = $this->findNamedArgument($arm, $name);
        if ($position === null || ($arm[$position + 2] ?? null) !== '[') {
            return [];
        }

        $values = [];
        for ($cursor = $position + 3, $count = count($arm); $cursor < $count && $arm[$cursor] !== ']'; $cursor++) {
            $token = $arm[$cursor];
            if (is_array($token) && $token[0] === T_CONSTANT_ENCAPSED_STRING) {
                $values[] = $this->decodeString($token[1]);
            }
        }

        return $values;
    }

    /**
     * @param list<array{0:int,1:string,2?:int}|string> $arm
     */
    private function findNamedArgument(array $arm, string $name): ?int
    {
        foreach ($arm as $position => $token) {
            if (is_array($token) && $token[0] === T_STRING && $token[1] === $name && ($arm[$position + 1] ?? null) === ':') {
                return $position;
            }
        }

        return null;
    }

    private function decodeString(string $literal): string
    {
        $body = substr($literal, 1, -1);

        if ($literal[0] === "'") {
            return strtr($body, ["\\\\" => "\\", "\\'" => "'"]);
        }

        return stripcslashes($body);
    }

    /**
     * @param array{0:int,1:string,2?:int}|string $token
     * @param list<string> $texts
     */
    private function isText(array|string $token, array $texts): bool
    {
        return in_array($this->text($token), $texts, true);
    }

    /**
     * @param array{0:int,1:string,2?:int}|string $token
     */
    private function isType(array|string $token, int $type): bool
    {
        return is_array($token) && $token[0] === $type;
    }

    /**
     * @param array{0:int,1:string,2?:int}|string $token
     */
    private function text(array|string $token): string
    {
        return is_array($token) ? $token[1] : $token;
    }
}
