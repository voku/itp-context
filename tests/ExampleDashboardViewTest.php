<?php

declare(strict_types=1);

namespace ItpContext\Tests;

use ItpContextExample\DashboardView;
use PHPUnit\Framework\TestCase;

final class ExampleDashboardViewTest extends TestCase
{
    public function testRenderReturnsExpectedMarker(): void
    {
        self::assertSame('ok', (new DashboardView())->render());
    }
}
