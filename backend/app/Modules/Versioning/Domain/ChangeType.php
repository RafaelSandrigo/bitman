<?php

declare(strict_types=1);

namespace App\Modules\Versioning\Domain;

/** Diferença de um arquivo em relação à versão publicada do jogo. */
enum ChangeType: string
{
    case Added = 'added';
    case Modified = 'modified';
    case Unchanged = 'unchanged';

    public static function against(?string $publishedSha256, string $sha256): self
    {
        return match (true) {
            $publishedSha256 === null => self::Added,
            hash_equals($publishedSha256, $sha256) => self::Unchanged,
            default => self::Modified,
        };
    }
}
