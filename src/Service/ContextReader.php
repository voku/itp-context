<?php

declare(strict_types=1);

namespace ItpContext\Service;

use ItpContext\Attribute\Rule;
use ItpContext\Context\PackageRules;
use ItpContext\Contract\RuleIdentifier;
use ItpContext\Model\ContextDocument;
use ItpContext\Model\RuleDef;
use ItpContext\Model\RuleTarget;

/**
 * Reads context documents directly from source files so summaries and exports
 * still work for multiple symbols, functions, and partially autoloadable code.
 * The class stays extendable so tests can inject narrow failure-oriented doubles.
 */
#[Rule(PackageRules::DegradedDiscovery)]
class ContextReader
{
    public function __construct(
        private ContextResolver $resolver = new ContextResolver(),
        private TokenParser $parser = new TokenParser(),
    ) {
    }

    /**
     * @return list<ContextDocument>
     */
    public function read(string $filePath): array
    {
        if ($filePath === '' || !file_exists($filePath)) {
            throw new \RuntimeException("File not found: {$filePath}");
        }

        $symbols = $this->parser->getSymbolsFromFile($filePath);
        if ($symbols === []) {
            throw new \RuntimeException('No class/interface/trait/enum/function found in file.');
        }

        $targets = $this->parser->getRuleTargetsFromFile($filePath);
        $sourcePath = $this->toRelativePath($filePath);
        $documents = [];

        foreach ($symbols as $symbol) {
            $directTarget = $this->findDirectTarget($targets, $symbol->kind, $symbol->fqcn);
            $methodTargets = $this->methodTargetsForSymbol($targets, $symbol->fqcn);
            $metadata = $this->collectMetadata($directTarget, $methodTargets);

            $documents[] = new ContextDocument(
                id: $symbol->kind === 'function' ? 'PHP:function:' . $symbol->fqcn : 'PHP:' . $symbol->fqcn,
                title: $symbol->fqcn,
                kind: $symbol->kind,
                sourcePath: $sourcePath,
                ruleIds: $metadata['rule_ids'],
                owners: $metadata['owners'],
                refs: $metadata['refs'],
                verifiedBy: $metadata['verified_by'],
                annotatedMethods: $metadata['annotated_methods'],
                body: $this->renderDocument($symbol->kind, $symbol->fqcn, $directTarget, $methodTargets),
            );
        }

        return $documents;
    }

    /**
     * @param list<RuleTarget> $targets
     * @return list<RuleTarget>
     */
    private function methodTargetsForSymbol(array $targets, string $fqcn): array
    {
        return array_values(array_filter(
            $targets,
            static fn (RuleTarget $target): bool => $target->kind === 'method' && $target->ownerFqcn === $fqcn
        ));
    }

    /**
     * @param list<RuleTarget> $targets
     */
    private function findDirectTarget(array $targets, string $kind, string $fqcn): ?RuleTarget
    {
        foreach ($targets as $target) {
            if ($target->kind === $kind && $target->fqcn === $fqcn) {
                return $target;
            }
        }

        return null;
    }

    /**
     * @param list<RuleTarget> $methodTargets
     * @return array{
     *     rule_ids: list<string>,
     *     owners: list<string>,
     *     refs: list<string>,
     *     verified_by: list<string>,
     *     annotated_methods: list<string>
     * }
     */
    private function collectMetadata(?RuleTarget $directTarget, array $methodTargets): array
    {
        $ruleIds = [];
        $owners = [];
        $refs = [];
        $verifiedBy = [];
        $annotatedMethods = [];

        foreach (array_filter([$directTarget]) as $target) {
            foreach ($target->ruleIds as $ruleId) {
                $ruleIds[] = $ruleId;
                try {
                    $definition = $this->tryResolveRuleDefinition($ruleId);
                } catch (\Throwable) {
                    continue;
                }

                if ($definition === null) {
                    continue;
                }

                $this->appendDefinitionMetadata($definition, $owners, $refs, $verifiedBy);
            }
        }

        foreach ($methodTargets as $methodTarget) {
            if (!$methodTarget->hasRules()) {
                continue;
            }

            $annotatedMethods[] = $methodTarget->name;

            foreach ($methodTarget->ruleIds as $ruleId) {
                $ruleIds[] = $ruleId;
                try {
                    $definition = $this->tryResolveRuleDefinition($ruleId);
                } catch (\Throwable) {
                    continue;
                }

                if ($definition === null) {
                    continue;
                }

                $this->appendDefinitionMetadata($definition, $owners, $refs, $verifiedBy);
            }
        }

        return [
            'rule_ids' => $this->uniqueSorted($ruleIds),
            'owners' => $this->uniqueSorted($owners),
            'refs' => $this->uniqueSorted($refs),
            'verified_by' => $this->uniqueSorted($verifiedBy),
            'annotated_methods' => $this->uniqueSorted($annotatedMethods),
        ];
    }

    /**
     * @param list<string> $owners
     * @param list<string> $refs
     * @param list<string> $verifiedBy
     */
    private function appendDefinitionMetadata(
        RuleDef $definition,
        array &$owners,
        array &$refs,
        array &$verifiedBy,
    ): void
    {
        if ($definition->owner !== null && trim($definition->owner) !== '') {
            $owners[] = $definition->owner;
        }

        foreach ($definition->refs as $ref) {
            if (trim($ref) !== '') {
                $refs[] = $ref;
            }
        }

        foreach ($definition->verifiedBy as $proof) {
            if (trim($proof) !== '') {
                $verifiedBy[] = $proof;
            }
        }
    }

    /**
     * @param list<RuleTarget> $methodTargets
     */
    private function renderDocument(
        string $kind,
        string $fqcn,
        ?RuleTarget $directTarget,
        array $methodTargets,
    ): string {
        $output = $kind === 'function'
            ? '# Context: function `' . $fqcn . "()`\n\n"
            : '# Context: ' . $this->shortName($fqcn) . "\n\n";

        if ($directTarget !== null) {
            $output .= $this->renderRules($directTarget->ruleIds);
        }

        foreach ($methodTargets as $methodTarget) {
            if (!$methodTarget->hasRules()) {
                continue;
            }

            $output .= '## Method: `' . $methodTarget->name . "`\n";
            $output .= $this->renderRules($methodTarget->ruleIds);
        }

        return $output;
    }

    /**
     * @param list<string> $ruleIds
     */
    private function renderRules(array $ruleIds): string
    {
        $output = '';

        foreach ($ruleIds as $ruleId) {
            try {
                $definition = $this->tryResolveRuleDefinition($ruleId);
                if ($definition === null) {
                    $output .= "### [INFO] Raw rule annotation.\n";
                    $output .= '- **ID:** `' . $ruleId . "`\n";
                    $output .= "- **Why:** Rule definition could not be resolved from the current runtime.\n\n";
                    continue;
                }

                $icon = match ($definition->tier->value) {
                    1 => '[CRITICAL]',
                    2 => '[IMPORTANT]',
                    default => '[INFO]',
                };

                $output .= "### {$icon} {$definition->statement}\n";
                $output .= '- **ID:** `' . $ruleId . "`\n";

                if ($definition->rationale !== null) {
                    $output .= "- **Why:** {$definition->rationale}\n";
                }
                if ($definition->owner !== null) {
                    $output .= "- **Owner:** {$definition->owner}\n";
                }
                if ($definition->verifiedBy !== []) {
                    $output .= '- **Proof:** ' . implode(', ', $definition->verifiedBy) . "\n";
                }
                if ($definition->refs !== []) {
                    $output .= '- **Refs:** ' . implode(', ', $definition->refs) . "\n";
                }

                $output .= "\n";
            } catch (\Throwable $throwable) {
                $output .= "⚠ Error: {$throwable->getMessage()}\n\n";
            }
        }

        return $output;
    }

    private function tryResolveRuleDefinition(string $ruleId): ?RuleDef
    {
        $parts = explode('::', $ruleId, 2);
        if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') {
            return null;
        }

        [$enumClass, $caseName] = $parts;
        $constantName = $enumClass . '::' . $caseName;

        if (!enum_exists($enumClass) && !class_exists($enumClass)) {
            return null;
        }

        if (!defined($constantName)) {
            return null;
        }

        $identifier = constant($constantName);
        if (!$identifier instanceof RuleIdentifier) {
            return null;
        }

        return $this->resolver->resolve($identifier);
    }

    private function shortName(string $fqcn): string
    {
        $position = strrpos($fqcn, '\\');

        return $position === false ? $fqcn : substr($fqcn, $position + 1);
    }

    private function toRelativePath(string $path): string
    {
        $cwd = getcwd();
        if (!is_string($cwd)) {
            return $path;
        }

        $prefix = rtrim($cwd, '/') . '/';

        if (!str_starts_with($path, $prefix)) {
            return $path;
        }

        return substr($path, strlen($prefix));
    }

    /**
     * @param list<string> $values
     * @return list<string>
     */
    private function uniqueSorted(array $values): array
    {
        $values = array_values(array_unique(array_filter($values, static fn (string $value): bool => trim($value) !== '')));
        sort($values);

        return $values;
    }
}
