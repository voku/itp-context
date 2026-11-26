<?php

declare(strict_types=1);

namespace ItpContext\Tests;

use PHPUnit\Framework\TestCase;
use RuntimeException;

/** @internal */
final class SourceRuleHintCliTest extends TestCase
{
    public function testJsonCliProjectsRealAnnotatedSourceWithoutLoadingItsRuleEnum(): void
    {
        $root = dirname(__DIR__);
        $pipes = [];
        $process = proc_open(
            [
                PHP_BINARY,
                $root . '/bin/itp-context-source-hints',
                '--root=' . $root,
                '--file=src/Service/TokenParser.php',
            ],
            [
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ],
            $pipes,
            $root,
        );
        if (!is_resource($process)) {
            throw new RuntimeException('Unable to start the source-hints CLI.');
        }

        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);
        $exit = proc_close($process);

        self::assertSame(0, $exit, is_string($stderr) ? $stderr : '');
        self::assertIsString($stdout);
        $result = json_decode($stdout, true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($result);
        self::assertSame('ready', $result['status'] ?? null);
        self::assertSame('syntax_only', $result['resolution'] ?? null);
        self::assertSame('none', $result['authority'] ?? null);
        self::assertSame('ItpContext\Context\PackageRules::TokenFirstDiscovery', $result['rules'][0]['rule_id'] ?? null);
        self::assertSame('src/Service/TokenParser.php', $result['rules'][0]['files'][0] ?? null);
    }
}
