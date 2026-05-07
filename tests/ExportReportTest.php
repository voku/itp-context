<?php

declare(strict_types=1);

namespace ItpContext\Tests;

use ItpContext\Model\ExportReport;
use PHPUnit\Framework\TestCase;

final class ExportReportTest extends TestCase
{
    public function testHasErrorsReturnsTrueWhenErrorsExist(): void
    {
        $report = new ExportReport('/tmp/export', 1, 0, 1, [], ['broken']);

        self::assertTrue($report->hasErrors());
    }

    public function testHasErrorsReturnsFalseWhenErrorsAreEmpty(): void
    {
        $report = new ExportReport('/tmp/export', 1, 1, 0, ['/tmp/export/index.md']);

        self::assertFalse($report->hasErrors());
    }
}
