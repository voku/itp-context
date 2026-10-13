<?php

declare(strict_types=1);

namespace ItpContext\Model;

/**
 * Advisory notice about a change to protected code. Never an enforcement result.
 */
final readonly class ChangeNotice
{
    /**
     * @param 'modified'|'removed'|'annotation-removed'|'rule-definition-changed'|'rule-removed' $kind
     * @param list<string> $refs
     */
    public function __construct(
        public string $id,
        public string $kind,
        public string $ruleId,
        public string $subject,
        public string $sourcePath,
        public string $statement,
        public ?string $guidance,
        public array $refs,
    ) {
    }

    /**
     * Shape consumed by voku/phpstan-agent-format's `noticesFile`.
     *
     * @return array{id: string, title: string, message: string, subject: string, refs: list<string>}
     */
    public function toArray(): array
    {
        $what = match ($this->kind) {
            'modified' => 'Protected symbol modified',
            'removed' => 'Protected symbol removed',
            'annotation-removed' => 'Rule annotation removed',
            'rule-definition-changed' => 'Rule definition changed',
            'rule-removed' => 'Rule definition removed',
        };

        return [
            'id' => $this->id,
            'title' => $what . ': ' . $this->statement,
            'message' => $this->guidance ?? 'No changeGuidance is defined for this rule; review its refs before keeping this change.',
            'subject' => $this->subject,
            'refs' => $this->refs,
        ];
    }
}
