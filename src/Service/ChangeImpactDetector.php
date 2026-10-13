<?php

declare(strict_types=1);

namespace ItpContext\Service;

use ItpContext\Model\ChangeNotice;
use ItpContext\Model\RuleArm;
use ItpContext\Model\RuleTarget;
use RuntimeException;

/**
 * Compares a trusted Git base with the working tree (or a proposed revision) and
 * reports advisory notices for changed protected code. Annotations and rule
 * definitions are always read from the base, so a change cannot remove its own protection.
 */
final class ChangeImpactDetector
{
    public function __construct(
        private TokenParser $parser = new TokenParser(),
        private RuleArmScanner $armScanner = new RuleArmScanner(),
    ) {
    }

    /**
     * @param string|null $headRef proposed revision; null compares against the working tree
     * @return list<ChangeNotice> ordered by id, deterministic for a given base and change
     */
    public function detect(string $repoDir, string $baseRef, ?string $headRef = null): array
    {
        $baseSha = trim($this->git($repoDir, ['rev-parse', '--verify', '--quiet', $baseRef . '^{commit}']));
        $diffArguments = $headRef === null ? [$baseSha] : [$baseSha, $headRef];
        $changes = $this->changedPhpFiles($this->git($repoDir, ['diff', '--name-status', '--no-renames', '-z', ...$diffArguments]));
        $baselineArms = $this->baselineArms($repoDir, $baseSha);

        $notices = [];
        foreach ($changes as [$status, $path]) {
            if ($status === 'A') {
                continue;
            }

            $baseCode = $this->git($repoDir, ['show', $baseSha . ':' . $path]);
            $currentCode = $status === 'D' ? '' : $this->currentCode($repoDir, $headRef, $path);

            foreach ($this->symbolNotices($baseSha, $path, $baseCode, $currentCode, $baselineArms) as $notice) {
                $notices[$notice->id] = $notice;
            }
            foreach ($this->definitionNotices($baseSha, $path, $baseCode, $currentCode) as $notice) {
                $notices[$notice->id] = $notice;
            }
        }

        ksort($notices);

        return array_values($notices);
    }

    /**
     * @param list<ChangeNotice> $notices
     */
    public function toNoticesJson(array $notices): string
    {
        return json_encode(
            ['notices' => array_map(static fn (ChangeNotice $notice): array => $notice->toArray(), $notices)],
            JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES,
        ) . "\n";
    }

    /**
     * @param array<string, RuleArm> $baselineArms
     * @return list<ChangeNotice>
     */
    private function symbolNotices(string $baseSha, string $path, string $baseCode, string $currentCode, array $baselineArms): array
    {
        $current = [];
        foreach ($this->parser->getRuleTargetsFromCode($currentCode) as $target) {
            $current[$target->kind . '|' . $target->fqcn] = $target;
        }

        $notices = [];
        foreach ($this->parser->getRuleTargetsFromCode($baseCode) as $target) {
            if (!$target->hasRules()) {
                continue;
            }

            $now = $current[$target->kind . '|' . $target->fqcn] ?? null;
            foreach ($target->ruleIds as $ruleId) {
                $kind = $this->symbolChangeKind($target, $now, $ruleId);
                if ($kind === null) {
                    continue;
                }

                $arm = $baselineArms[$ruleId] ?? null;
                $notices[] = $this->notice(
                    $baseSha,
                    $kind,
                    $ruleId,
                    $target->fqcn,
                    $path,
                    $arm === null ? $ruleId : ($arm->statement ?? $ruleId),
                    $arm === null ? null : $arm->changeGuidance,
                    $arm === null ? [] : $arm->refs,
                    $kind === 'modified' && $now !== null ? $now->fingerprint : '',
                );
            }
        }

        return $notices;
    }

    /**
     * @return 'modified'|'removed'|'annotation-removed'|null
     */
    private function symbolChangeKind(RuleTarget $base, ?RuleTarget $now, string $ruleId): ?string
    {
        if ($now === null) {
            return 'removed';
        }

        if (!in_array($ruleId, $now->ruleIds, true)) {
            return 'annotation-removed';
        }

        return $now->fingerprint !== $base->fingerprint ? 'modified' : null;
    }

    /**
     * @return list<ChangeNotice>
     */
    private function definitionNotices(string $baseSha, string $path, string $baseCode, string $currentCode): array
    {
        $currentArms = $this->armScanner->scan($currentCode);
        $notices = [];

        foreach ($this->armScanner->scan($baseCode) as $ruleId => $arm) {
            $now = $currentArms[$ruleId] ?? null;
            if ($now !== null && $now->fingerprint === $arm->fingerprint) {
                continue;
            }

            $notices[] = $this->notice(
                $baseSha,
                $now === null ? 'rule-removed' : 'rule-definition-changed',
                $ruleId,
                $ruleId,
                $path,
                $arm->statement ?? $ruleId,
                $now !== null ? $now->changeGuidance : $arm->changeGuidance,
                $now !== null ? $now->refs : $arm->refs,
                $now !== null ? $now->fingerprint : '',
            );
        }

        return $notices;
    }

    /**
     * @param 'modified'|'removed'|'annotation-removed'|'rule-definition-changed'|'rule-removed' $kind
     * @param list<string> $refs
     */
    private function notice(
        string $baseSha,
        string $kind,
        string $ruleId,
        string $subject,
        string $path,
        string $statement,
        ?string $guidance,
        array $refs,
        string $change,
    ): ChangeNotice {
        return new ChangeNotice(
            id: substr(sha1(implode("\0", [$kind, $ruleId, $subject, $baseSha, $change, $guidance ?? ''])), 0, 20),
            kind: $kind,
            ruleId: $ruleId,
            subject: $subject,
            sourcePath: $path,
            statement: $statement,
            guidance: $guidance,
            refs: $refs,
        );
    }

    /**
     * @return array<string, RuleArm>
     */
    private function baselineArms(string $repoDir, string $baseSha): array
    {
        $arms = [];
        $listing = $this->git($repoDir, ['grep', '-l', '-z', '--fixed-strings', 'RuleDef', $baseSha, '--', '*.php'], [0, 1]);

        foreach (array_filter(explode("\0", $listing)) as $entry) {
            $arms += $this->armScanner->scan($this->git($repoDir, ['show', $entry]));
        }

        return $arms;
    }

    /**
     * @return list<array{0:string,1:string}>
     */
    private function changedPhpFiles(string $nameStatus): array
    {
        $parts = explode("\0", $nameStatus);
        $files = [];

        for ($index = 0; $index + 1 < count($parts); $index += 2) {
            if (str_ends_with($parts[$index + 1], '.php')) {
                $files[] = [$parts[$index], $parts[$index + 1]];
            }
        }

        return $files;
    }

    private function currentCode(string $repoDir, ?string $headRef, string $path): string
    {
        if ($headRef !== null) {
            return $this->git($repoDir, ['show', $headRef . ':' . $path]);
        }

        $code = @file_get_contents(rtrim($repoDir, '/') . '/' . $path);
        if ($code === false) {
            throw new RuntimeException("Cannot read {$path}.");
        }

        return $code;
    }

    /**
     * @param list<string> $arguments
     * @param list<int> $okExitCodes
     */
    private function git(string $repoDir, array $arguments, array $okExitCodes = [0]): string
    {
        $process = proc_open(
            ['git', '-C', $repoDir, ...$arguments],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
        );
        if (!is_resource($process)) {
            throw new RuntimeException('Cannot start git.');
        }

        $stdout = (string) stream_get_contents($pipes[1]);
        $stderr = (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        if (!in_array($exitCode, $okExitCodes, true)) {
            throw new RuntimeException(sprintf('git %s failed: %s', $arguments[0], trim($stderr) !== '' ? trim($stderr) : 'ref or path not found'));
        }

        return $stdout;
    }
}
