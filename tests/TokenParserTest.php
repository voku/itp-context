<?php

declare(strict_types=1);

namespace ItpContext\Tests;

use ItpContext\Service\TokenParser;
use PHPUnit\Framework\TestCase;

final class TokenParserTest extends TestCase
{
    public function testGetFirstSymbolFromFileSkipsClassConstantReferences(): void
    {
        $symbol = (new TokenParser())->getFirstSymbolFromFile(
            dirname(__DIR__) . '/examples/basic-domain/src/Context/ArchitectureCatalog.php'
        );

        self::assertNull($symbol);
    }
}
