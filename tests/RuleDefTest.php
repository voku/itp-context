<?php

declare(strict_types=1);

namespace ItpContext\Tests;

use ItpContext\Enum\Tier;
use ItpContext\Model\RuleDef;
use LogicException;
use PHPUnit\Framework\TestCase;

final class RuleDefTest extends TestCase
{
    public function testConstructorRejectsEmptyStatement(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Rule statement cannot be empty.');

        new RuleDef('   ');
    }

    public function testCriticalRuleRequiresProof(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('must have proof');

        new RuleDef('Critical rule', Tier::Critical);
    }

    public function testCriticalRuleRequiresOwner(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('must have an explicitly assigned owner');

        new RuleDef('Critical rule', Tier::Critical, verifiedBy: [self::class]);
    }

    public function testChangeGuidanceIsOptional(): void
    {
        $plain = new RuleDef('Rule');
        $guided = new RuleDef('Rule', changeGuidance: 'Read the ADR first.');

        self::assertNull($plain->changeGuidance);
        self::assertSame('Read the ADR first.', $guided->changeGuidance);
    }

    public function testEmptyChangeGuidanceIsRejected(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('empty changeGuidance');

        new RuleDef('Rule', changeGuidance: '  ');
    }

    public function testStrictRuleRejectsBlankOwner(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('must have an explicitly assigned owner');

        new RuleDef('Critical rule', Tier::Critical, owner: '   ', verifiedBy: [self::class]);
    }
}
