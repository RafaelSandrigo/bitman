<?php

declare(strict_types=1);

namespace App\Modules\Versioning\Domain;

/**
 * Artefatos publicados (docs/arquitetura.md#publicação). Só nossos IDs formam caminhos: ULID do
 * jogo, número da versão, um path da allowlist e o `publicId` (UUID).
 *
 * - `archive/`: write-once e permanente (I7). Fonte para voltar a servir depois de um desbloqueio.
 * - `served/{publicId}/`: o que a origem de jogos expõe (J6). Aparece e some de uma vez (rename).
 */
interface ArtifactStore
{
    /**
     * I7: grava a versão no arquivo permanente. Se o destino já existe, falha sem tocar em nada.
     *
     * @param  array<string, string>  $files  path => conteúdo
     *
     * @throws Exceptions\ArtifactAlreadyExists
     */
    public function archive(string $gameId, int $number, array $files): void;

    /**
     * @param  list<string>  $paths
     * @return array<string, string> path => conteúdo
     */
    public function readArchive(string $gameId, int $number, array $paths): array;

    /**
     * Desfaz um `archive()` cuja publicação não chegou a ser confirmada no banco. Nunca é usado
     * numa versão publicada.
     */
    public function discardUncommittedArchive(string $gameId, int $number): void;

    /**
     * Passa a servir os arquivos em `served/{publicId}/` (diretórios 0755, arquivos 0644).
     *
     * @param  array<string, string>  $files  path => conteúdo
     *
     * @throws Exceptions\ArtifactAlreadyExists
     */
    public function serve(string $publicId, array $files): void;

    /** Deixa de servir na hora (idempotente). */
    public function unserve(string $publicId): void;

    public function isServed(string $publicId): bool;

    /**
     * M12: o que está em `served/{publicId}/` agora; arquivo ausente vem como `null`.
     *
     * @param  list<string>  $paths
     * @return array<string, string|null>
     */
    public function readServed(string $publicId, array $paths): array;

    /**
     * M12: os `publicId` com diretório em `served/` (só nomes no formato UUID).
     *
     * @return list<string>
     */
    public function servedIds(): array;
}
