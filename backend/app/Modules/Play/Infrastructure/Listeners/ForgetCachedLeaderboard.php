<?php

declare(strict_types=1);

namespace App\Modules\Play\Infrastructure\Listeners;

use App\Modules\Play\Domain\Events\ScoreHidden;
use App\Modules\Play\Domain\Events\ScoreKept;
use App\Modules\Play\Domain\Events\ScoreRecorded;
use App\Modules\Play\Domain\Events\ScoreRestored;
use App\Modules\Play\Infrastructure\CachedLeaderboard;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

/** Depois do commit: antes dele, uma leitura concorrente regravaria o ranking sem o novo score. */
final readonly class ForgetCachedLeaderboard implements ShouldHandleEventsAfterCommit
{
    public function __construct(private CachedLeaderboard $leaderboard) {}

    public function handle(ScoreRecorded|ScoreHidden|ScoreRestored|ScoreKept $event): void
    {
        $this->leaderboard->forget($event->gameId);
    }
}
