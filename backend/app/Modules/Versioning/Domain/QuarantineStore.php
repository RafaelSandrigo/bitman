<?php

declare(strict_types=1);

namespace App\Modules\Versioning\Domain;

/**
 * Blobs de versões ainda não publicadas. Nunca é servida. Os caminhos são montados só com
 * nossos IDs e o SHA-256 do conteúdo, nunca com nomes vindos do Git (G6).
 */
interface QuarantineStore
{
    /** Grava o conteúdo e devolve o SHA-256 dele (hex). */
    public function put(string $versionId, string $contents): string;

    public function get(string $versionId, string $sha256): string;

    /** Apaga tudo da versão (busca que falhou no meio). */
    public function purge(string $versionId): void;
}
