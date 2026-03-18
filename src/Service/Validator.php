<?php

declare(strict_types=1);

namespace ItpContext\Service;

use ItpContext\Contract\RuleIdentifier;
use ReflectionEnum;

final class Validator
{
    public function __construct(private ContextResolver $resolver = new ContextResolver())
    {
    }

    /**
     * @param class-string $enumClass
     * @return list<string>
     */
    public function validateEnumClass(string $enumClass): array
    {
        if (!is_subclass_of($enumClass, RuleIdentifier::class)) {
            return ["Not a RuleIdentifier enum: {$enumClass}"];
        }

        $errors = [];
        $reflection = new ReflectionEnum($enumClass);

        foreach ($reflection->getCases() as $case) {
            try {
                $identifier = $case->getValue();
                if (!$identifier instanceof RuleIdentifier) {
                    $errors[] = "❌ [{$case->getName()}] Enum case value does not implement RuleIdentifier.";
                    continue;
                }

                $this->resolver->resolve($identifier);
            } catch (\Throwable $throwable) {
                $errors[] = "❌ [{$case->getName()}] {$throwable->getMessage()}";
            }
        }

        return $errors;
    }
}
