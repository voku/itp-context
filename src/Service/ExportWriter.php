<?php

declare(strict_types=1);

namespace ItpContext\Service;

use RuntimeException;

final class ExportWriter
{
    public function __construct(private string $outputDir)
    {
        $this->outputDir = rtrim($this->outputDir, '/');

        if ($this->outputDir === '') {
            throw new RuntimeException('Output directory cannot be empty.');
        }
    }

    /**
     * @param array<string, mixed> $meta
     */
    public function writeMarkdown(string $area, string $slug, array $meta, string $body): string
    {
        $safeArea = trim($area, '/');
        $fileName = $this->normalizeSlug($slug) . '.md';
        $directory = $safeArea !== '' ? $this->outputDir . '/' . $safeArea : $this->outputDir;

        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
            throw new RuntimeException(sprintf('Directory "%s" was not created.', $directory));
        }

        $path = $directory . '/' . $fileName;
        $bytes = file_put_contents($path, Frontmatter::render($meta, $body));
        if ($bytes === false) {
            throw new RuntimeException("Failed to write export file: {$path}");
        }

        return $path;
    }

    private function normalizeSlug(string $slug): string
    {
        $slug = trim($slug);
        if ($slug === '') {
            return 'context';
        }

        $slug = preg_replace('/[^A-Za-z0-9._-]+/', '-', $slug) ?? $slug;
        $slug = trim($slug, '-');

        return $slug !== '' ? $slug : 'context';
    }
}
