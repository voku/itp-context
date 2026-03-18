<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);

require $root . '/src/Contract/RuleIdentifier.php';
require $root . '/src/Attribute/Rule.php';
require $root . '/src/Enum/Tier.php';
require $root . '/src/Model/RuleDef.php';
require $root . '/src/Service/ParsedSymbol.php';
require $root . '/src/Service/TokenParser.php';
require $root . '/src/Service/ContextResolver.php';
require $root . '/src/Service/Validator.php';
require $root . '/src/Service/Summarizer.php';
require $root . '/src/Service/Generator.php';
require $root . '/examples/basic-domain/src/Context/ArchitectureRules.php';
require $root . '/examples/basic-domain/src/DashboardView.php';

$validator = new ItpContext\Service\Validator();
$errors = $validator->validateEnumClass(ItpContextExample\Context\ArchitectureRules::class);
if ($errors !== []) {
    fwrite(STDERR, implode(PHP_EOL, $errors) . PHP_EOL);
    exit(1);
}

$summarizer = new ItpContext\Service\Summarizer();
$output = $summarizer->summarize($root . '/examples/basic-domain/src/DashboardView.php');
if (!str_contains($output, 'ViewAbstraction') || !str_contains($output, 'I18n')) {
    fwrite(STDERR, "Unexpected summary output.\n");
    exit(1);
}

$generatorBaseDir = sys_get_temp_dir() . '/itp-context-smoke-' . md5((string) getmypid());
$removeDirectory = static function (string $path): void {
    if (!is_dir($path)) {
        return;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($iterator as $item) {
        if ($item->isDir()) {
            rmdir($item->getPathname());
            continue;
        }

        unlink($item->getPathname());
    }

    rmdir($path);
};

$removeDirectory($generatorBaseDir);

(new ItpContext\Service\Generator())->handle('Example', 'SecurityBoundary', $generatorBaseDir, 'Smoke\\Context');

if (!file_exists($generatorBaseDir . '/ExampleRules.php') || !file_exists($generatorBaseDir . '/ExampleCatalog.php')) {
    fwrite(STDERR, "Generator did not create the expected files.\n");
    exit(1);
}

$removeDirectory($generatorBaseDir);

echo "Package smoke checks passed.\n";
