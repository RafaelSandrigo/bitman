<?php

declare(strict_types=1);

namespace App\Modules\Versioning\Application\Actions;

use App\Modules\Catalog\Domain\Game;
use App\Modules\Catalog\Domain\GameStatus;
use App\Modules\Identity\Domain\Permission;
use App\Modules\Identity\Domain\User;
use App\Modules\Versioning\Domain\ArtifactStore;
use App\Modules\Versioning\Domain\Exceptions\GameIsBlocked;
use App\Modules\Versioning\Domain\Exceptions\PublishedContentMismatch;
use App\Modules\Versioning\Domain\GameVersion;
use App\Modules\Versioning\Domain\QuarantineStore;
use App\Modules\Versioning\Domain\VersionStatus;
use App\Shared\Application\DomainEventPublisher;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Str;
use Throwable;

/**
 * Publicação (docs/arquitetura.md#publicação), numa transação com o jogo travado:
 * I6 (todos aprovados + SHA-256 recalculado da quarentena = hash aprovado) → `archive` write-once (I7)
 * → `served/{publicId}` → versão anterior `superseded` e fora do ar (I8) → `VersionPublished`.
 *
 * Se algo falha antes do commit, o que esta publicação gravou é desfeito. Tirar a anterior do ar
 * acontece dentro da transação: numa falha, o jogo fica fora do ar, nunca servindo o que não deveria.
 */
final readonly class PublishVersion
{
    public function __construct(
        private Gate $gate,
        private ConnectionInterface $db,
        private DomainEventPublisher $publisher,
        private QuarantineStore $quarantine,
        private ArtifactStore $artifacts,
        private ExceptionHandler $exceptions,
    ) {}

    public function __invoke(User $actor, string $versionId): GameVersion
    {
        $this->gate->forUser($actor)->authorize(Permission::PublishVersions->value);

        $publicId = Str::uuid()->toString();
        $archived = null; // [gameId, number] depois de gravar o archive
        $served = false;

        try {
            $version = $this->db->transaction(function () use ($actor, $versionId, $publicId, &$archived, &$served): GameVersion {
                $version = GameVersion::query()->lockForUpdate()->findOrFail($versionId);
                $game = Game::query()->lockForUpdate()->findOrFail($version->game_id);
                if ($game->status === GameStatus::Blocked) {
                    throw new GameIsBlocked;
                }
                $version->assertPublishable();

                $contents = $this->verifiedContents($version);
                $previous = GameVersion::query()->lockForUpdate()
                    ->where('game_id', $version->game_id)->where('status', VersionStatus::Published)->first();

                $this->artifacts->archive($version->game_id, $version->number, $contents);
                $archived = [$version->game_id, $version->number];
                $this->artifacts->serve($publicId, $contents);
                $served = true;

                // A anterior sai primeiro: o índice parcial só admite uma publicada por jogo (I8).
                if ($previous !== null) {
                    $previous->supersede($actor, $version);
                    $this->publisher->publishFrom($previous);
                    $this->artifacts->unserve((string) $previous->public_id);
                }

                $version->publish($actor, $publicId);
                $this->publisher->publishFrom($version);

                return $version;
            });
        } catch (Throwable $e) {
            if ($served) {
                $this->artifacts->unserve($publicId);
            }
            if ($archived !== null) {
                $this->artifacts->discardUncommittedArchive(...$archived);
            }

            throw $e;
        }

        // O conteúdo já está no archive (permanente); a quarentena não é mais necessária.
        try {
            $this->quarantine->purge($version->id);
        } catch (Throwable $e) {
            $this->exceptions->report($e);
        }

        return $version;
    }

    /**
     * I6: lê da quarentena e recalcula o SHA-256 de cada arquivo. O hash registrado é o mesmo que o
     * admin aprovou (I5: a decisão só é aceita para ele).
     *
     * @return array<string, string> path => conteúdo
     */
    private function verifiedContents(GameVersion $version): array
    {
        $contents = [];
        foreach ($version->files as $file) {
            $content = $this->quarantine->get($version->id, $file->sha256);
            if (! hash_equals($file->sha256, hash('sha256', $content))) {
                throw new PublishedContentMismatch($file->path);
            }
            $contents[$file->path] = $content;
        }

        return $contents;
    }
}
