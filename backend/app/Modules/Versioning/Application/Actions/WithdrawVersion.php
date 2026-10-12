<?php

declare(strict_types=1);

namespace App\Modules\Versioning\Application\Actions;

use App\Modules\Identity\Domain\Permission;
use App\Modules\Identity\Domain\User;
use App\Modules\Versioning\Domain\ArtifactStore;
use App\Modules\Versioning\Domain\DecisionReason;
use App\Modules\Versioning\Domain\GameVersion;
use App\Shared\Application\DomainEventPublisher;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Database\ConnectionInterface;

/**
 * J6: o admin retira a versão publicada, com motivo (I11). Ela sai do ar dentro da transação:
 * numa falha, fica fora do ar (nunca "retirada no banco, mas servida"). O archive é permanente.
 */
final readonly class WithdrawVersion
{
    public function __construct(
        private Gate $gate,
        private ConnectionInterface $db,
        private DomainEventPublisher $publisher,
        private ArtifactStore $artifacts,
    ) {}

    public function __invoke(User $actor, string $versionId, DecisionReason $reason): GameVersion
    {
        $this->gate->forUser($actor)->authorize(Permission::PublishVersions->value);

        return $this->db->transaction(function () use ($actor, $versionId, $reason): GameVersion {
            $version = GameVersion::query()->lockForUpdate()->findOrFail($versionId);
            $version->withdraw($actor, $reason);
            $this->publisher->publishFrom($version);
            $this->artifacts->unserve((string) $version->public_id);

            return $version;
        });
    }
}
