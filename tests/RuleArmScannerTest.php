<?php

declare(strict_types=1);

namespace ItpContext\Tests;

use ItpContext\Service\RuleArmScanner;
use PHPUnit\Framework\TestCase;

final class RuleArmScannerTest extends TestCase
{
    public function testExtractsStatementGuidanceAndRefsFromEveryArm(): void
    {
        $arms = (new RuleArmScanner())->scan($this->enum(<<<'PHP'
            self::One => new RuleDef(
                statement: 'First \'quoted\' \\ rule.',
                refs: ['docs/a.md', 'docs/b.md'],
                changeGuidance: "Read the ADR.\nThen ask.",
            ),
            self::Two => new RuleDef(statement: 'Second.', verifiedBy: [A::class, B::class]),
PHP));

        self::assertSame(['Fx\Rules::One', 'Fx\Rules::Two'], array_keys($arms));
        self::assertSame('Fx\Rules::One', $arms['Fx\Rules::One']->ruleId);
        self::assertSame("First 'quoted' \\ rule.", $arms['Fx\Rules::One']->statement);
        self::assertSame("Read the ADR.\nThen ask.", $arms['Fx\Rules::One']->changeGuidance);
        self::assertSame(['docs/a.md', 'docs/b.md'], $arms['Fx\Rules::One']->refs);
        self::assertSame('Second.', $arms['Fx\Rules::Two']->statement);
        self::assertNull($arms['Fx\Rules::Two']->changeGuidance);
        self::assertSame([], $arms['Fx\Rules::Two']->refs);
        self::assertNotSame($arms['Fx\Rules::One']->fingerprint, $arms['Fx\Rules::Two']->fingerprint);
    }

    public function testLastArmWithoutTrailingCommaAndStaticReceiverAreRead(): void
    {
        $arms = (new RuleArmScanner())->scan($this->enum("            static::One => new RuleDef(statement: 'Only.')\n"));

        self::assertSame('Only.', $arms['Fx\Rules::One']->statement);
    }

    public function testFingerprintIgnoresFormattingButNotContent(): void
    {
        $scanner = new RuleArmScanner();
        $base = $scanner->scan($this->enum("            self::One => new RuleDef(statement: 'A', verifiedBy: [X::class, Y::class]),"));
        $formatted = $scanner->scan($this->enum("            self::One   =>\n  new RuleDef( // note\n statement: 'A',\n verifiedBy: [X::class,\n Y::class] ),"));
        $changedLast = $scanner->scan($this->enum("            self::One => new RuleDef(statement: 'A', verifiedBy: [X::class, Z::class]),"));

        self::assertSame($base['Fx\Rules::One']->fingerprint, $formatted['Fx\Rules::One']->fingerprint);
        self::assertNotSame($base['Fx\Rules::One']->fingerprint, $changedLast['Fx\Rules::One']->fingerprint);
    }

    public function testOnlyLiteralNamedArgumentsAreExtracted(): void
    {
        $arms = (new RuleArmScanner())->scan($this->enum(<<<'PHP'
            self::One => new RuleDef(
                statement: 'a' . 'b',
                refs: [self::REF, 'docs/x.md', 7],
                changeGuidance: self::TEXT,
            ),
            self::Two => new RuleDef('positional'),
            self::Three => new RuleDef(statement: 'Three', refs: self::REFS, changeGuidance: 'x' . 'y'),
PHP));

        self::assertNull($arms['Fx\Rules::One']->statement);
        self::assertNull($arms['Fx\Rules::One']->changeGuidance);
        self::assertSame(['docs/x.md'], $arms['Fx\Rules::One']->refs);
        self::assertNull($arms['Fx\Rules::Two']->statement);
        self::assertSame([], $arms['Fx\Rules::Three']->refs);
        self::assertNull($arms['Fx\Rules::Three']->changeGuidance);
    }

    public function testNonArmsAndNonRuleFilesAreIgnored(): void
    {
        $scanner = new RuleArmScanner();

        self::assertSame([], $scanner->scan("<?php\nnamespace Fx;\nenum Plain { case A; }\n"));
        self::assertSame([], $scanner->scan("<?php\nnamespace Fx;\nclass NotAnEnum { /* RuleDef */ public function x() { return self::A => 1; } }\n"));
        self::assertSame([], $scanner->scan($this->enum("            Other::One => new RuleDef(statement: 'x'),\n            self::Two = new RuleDef(statement: 'x'),")));
        self::assertSame([], $scanner->scan("<?php\nnamespace Fx;\nenum Rules { case One; public function d() { return match (\$this) { self::One => 1 }; } }\n"));
    }

    public function testEnumWithoutNamespaceUsesItsShortName(): void
    {
        $arms = (new RuleArmScanner())->scan("<?php\nenum Rules { case One;\n public function d() { return match (\$this) { self::One => new RuleDef(statement: 'x') }; } }\n");

        self::assertSame(['Rules::One'], array_keys($arms));
    }

    private function enum(string $arms): string
    {
        return "<?php\n\nnamespace Fx;\n\nenum Rules implements RuleIdentifier\n{\n    case One;\n    case Two;\n    case Three;\n\n    public function getDefinition(): RuleDef\n    {\n        return match (\$this) {\n{$arms}\n        };\n    }\n}\n";
    }
}
