<?php

declare(strict_types=1);

namespace App\Modules\Versioning\Application\Actions;

use App\Modules\Catalog\Domain\Game;
use App\Modules\Catalog\Domain\GameStatus;
use App\Modules\Identity\Domain\Permission;
use App\Modules\Identity\Domain\User;
use App\Modules\Versioning\Domain\Exceptions\GameIsBlocked;
use App\Modules\Versioning\Domain\Exceptions\ImportAlreadyInProgress;
use App\Modules\Versioning\Domain\GameVersion;
use App\Modules\Versioning\Domain\VersionStatus;
use App\Shared\Application\DomainEventPublisher;
use App\Shared\Domain\Git\GitRef;
use App\Shared\Domain\Git\RepositoryUrl;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * Nova versão do jogo, a partir da URL e da ref atuais dele (ex.: depois de uma busca que falhou
 * ou de uma correção no repositório). Volta ao começo: Portão 0, sem acesso à rede (I2).
 *
 * Pedem o dono (reenvio, com a permissão de submeter) e o admin. Pedir não decide nada: a nova
 * versão passa por todos os portões de novo (I1).
 */
final readonly class RequestNewImport
{
    public function __construct(
        private Gate $gate,
        private ConnectionInterface $db,
        private DomainEventPublisher $publisher,
    ) {}

    public function __invoke(User $actor, string $gameId): GameVersion
    {
        $game = Game::findOrFail($gameId);
        $gate = $this->gate->forUser($actor);
        $allowed = $gate->allows(Permission::AuthorizeImports->value)
            || ($game->owner_id === $actor->id && $gate->allows(Permission::SubmitGames->value));
        if (! $allowed) {
            throw new AuthorizationException;
        }
        if ($game->status === GameStatus::Blocked) {
            throw new GameIsBlocked;
        }

        $inProgress = GameVersion::query()->where('game_id', $game->id)->whereIn('status', VersionStatus::inProgress())->exists();
        if ($inProgress) {
            throw new ImportAlreadyInProgress;
        }

        try {
            return $this->db->transaction(function () use ($actor, $game): GameVersion {
                $version = GameVersion::requestImport(
                    $game->id,
                    RepositoryUrl::fromString($game->repository_url),
                    GitRef::fromString($game->default_ref),
                    $actor->id,
                );
                $this->publisher->publishFrom($version);

                return $version;
            });
        } catch (UniqueConstraintViolationException) {
            // Outro pedido criou a versão entre a checagem e o commit (índice parcial / número).
            throw new ImportAlreadyInProgress;
        }
    }
}
