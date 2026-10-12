<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\Actions;

use App\Modules\Catalog\Application\SubmitGameInput;
use App\Modules\Catalog\Domain\Exceptions\SlugAlreadyTaken;
use App\Modules\Catalog\Domain\Game;
use App\Modules\Catalog\Domain\Slug;
use App\Modules\Catalog\Domain\ThumbnailProcessor;
use App\Modules\Identity\Domain\Permission;
use App\Modules\Identity\Domain\User;
use App\Shared\Application\DomainEventPublisher;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Database\ConnectionInterface;
use Throwable;

/**
 * O developer submete o jogo. Na mesma transação nascem o Game (`submitted`) e, pelo evento
 * GameSubmitted, a versão 1 (`import_requested`), ambos auditados. Nenhum acesso à rede (I2).
 */
final readonly class SubmitGame
{
    /** Tentativas de sufixo (`snake-2`, `snake-3`…) antes de desistir. */
    private const MAX_SLUG_ATTEMPTS = 50;

    public function __construct(
        private Gate $gate,
        private ConnectionInterface $db,
        private DomainEventPublisher $publisher,
        private ThumbnailProcessor $thumbnails,
    ) {}

    public function __invoke(User $owner, SubmitGameInput $input): Game
    {
        $this->gate->forUser($owner)->authorize(Permission::SubmitGames->value);

        $thumbnail = $this->thumbnails->store($input->thumbnailPath);

        try {
            return $this->db->transaction(function () use ($owner, $input, $thumbnail): Game {
                $game = Game::submit(
                    owner: $owner,
                    name: $input->name,
                    description: $input->description,
                    authorName: $input->authorName,
                    slug: $this->availableSlug(Slug::fromName($input->name)),
                    thumbnailPath: $thumbnail,
                    repositoryUrl: $input->repositoryUrl,
                    defaultRef: $input->ref,
                );
                $this->publisher->publishFrom($game);

                return $game;
            });
        } catch (Throwable $e) {
            $this->thumbnails->discard($thumbnail);

            throw $e;
        }
    }

    private function availableSlug(Slug $base): Slug
    {
        for ($attempt = 1; $attempt <= self::MAX_SLUG_ATTEMPTS; $attempt++) {
            $candidate = $attempt === 1 ? $base : $base->withSuffix($attempt);
            if (! Game::query()->where('slug', $candidate->value)->exists()) {
                return $candidate;
            }
        }

        throw SlugAlreadyTaken::for($base);
    }
}
