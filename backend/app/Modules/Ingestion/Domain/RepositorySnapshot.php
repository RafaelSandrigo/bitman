<?php

declare(strict_types=1);

namespace App\Modules\Ingestion\Domain;

use App\Modules\Ingestion\Domain\Exceptions\InvalidSnapshot;
use App\Shared\Domain\Git\CommitSha;

/**
 * O estado do repositório obtido no fetch: o commit resolvido, a árvore inteira e o conteúdo
 * apenas dos paths pedidos. O construtor confere que cada blob entregue é exatamente o objeto
 * Git que a árvore aponta (hash do objeto e tamanho): a sandbox não consegue trocar conteúdo.
 */
final readonly class RepositorySnapshot
{
    /** @var array<string, TreeEntry> */
    private array $byPath;

    /**
     * @param  list<TreeEntry>  $entries
     * @param  array<string, string>  $blobs  path => conteúdo
     */
    public function __construct(
        public CommitSha $commit,
        public array $entries,
        public array $blobs,
    ) {
        $byPath = [];
        foreach ($entries as $entry) {
            if (isset($byPath[$entry->path])) {
                throw new InvalidSnapshot('caminho repetido na árvore');
            }
            $byPath[$entry->path] = $entry;
        }
        $this->byPath = $byPath;

        foreach ($blobs as $path => $content) {
            $entry = $byPath[$path] ?? null;
            if ($entry === null || $entry->type !== 'blob') {
                throw new InvalidSnapshot('blob sem entrada correspondente na árvore');
            }
            if ($entry->size !== strlen($content) || ! hash_equals($entry->objectId, self::gitBlobId($content, strlen($entry->objectId)))) {
                throw new InvalidSnapshot('blob diferente do objeto da árvore');
            }
        }
    }

    public function entry(string $path): ?TreeEntry
    {
        return $this->byPath[$path] ?? null;
    }

    public function blob(string $path): ?string
    {
        return $this->blobs[$path] ?? null;
    }

    /** Id do objeto blob no Git: hash de "blob {tamanho}\0{conteúdo}" (SHA-1, ou SHA-256 nos repositórios sha256). */
    public static function gitBlobId(string $content, int $hexLength = 40): string
    {
        return hash($hexLength === 64 ? 'sha256' : 'sha1', 'blob '.strlen($content)."\0".$content);
    }
}
