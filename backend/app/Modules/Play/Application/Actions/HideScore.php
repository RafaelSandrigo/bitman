<?php

declare(strict_types=1);

namespace App\Modules\Play\Application\Actions;

use App\Modules\Identity\Domain\Permission;
use App\Modules\Identity\Domain\User;
use App\Modules\Play\Domain\Score;
use App\Modules\Versioning\Domain\DecisionReason;
use App\Shared\Application\DomainEventPublisher;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Database\ConnectionInterface;

/** Tira o score do ranking (S2), com motivo (I11). O registro continua. */
final readonly class HideScore
{
    public function __construct(
        private Gate $gate,
        private ConnectionInterface $db,
        private DomainEventPublisher $publisher,
    ) {}

    public function __invoke(User $actor, string $scoreId, DecisionReason $reason): Score
    {
        $this->gate->forUser($actor)->authorize(Permission::ModerateScores->value);

        return $this->db->transaction(function () use ($actor, $scoreId, $reason): Score {
            $score = Score::query()->lockForUpdate()->findOrFail($scoreId);
            $score->hide($actor, $reason);
            $this->publisher->publishFrom($score);

            return $score;
        });
    }
}
