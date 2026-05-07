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
}
