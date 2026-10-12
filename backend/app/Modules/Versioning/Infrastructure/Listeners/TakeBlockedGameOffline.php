<?php

declare(strict_types=1);

namespace App\Modules\Versioning\Infrastructure\Listeners;

use App\Modules\Catalog\Domain\Events\GameBlocked;
use App\Modules\Versioning\Domain\ArtifactStore;
use App\Modules\Versioning\Domain\GameVersion;

/**
 * J6, C2: o bloqueio tira a versão publicada do ar dentro da transação do bloqueio. Se falhar,
 * o bloqueio inteiro é desfeito (nunca "bloqueado no banco, mas servido").
 */
final readonly class TakeBlockedGameOffline
{
    public function __construct(private ArtifactStore $artifacts) {}

    public function handle(GameBlocked $event): void
    {
        $publicId = $event->publishedVersionId === null ? null : GameVersion::query()->whereKey($event->publishedVersionId)->value('public_id');
        if (is_string($publicId)) {
            $this->artifacts->unserve($publicId);
        }
    }
}
