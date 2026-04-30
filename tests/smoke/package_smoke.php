<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);

require $root . '/src/Contract/RuleIdentifier.php';
require $root . '/src/Attribute/Rule.php';
require $root . '/src/Context/PackageRules.php';
require $root . '/src/Enum/Tier.php';
require $root . '/src/Model/ExportReport.php';
require $root . '/src/Model/RuleDef.php';
require $root . '/src/Service/ParsedSymbol.php';
require $root . '/src/Service/TokenParser.php';
require $root . '/src/Service/ContextResolver.php';
require $root . '/src/Service/Frontmatter.php';
require $root . '/src/Service/ExportWriter.php';
require $root . '/src/Service/Validator.php';
require $root . '/src/Service/Summarizer.php';
require $root . '/src/Service/ContextExporter.php';
require $root . '/src/Service/Generator.php';
require $root . '/examples/basic-domain/src/Context/ArchitectureRules.php';
require $root . '/examples/basic-domain/src/DashboardView.php';

$validator = new ItpContext\Service\Validator();
$errors = $validator->validateEnumClass(ItpContextExample\Context\ArchitectureRules::class);
if ($errors !== []) {
    fwrite(STDERR, implode(PHP_EOL, $errors) . PHP_EOL);
    exit(1);
}

$packageErrors = $validator->validateEnumClass(ItpContext\Context\PackageRules::class);
if ($packageErrors !== []) {
    fwrite(STDERR, implode(PHP_EOL, $packageErrors) . PHP_EOL);
    exit(1);
}

$summarizer = new ItpContext\Service\Summarizer();
$output = $summarizer->summarize($root . '/examples/basic-domain/src/DashboardView.php');
if (!str_contains($output, 'ViewAbstraction') || !str_contains($output, 'I18n')) {
    fwrite(STDERR, "Unexpected summary output.\n");
    exit(1);
}

$packageOutput = $summarizer->summarize($root . '/src/Service/ContextResolver.php');
if (!str_contains($packageOutput, 'PackageRules::FrameworkAgnostic') || !str_contains($packageOutput, 'PackageRules::CatalogByConvention')) {
    fwrite(STDERR, "Unexpected package summary output.\n");
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

$exportBaseDir = sys_get_temp_dir() . '/itp-context-export-' . md5((string) getmypid());
$removeDirectory($exportBaseDir);

$command = escapeshellarg(PHP_BINARY)
    . ' '
    . escapeshellarg($root . '/bin/itp-context-export')
    . ' '
    . escapeshellarg($exportBaseDir)
    . ' '
    . escapeshellarg($root . '/src');

$output = [];
$exitCode = 0;
exec($command, $output, $exitCode);

if ($exitCode !== 0) {
    fwrite(STDERR, "Context export CLI failed.\n");
    exit(1);
}

if (!isset($output[0]) || !str_contains($output[0], 'Exported 3 context documents')) {
    fwrite(STDERR, "Context export CLI produced unexpected output.\n");
    exit(1);
}

$frontmatter = ItpContext\Service\Frontmatter::parse(
    (string) file_get_contents($exportBaseDir . '/php/ItpContext_Service_ContextExporter.md')
);

if (($frontmatter['source_path'] ?? null) !== 'src/Service/ContextExporter.php' || !file_exists($exportBaseDir . '/index.md')) {
    fwrite(STDERR, "Context export did not create the expected files.\n");
    exit(1);
}

$removeDirectory($exportBaseDir);

echo "Package smoke checks passed.\n";
