<?php

declare(strict_types=1);

namespace ItpContext\Service;

use ItpContext\Contract\RuleIdentifier;
use ItpContext\Model\ApplicableRule;
use RuntimeException;

/**
 * Stateless: answers which architecture rules apply to a given list of PHP files.
 * Rules are collected from `#[Rule]` attributes and returned once per rule id.
 */
final class ApplicableRuleResolver
{
    public function __construct(
        private TokenParser $parser = new TokenParser(),
        private ContextResolver $resolver = new ContextResolver(),
    ) {
    }

    /**
     * @param iterable<string> $files PHP file paths; unreadable or non-PHP files contribute nothing
     * @return list<ApplicableRule> sorted by rule id, deterministic for the same files
     * @throws RuntimeException when an annotated rule id cannot be resolved to a RuleIdentifier case
     */
    public function resolveFiles(iterable $files): array
    {
        $subjectsByRule = [];
        $seenFiles = [];

        foreach ($files as $file) {
            if (isset($seenFiles[$file])) {
                continue;
            }
            $seenFiles[$file] = true;

            foreach ($this->parser->getRuleTargetsFromFile($file) as $target) {
                foreach ($target->ruleIds as $ruleId) {
                    $subjectsByRule[$ruleId][$target->fqcn] = true;
                }
            }
        }

        ksort($subjectsByRule);

        $rules = [];
        foreach ($subjectsByRule as $ruleId => $subjects) {
            $definition = $this->resolver->resolve($this->identifier((string) $ruleId));
            $subjects = array_map('strval', array_keys($subjects));
            sort($subjects);

            $rules[] = new ApplicableRule(
                ruleId: (string) $ruleId,
                statement: $definition->statement,
                changeGuidance: $definition->changeGuidance,
                refs: $definition->refs,
                subjects: $subjects,
            );
        }

        return $rules;
    }

    private function identifier(string $ruleId): RuleIdentifier
    {
        [$enumClass, $case] = explode('::', $ruleId, 2) + [1 => ''];

        if ((enum_exists($enumClass) || class_exists($enumClass)) && defined($ruleId)) {
            $identifier = constant($ruleId);
            if ($identifier instanceof RuleIdentifier) {
                return $identifier;
            }
        }

        throw new RuntimeException(sprintf('Rule "%s" cannot be resolved to a %s case. Is the enum autoloadable?', $ruleId, RuleIdentifier::class));
    }
}
