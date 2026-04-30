<?php

declare(strict_types=1);

$autoload = dirname(__DIR__) . '/vendor/autoload.php';
if (file_exists($autoload)) {
    require $autoload;
    return;
}

require dirname(__DIR__) . '/src/Contract/RuleIdentifier.php';
require dirname(__DIR__) . '/src/Attribute/Rule.php';
require dirname(__DIR__) . '/src/Context/PackageRules.php';
require dirname(__DIR__) . '/src/Enum/Tier.php';
require dirname(__DIR__) . '/src/Model/ExportReport.php';
require dirname(__DIR__) . '/src/Model/RuleDef.php';
require dirname(__DIR__) . '/src/Service/ParsedSymbol.php';
require dirname(__DIR__) . '/src/Service/TokenParser.php';
require dirname(__DIR__) . '/src/Service/ContextResolver.php';
require dirname(__DIR__) . '/src/Service/Frontmatter.php';
require dirname(__DIR__) . '/src/Service/ExportWriter.php';
require dirname(__DIR__) . '/src/Service/Validator.php';
require dirname(__DIR__) . '/src/Service/Summarizer.php';
require dirname(__DIR__) . '/src/Service/ContextExporter.php';
require dirname(__DIR__) . '/src/Service/Generator.php';
require dirname(__DIR__) . '/examples/basic-domain/src/Context/ArchitectureRules.php';
require dirname(__DIR__) . '/examples/basic-domain/src/DashboardView.php';
