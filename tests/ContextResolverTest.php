<?php

declare(strict_types=1);

namespace ItpContext\Tests;

use ItpContext\Model\RuleDef;
use ItpContext\Service\ContextResolver;
use ItpContextExample\Context\ArchitectureRules;
use PHPUnit\Framework\TestCase;

final class ContextResolverTest extends TestCase
{
    public function testResolveReturnsRuleDefinitionFromCatalog(): void
    {
        $definition = (new ContextResolver())->resolve(ArchitectureRules::ViewAbstraction);

        self::assertInstanceOf(RuleDef::class, $definition);
        self::assertSame('Use a dedicated view abstraction for rendering.', $definition->statement);
        self::assertSame('Team-Architecture', $definition->owner);
    }
}
