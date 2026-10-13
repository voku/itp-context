<?php

declare(strict_types=1);

namespace ItpContext\Tests;

use ItpContext\Model\ChangeNotice;
use ItpContext\Service\NoticeAcknowledgements;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class NoticeAcknowledgementsTest extends TestCase
{
    private string $stateDir;

    protected function setUp(): void
    {
        $this->stateDir = sys_get_temp_dir() . '/' . uniqid('itp-context-ack-');
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->stateDir));
    }

    public function testSameNoticeIsShownOnlyOnce(): void
    {
        $acknowledgements = NoticeAcknowledgements::forRepository($this->stateDir, __DIR__);
        $notice = $this->notice('a');

        self::assertSame([$notice], $acknowledgements->consume([$notice]));
        self::assertSame([], $acknowledgements->consume([$notice]));
        self::assertSame([], NoticeAcknowledgements::forRepository($this->stateDir, __DIR__)->consume([$notice]));
    }

    public function testChangedNoticeIdIsShownAgain(): void
    {
        $acknowledgements = NoticeAcknowledgements::forRepository($this->stateDir, __DIR__);
        $acknowledgements->consume([$this->notice('a')]);

        self::assertCount(1, $acknowledgements->consume([$this->notice('a'), $this->notice('b')]));
    }

    public function testRepositoriesDoNotShareState(): void
    {
        NoticeAcknowledgements::forRepository($this->stateDir, __DIR__)->consume([$this->notice('a')]);

        self::assertCount(1, NoticeAcknowledgements::forRepository($this->stateDir, dirname(__DIR__))->consume([$this->notice('a')]));
    }

    public function testCorruptStateFailsLoudly(): void
    {
        $file = $this->stateDir . '/state.json';
        mkdir($this->stateDir);
        file_put_contents($file, 'not json');

        $this->expectException(RuntimeException::class);

        (new NoticeAcknowledgements($file))->consume([$this->notice('a')]);
    }

    private function notice(string $id): ChangeNotice
    {
        return new ChangeNotice($id, 'modified', 'R::A', 'S', 'src/S.php', 'Statement', null, []);
    }
}
