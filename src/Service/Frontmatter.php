<?php

declare(strict_types=1);

namespace ItpContext\Service;

use JsonException;
use RuntimeException;

final class Frontmatter
{
    /**
     * @param array<string, mixed> $meta
     */
    public static function render(array $meta, string $body): string
    {
        $lines = ['---'];

        foreach ($meta as $key => $value) {
            if ($key === '' || $value === null) {
                continue;
            }

            if (is_array($value)) {
                $items = [];

                foreach ($value as $item) {
                    if (is_scalar($item)) {
                        $items[] = $item;
                    }
                }

                if ($items === []) {
                    continue;
                }

                $lines[] = $key . ':';

                foreach ($items as $item) {
                    $lines[] = '  - ' . self::formatScalar($item);
                }

                continue;
            }

            if (is_scalar($value)) {
                $lines[] = $key . ': ' . self::formatScalar($value);
            }
        }

        $lines[] = '---';
        $lines[] = '';
        $lines[] = $body;

        return implode("\n", $lines);
    }

    /**
     * @return array<string, mixed>
     */
    public static function parse(string $content): array
    {
        if (!str_starts_with($content, "---\n") && !str_starts_with($content, "---\r\n")) {
            return ['body' => $content];
        }

        $parts = preg_split('/\R---\R/', $content, 2);
        if (!is_array($parts) || count($parts) !== 2) {
            return ['body' => $content];
        }

        $header = substr($parts[0], 4);
        $body = $parts[1];
        $body = preg_replace('/^\R/', '', $body) ?? $body;

        $meta = [];
        $currentList = null;

        foreach (preg_split('/\R/', trim($header)) ?: [] as $line) {
            if ($line === '') {
                continue;
            }

            if (str_starts_with($line, '  - ') && $currentList !== null) {
                if (!isset($meta[$currentList]) || !is_array($meta[$currentList])) {
                    $meta[$currentList] = [];
                }

                $meta[$currentList][] = self::parseScalar(substr($line, 4));
                continue;
            }

            $currentList = null;

            if (!str_contains($line, ':')) {
                continue;
            }

            [$key, $value] = explode(':', $line, 2);
            $key = trim($key);
            $value = trim($value);

            if ($key === '') {
                continue;
            }

            if ($value === '') {
                $meta[$key] = [];
                $currentList = $key;
                continue;
            }

            $meta[$key] = self::parseScalar($value);
        }

        $meta['body'] = $body;

        return $meta;
    }

    private static function formatScalar(string|int|float|bool $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        try {
            return json_encode(
                str_replace("\n", ' ', $value),
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES
            );
        } catch (JsonException $exception) {
            throw new RuntimeException('Failed to encode frontmatter string.', 0, $exception);
        }
    }

    private static function parseScalar(string $value): string|int|float|bool
    {
        if ($value === 'true') {
            return true;
        }

        if ($value === 'false') {
            return false;
        }

        if (is_numeric($value)) {
            return str_contains($value, '.') ? (float) $value : (int) $value;
        }

        if (str_starts_with($value, '"') && str_ends_with($value, '"')) {
            try {
                $decoded = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException $exception) {
                throw new RuntimeException('Failed to decode frontmatter string.', 0, $exception);
            }

            if (is_string($decoded)) {
                return $decoded;
            }
        }

        return $value;
    }
}
