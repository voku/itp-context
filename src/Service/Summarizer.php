<?php

declare(strict_types=1);

namespace ItpContext\Service;

use ItpContext\Attribute\Rule;
use ReflectionClass;

final class Summarizer
{
    public function __construct(
        private ContextResolver $resolver = new ContextResolver(),
        private TokenParser $parser = new TokenParser(),
    ) {
    }

    public function summarize(string $filePath): string
    {
        if ($filePath === '' || !file_exists($filePath)) {
            throw new \RuntimeException("File not found: {$filePath}");
        }

        $symbol = $this->parser->getFirstSymbolFromFile($filePath);
        if ($symbol === null) {
            throw new \RuntimeException('No class/interface/trait/enum found in file.');
        }

        $exists = match ($symbol->kind) {
            'class', 'enum' => class_exists($symbol->fqcn),
            'interface' => interface_exists($symbol->fqcn),
            'trait' => trait_exists($symbol->fqcn),
            default => false,
        };

        if (!$exists) {
            throw new \RuntimeException("Symbol not autoloadable: {$symbol->fqcn}");
        }

        $reflection = new ReflectionClass($symbol->fqcn);
        $output = "# Context: {$reflection->getShortName()}\n\n";
        $output .= $this->render($reflection->getAttributes(Rule::class));

        foreach ($reflection->getMethods() as $method) {
            $attributes = $method->getAttributes(Rule::class);
            if ($attributes === []) {
                continue;
            }

            $output .= "## Method: `{$method->getName()}`\n";
            $output .= $this->render($attributes);
        }

        return $output;
    }

    public function handle(?string $filePath): void
    {
        try {
            echo $this->summarize((string)$filePath);
        } catch (\Throwable $throwable) {
            fwrite(STDERR, $throwable->getMessage() . "\n");
            exit(1);
        }
    }

    /**
     * @param array<\ReflectionAttribute<Rule>> $attributes
     */
    private function render(array $attributes): string
    {
        $output = '';

        foreach ($attributes as $attribute) {
            try {
                $instance = $attribute->newInstance();
                $definition = $this->resolver->resolve($instance->id);

                $icon = match ($definition->tier->value) {
                    1 => '[CRITICAL]',
                    2 => '[IMPORTANT]',
                    default => '[INFO]',
                };

                $output .= "### {$icon} {$definition->statement}\n";
                $output .= '- **ID:** `' . $instance->id::class . "::{$instance->id->name}`\n";

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
}
