<?php

declare(strict_types=1);

namespace ItpContext\Tests;

use ItpContext\Service\Validator;
use ItpContextExample\Context\ArchitectureRules;
use PHPUnit\Framework\TestCase;

final class ValidatorTest extends TestCase
{
    public function testValidateEnumClassReturnsNoErrorsForMatchingCatalog(): void
    {
        $errors = (new Validator())->validateEnumClass(ArchitectureRules::class);

        self::assertSame([], $errors);
    }
}
