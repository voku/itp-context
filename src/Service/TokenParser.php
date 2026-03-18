<?php

declare(strict_types=1);

namespace ItpContext\Service;

final class TokenParser
{
    public function getFirstSymbolFromFile(string $filePath): ?ParsedSymbol
    {
        $code = file_get_contents($filePath);
        if ($code === false) {
            return null;
        }

        $tokens = token_get_all($code);
        $namespace = '';

        foreach ($tokens as $index => $token) {
            if (is_array($token) && $token[0] === T_NAMESPACE) {
                [$namespace, $index] = $this->parseNamespace($tokens, $index);
                continue;
            }

            if (!is_array($token) || !in_array($token[0], [T_CLASS, T_INTERFACE, T_TRAIT, T_ENUM], true)) {
                continue;
            }

            $kind = match ($token[0]) {
                T_CLASS => 'class',
                T_INTERFACE => 'interface',
                T_TRAIT => 'trait',
                T_ENUM => 'enum',
            };

            if ($kind === 'class' && $this->isAnonymousClass($tokens, $index)) {
                continue;
            }

            $name = $this->parseDeclarationName($tokens, $index);
            if ($name === null) {
                continue;
            }

            $fqcn = $namespace !== '' ? $namespace . '\\' . $name : $name;
            /** @var class-string $fqcn */

            return new ParsedSymbol($kind, $fqcn);
        }

        return null;
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

            if (is_string($token) && ($token === ';' || $token === '{')) {
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
}
