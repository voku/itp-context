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

    public function testStateIsStoredAsSortedIdListUnderRepositoryKey(): void
    {
        NoticeAcknowledgements::forRepository($this->stateDir . '/', __DIR__)->consume([$this->notice('b'), $this->notice('a')]);

        $expected = $this->stateDir . '/itp-context/' . sha1((string) realpath(__DIR__)) . '.json';
        self::assertFileExists($expected);
        self::assertSame('{"seen":["a","b"]}', file_get_contents($expected));
        self::assertSame([], glob($this->stateDir . '/itp-context/*.tmp'));
    }

    public function testUnresolvableRepositoryPathIsKeyedByItsGivenPath(): void
    {
        NoticeAcknowledgements::forRepository($this->stateDir, '/does/not/exist')->consume([$this->notice('a')]);

        self::assertFileExists($this->stateDir . '/itp-context/' . sha1('/does/not/exist') . '.json');
    }

    public function testNumericIdsAreRemembered(): void
    {
        $acknowledgements = NoticeAcknowledgements::forRepository($this->stateDir, __DIR__);
        $acknowledgements->consume([$this->notice('123')]);

        self::assertSame([], $acknowledgements->consume([$this->notice('123')]));
    }

    public function testNothingIsWrittenWhenAllNoticesWereSeen(): void
    {
        $acknowledgements = NoticeAcknowledgements::forRepository($this->stateDir, __DIR__);
        $acknowledgements->consume([$this->notice('a')]);
        $file = $this->stateDir . '/itp-context/' . sha1((string) realpath(__DIR__)) . '.json';
        file_put_contents($file, '{"seen":["a"],"marker":1}');

        $acknowledgements->consume([$this->notice('a')]);

        self::assertSame('{"seen":["a"],"marker":1}', file_get_contents($file));
    }

    public function testCorruptStateVariantsFailLoudly(): void
    {
        mkdir($this->stateDir);
        $file = $this->stateDir . '/state.json';

        foreach (['not json', '"x"', 'null', '{}', '{"seen":"x"}', '{"seen":{"a":"a"}}', '{"seen":[1]}'] as $contents) {
            file_put_contents($file, $contents);

            try {
                (new NoticeAcknowledgements($file))->consume([$this->notice('a')]);
                self::fail('Expected a RuntimeException for: ' . $contents);
            } catch (RuntimeException $exception) {
                self::assertStringContainsString('is corrupt', $exception->getMessage());
            }
        }
    }

    public function testUnreadableDirectoryFailsLoudly(): void
    {
        mkdir($this->stateDir);
        file_put_contents($this->stateDir . '/blocker', 'x');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cannot create acknowledgement directory');

        (new NoticeAcknowledgements($this->stateDir . '/blocker/sub/state.json'))->consume([$this->notice('a')]);
    }

    public function testUnwritableTargetFailsLoudly(): void
    {
        mkdir($this->stateDir . '/state.json', 0777, true);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cannot write acknowledgement file');

        (new NoticeAcknowledgements($this->stateDir . '/state.json'))->consume([$this->notice('a')]);
    }

    private function notice(string $id): ChangeNotice
    {
        return new ChangeNotice($id, 'modified', 'R::A', 'S', 'src/S.php', 'Statement', null, []);
    }
}
