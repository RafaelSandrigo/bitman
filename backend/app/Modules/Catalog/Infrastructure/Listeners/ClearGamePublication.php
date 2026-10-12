<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Infrastructure\Listeners;

use App\Modules\Catalog\Domain\Game;
use App\Modules\Versioning\Domain\Events\VersionWithdrawn;

/** A versão publicada foi retirada: o jogo sai da listagem até outra ser publicada (I9). */
final readonly class ClearGamePublication
{
    public function handle(VersionWithdrawn $event): void
    {
        Game::query()->findOrFail($event->gameId)->clearPublication($event->versionId);
    }
}
