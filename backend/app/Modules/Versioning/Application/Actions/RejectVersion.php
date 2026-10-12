<?php

declare(strict_types=1);

namespace App\Modules\Versioning\Application\Actions;

use App\Modules\Identity\Domain\Permission;
use App\Modules\Identity\Domain\User;
use App\Modules\Versioning\Domain\DecisionReason;
use App\Modules\Versioning\Domain\GameVersion;
use App\Modules\Versioning\Domain\QuarantineStore;
use App\Shared\Application\DomainEventPublisher;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Database\ConnectionInterface;

/** Portão 2 — o admin rejeita a versão inteira, com motivo (I11). A quarentena é descartada. */
final readonly class RejectVersion
{
    public function __construct(
        private Gate $gate,
        private ConnectionInterface $db,
        private DomainEventPublisher $publisher,
        private QuarantineStore $quarantine,
    ) {}

    public function __invoke(User $actor, string $versionId, DecisionReason $reason): GameVersion
    {
        $this->gate->forUser($actor)->authorize(Permission::ReviewFiles->value);

        $version = $this->db->transaction(function () use ($actor, $versionId, $reason): GameVersion {
            $version = GameVersion::query()->lockForUpdate()->findOrFail($versionId);
            $version->rejectVersion($actor, $reason);
            $this->publisher->publishFrom($version);

            return $version;
        });
        $this->quarantine->purge($version->id);

        return $version;
    }
}
