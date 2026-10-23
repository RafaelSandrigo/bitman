<?php

declare(strict_types=1);

namespace App\Modules\Play\Application\Actions;

use App\Modules\Identity\Domain\Permission;
use App\Modules\Identity\Domain\User;
use App\Modules\Play\Domain\Score;
use App\Shared\Application\DomainEventPublisher;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Database\ConnectionInterface;

/** M20: mantém no ranking um score sinalizado depois da revisão (auditado). */
final readonly class KeepFlaggedScore
{
    public function __construct(
        private Gate $gate,
        private ConnectionInterface $db,
        private DomainEventPublisher $publisher,
    ) {}

    public function __invoke(User $actor, string $scoreId): Score
    {
        $this->gate->forUser($actor)->authorize(Permission::ModerateScores->value);

        return $this->db->transaction(function () use ($actor, $scoreId): Score {
            $score = Score::query()->lockForUpdate()->findOrFail($scoreId);
            $score->keep($actor);
            $this->publisher->publishFrom($score);

            return $score;
        });
    }
}
