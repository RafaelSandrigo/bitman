<?php

declare(strict_types=1);

namespace App\Modules\Versioning\Application\Actions;

use App\Modules\Identity\Domain\Permission;
use App\Modules\Identity\Domain\User;
use App\Modules\Versioning\Domain\GameVersion;
use App\Shared\Application\DomainEventPublisher;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Database\ConnectionInterface;

/**
 * Portão 0 — autorização Git. A busca em si roda na fila `ingestion`, disparada pelo evento
 * ImportAuthorized depois do commit desta transação.
 */
final readonly class AuthorizeImport
{
    public function __construct(
        private Gate $gate,
        private ConnectionInterface $db,
        private DomainEventPublisher $publisher,
    ) {}

    public function __invoke(User $actor, string $versionId): GameVersion
    {
        $this->gate->forUser($actor)->authorize(Permission::AuthorizeImports->value);

        return $this->db->transaction(function () use ($actor, $versionId): GameVersion {
            // Trava a linha: dois admins (ou dois cliques) não autorizam a mesma versão duas vezes.
            $version = GameVersion::query()->lockForUpdate()->findOrFail($versionId);
            $version->authorizeImport($actor);
            $this->publisher->publishFrom($version);

            return $version;
        });
    }
}
