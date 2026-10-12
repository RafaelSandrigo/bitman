<?php

declare(strict_types=1);

namespace App\Modules\Versioning\Application\Actions;

use App\Modules\Identity\Domain\Permission;
use App\Modules\Identity\Domain\User;
use App\Modules\Versioning\Domain\GameVersion;
use App\Shared\Application\DomainEventPublisher;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Database\ConnectionInterface;

/** Portão 1 — o admin aprova o commit buscado; a versão segue para a revisão dos arquivos. */
final readonly class ApproveCommit
{
    public function __construct(
        private Gate $gate,
        private ConnectionInterface $db,
        private DomainEventPublisher $publisher,
    ) {}

    public function __invoke(User $actor, string $versionId): GameVersion
    {
        $this->gate->forUser($actor)->authorize(Permission::ApproveCommits->value);

        return $this->db->transaction(function () use ($actor, $versionId): GameVersion {
            $version = GameVersion::query()->lockForUpdate()->findOrFail($versionId);
            $version->approveCommit($actor);
            $this->publisher->publishFrom($version);

            return $version;
        });
    }
}
