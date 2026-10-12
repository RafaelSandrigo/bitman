<?php

declare(strict_types=1);

namespace App\Modules\Versioning\Application\Actions;

use App\Modules\Identity\Domain\Permission;
use App\Modules\Identity\Domain\User;
use App\Modules\Versioning\Domain\DecisionReason;
use App\Modules\Versioning\Domain\GameVersion;
use App\Shared\Application\DomainEventPublisher;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Database\ConnectionInterface;

/** Portão 0 — o admin recusa o acesso ao repositório, com motivo (I11). */
final readonly class RejectImport
{
    public function __construct(
        private Gate $gate,
        private ConnectionInterface $db,
        private DomainEventPublisher $publisher,
    ) {}

    public function __invoke(User $actor, string $versionId, DecisionReason $reason): GameVersion
    {
        $this->gate->forUser($actor)->authorize(Permission::AuthorizeImports->value);

        return $this->db->transaction(function () use ($actor, $versionId, $reason): GameVersion {
            $version = GameVersion::query()->lockForUpdate()->findOrFail($versionId);
            $version->rejectImport($actor, $reason);
            $this->publisher->publishFrom($version);

            return $version;
        });
    }
}
