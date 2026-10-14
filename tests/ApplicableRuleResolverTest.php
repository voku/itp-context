<?php

declare(strict_types=1);

namespace ItpContext\Tests;

use ItpContext\Service\ApplicableRuleResolver;
use ItpContextExample\Context\ArchitectureRules;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ApplicableRuleResolverTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/' . uniqid('itp-context-applicable-');
        mkdir($this->dir, 0777, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir . '/*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($this->dir);
    }

    public function testRulesAreDeduplicatedAcrossFilesAndCarryTheirSubjects(): void
    {
        $a = $this->write('A.php', 'A', ArchitectureRules::class, 'ViewAbstraction');
        $b = $this->write('B.php', 'B', ArchitectureRules::class, 'ViewAbstraction');
        $c = $this->write('C.php', 'C', ArchitectureRules::class, 'I18n');
        $plain = $this->dir . '/Plain.php';
        file_put_contents($plain, "<?php\nnamespace Fx;\nfinal class Plain {}\n");

        $rules = (new ApplicableRuleResolver())->resolveFiles([$b, $plain, $a, $c, $a]);

        self::assertSame(
            [ArchitectureRules::class . '::I18n', ArchitectureRules::class . '::ViewAbstraction'],
            array_column($rules, 'ruleId'),
        );
        self::assertSame(['Fx\\A', 'Fx\\B'], $rules[1]->subjects);
        self::assertSame(['Fx\\C'], $rules[0]->subjects);

        $definition = ArchitectureRules::ViewAbstraction->getDefinition();
        self::assertSame($definition->statement, $rules[1]->statement);
        self::assertSame($definition->changeGuidance, $rules[1]->changeGuidance);
        self::assertSame($definition->refs, $rules[1]->refs);
    }

    public function testMethodLevelRulesListTheMethodAsSubject(): void
    {
        $file = $this->dir . '/M.php';
        file_put_contents($file, "<?php\nnamespace Fx;\nuse ItpContext\\Attribute\\Rule;\nuse " . ArchitectureRules::class . ";\nfinal class M {\n    #[Rule(ArchitectureRules::I18n)]\n    public function t(): void {}\n}\n");

        $rules = (new ApplicableRuleResolver())->resolveFiles([$file]);

        self::assertSame(['Fx\\M::t'], $rules[0]->subjects);
    }

    public function testNoFilesOrUnannotatedFilesYieldNoRules(): void
    {
        $resolver = new ApplicableRuleResolver();

        self::assertSame([], $resolver->resolveFiles([]));
        self::assertSame([], $resolver->resolveFiles([$this->dir . '/missing.php']));
    }

    public function testUnresolvableRuleFailsLoudly(): void
    {
        $file = $this->dir . '/U.php';
        file_put_contents($file, "<?php\nnamespace Fx;\nuse ItpContext\\Attribute\\Rule;\nuse Does\\Not\\Exist;\n#[Rule(Exist::Nope)]\nfinal class U {}\n");

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Does\\Not\\Exist::Nope');

        (new ApplicableRuleResolver())->resolveFiles([$file]);
    }

    private function write(string $name, string $class, string $enum, string $case): string
    {
        $short = substr($enum, (int) strrpos($enum, '\\') + 1);
        $file = $this->dir . '/' . $name;
        file_put_contents($file, "<?php\nnamespace Fx;\nuse ItpContext\\Attribute\\Rule;\nuse {$enum};\n#[Rule({$short}::{$case})]\nfinal class {$class} {}\n");

        return $file;
    }
}
