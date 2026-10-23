<?php

declare(strict_types=1);

namespace App\Modules\Play\Infrastructure\Listeners;

use App\Modules\Catalog\Domain\Game;
use App\Modules\Identity\Domain\Permission;
use App\Modules\Identity\Domain\User;
use App\Modules\Play\Domain\Events\ScoreRecorded;
use App\Modules\Play\Infrastructure\Notifications\FlaggedScoreNotification;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;
use Illuminate\Support\Facades\Notification;

/**
 * M21: avisa por e-mail quem modera scores (S2). No máximo um e-mail por jogo por hora, para uma
 * rajada de scores forjados não virar spam; o painel mostra o total em revisão.
 */
final readonly class NotifyModeratorsOfFlaggedScore implements ShouldHandleEventsAfterCommit
{
    private const THROTTLE_SECONDS = 3600;

    public function __construct(private Repository $cache) {}

    public function handle(ScoreRecorded $event): void
    {
        if (! $event->flagged || ! $this->cache->add("flagged-score-mail:{$event->gameId}", true, self::THROTTLE_SECONDS)) {
            return;
        }

        $game = Game::query()->find($event->gameId);
        $moderators = User::permission(Permission::ModerateScores->value)->whereNotNull('email_verified_at')->get();
        if ($game === null || $moderators->isEmpty()) {
            return;
        }

        Notification::send($moderators, new FlaggedScoreNotification($game->name, $event->value));
    }
}
