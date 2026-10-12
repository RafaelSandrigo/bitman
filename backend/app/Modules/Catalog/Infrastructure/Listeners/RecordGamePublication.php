<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Infrastructure\Listeners;

use App\Modules\Catalog\Domain\Game;
use App\Modules\Versioning\Domain\Events\VersionPublished;

/** O jogo passa a apontar para a versão publicada, na mesma transação da publicação (I8, I9). */
final readonly class RecordGamePublication
{
    public function handle(VersionPublished $event): void
    {
        Game::query()->findOrFail($event->gameId)->recordPublication($event->versionId);
    }
}
