<?php

declare(strict_types=1);

namespace ItpContext\Service;

use ItpContext\Model\ChangeNotice;
use RuntimeException;

/**
 * Local-only memory of already shown notices, kept outside the repository.
 * A notice id covers rule, symbol, base, normalized change and guidance, so
 * a changed implementation or changed guidance is a new, unseen notice.
 */
final class NoticeAcknowledgements
{
    public function __construct(private string $file)
    {
    }

    public static function forRepository(string $stateDir, string $repoDir): self
    {
        $resolved = realpath($repoDir);
        $repoKey = sha1($resolved === false ? $repoDir : $resolved);

        return new self(rtrim($stateDir, '/') . '/itp-context/' . $repoKey . '.json');
    }

    /**
     * Returns the notices not shown before and records them as shown.
     *
     * @param list<ChangeNotice> $notices
     * @return list<ChangeNotice>
     */
    public function consume(array $notices): array
    {
        $seen = $this->load();
        $unseen = array_values(array_filter($notices, static fn (ChangeNotice $notice): bool => !isset($seen[$notice->id])));

        if ($unseen !== []) {
            foreach ($unseen as $notice) {
                $seen[$notice->id] = true;
            }
            $this->save($seen);
        }

        return $unseen;
    }

    /**
     * @return array<string, true>
     */
    private function load(): array
    {
        if (!is_file($this->file)) {
            return [];
        }

        $decoded = json_decode((string) file_get_contents($this->file), true);
        if (!is_array($decoded) || !isset($decoded['seen']) || !is_array($decoded['seen'])) {
            throw new RuntimeException("Acknowledgement file {$this->file} is corrupt; delete it to reset.");
        }

        $seen = [];
        foreach (array_keys($decoded['seen']) as $id) {
            $seen[(string) $id] = true;
        }

        return $seen;
    }

    /**
     * @param array<string, true> $seen
     */
    private function save(array $seen): void
    {
        $dir = dirname($this->file);
        if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
            throw new RuntimeException("Cannot create acknowledgement directory {$dir}.");
        }

        ksort($seen);
        $temporary = $this->file . '.' . getmypid() . '.tmp';
        if (file_put_contents($temporary, json_encode(['seen' => $seen], JSON_THROW_ON_ERROR)) === false || !rename($temporary, $this->file)) {
            throw new RuntimeException("Cannot write acknowledgement file {$this->file}.");
        }
    }
}
