<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Domain;

use App\Modules\Catalog\Domain\Exceptions\InvalidThumbnail;

/**
 * Porta para a imagem enviada pelo dono (A4): o conteúdo é verificado, decodificado e
 * re-codificado em WebP, sem metadados, com um nome aleatório. O arquivo original nunca é servido.
 */
interface ThumbnailProcessor
{
    /**
     * @return string nome do arquivo gravado (ex.: `3f9c….webp`)
     *
     * @throws InvalidThumbnail
     */
    public function store(string $uploadedPath): string;

    /** Remove uma thumbnail gravada (ex.: a submissão falhou depois do processamento). */
    public function discard(string $name): void;
}
