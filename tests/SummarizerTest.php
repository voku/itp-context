<?php

declare(strict_types=1);

namespace ItpContext\Tests;

use ItpContext\Service\Summarizer;
use PHPUnit\Framework\TestCase;

final class SummarizerTest extends TestCase
{
    public function testSummarizeContainsClassAndMethodRules(): void
    {
        $output = (new Summarizer())->summarize(dirname(__DIR__) . '/examples/basic-domain/src/DashboardView.php');

        self::assertStringContainsString('Context: DashboardView', $output);
        self::assertStringContainsString('ArchitectureRules::ViewAbstraction', $output);
        self::assertStringContainsString('ArchitectureRules::I18n', $output);
    }
}
