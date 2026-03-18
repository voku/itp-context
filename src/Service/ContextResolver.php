<?php

declare(strict_types=1);

namespace ItpContext\Service;

use ItpContext\Contract\RuleIdentifier;
use ItpContext\Model\RuleDef;
use ReflectionEnum;
use RuntimeException;

final class ContextResolver
{
    /**
     * @var array<class-string<RuleIdentifier>, array<string, RuleDef>>
     */
    private array $cache = [];

    public function resolve(RuleIdentifier $id): RuleDef
    {
        $enumClass = $id::class;

        if (!isset($this->cache[$enumClass])) {
            $this->loadCatalog($enumClass);
        }

        if (!isset($this->cache[$enumClass][$id->name])) {
            throw new RuntimeException("Orphaned rule ID: {$enumClass}::{$id->name} exists in the enum but is missing from the catalog.");
        }

        return $this->cache[$enumClass][$id->name];
    }

    /**
     * @param class-string<RuleIdentifier> $enumClass
     */
    private function loadCatalog(string $enumClass): void
    {
        $reflection = new ReflectionEnum($enumClass);
        $catalogPath = $this->resolveCatalogPath($reflection, $enumClass);

        if (!file_exists($catalogPath)) {
            throw new RuntimeException("Missing context catalog at {$catalogPath}");
        }

        $catalog = require $catalogPath;
        if (!is_array($catalog)) {
            throw new RuntimeException("Catalog must return an array: {$catalogPath}");
        }

        foreach ($catalog as $key => $definition) {
            if (!is_string($key)) {
                throw new RuntimeException("Invalid catalog key in {$catalogPath}: keys must be strings.");
            }

            if (!$definition instanceof RuleDef) {
                throw new RuntimeException("Invalid catalog value in {$catalogPath}: values must be RuleDef instances.");
            }
        }

        $caseNames = [];
        foreach ($reflection->getCases() as $case) {
            $caseNames[$case->getName()] = true;
        }

        foreach (array_keys($catalog) as $key) {
            if (!isset($caseNames[$key])) {
                throw new RuntimeException("Stale catalog entry '{$key}' not found as an enum case in {$enumClass}. File: {$catalogPath}");
            }
        }

        /** @var array<string, RuleDef> $catalog */
        $this->cache[$enumClass] = $catalog;
    }

    /**
     * @param ReflectionEnum<RuleIdentifier> $reflection
     * @param class-string<RuleIdentifier> $enumClass
     */
    private function resolveCatalogPath(ReflectionEnum $reflection, string $enumClass): string
    {
        $file = $reflection->getFileName();
        if ($file === false) {
            throw new RuntimeException("Cannot resolve enum file path for {$enumClass}.");
        }

        if (!str_ends_with($file, 'Rules.php')) {
            throw new RuntimeException("Rule enum file must end with 'Rules.php': {$file}");
        }

        return substr($file, 0, -strlen('Rules.php')) . 'Catalog.php';
    }
}
