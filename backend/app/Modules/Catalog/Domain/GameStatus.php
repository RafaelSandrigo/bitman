<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Domain;

/** submitted → published ⇄ blocked; rejected quando a primeira versão é rejeitada (docs/dominio.md). */
enum GameStatus: string
{
    case Submitted = 'submitted';
    case Published = 'published';
    case Blocked = 'blocked';
    case Rejected = 'rejected';

    /** A URL pública (`/games/{slug}`) passa a existir na primeira publicação e não pode mais quebrar. */
    public function wasPublished(): bool
    {
        return $this === self::Published || $this === self::Blocked;
    }
}
