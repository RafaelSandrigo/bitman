<?php

declare(strict_types=1);

namespace App\Modules\Play\Infrastructure;

use App\Modules\Play\Domain\AnomalyPolicy;
use App\Modules\Play\Domain\Events\ScoreHidden;
use App\Modules\Play\Domain\Events\ScoreKept;
use App\Modules\Play\Domain\Events\ScoreRecorded;
use App\Modules\Play\Domain\Events\ScoreRestored;
use App\Modules\Play\Domain\Leaderboard;
use App\Modules\Play\Domain\PlayRules;
use App\Modules\Play\Domain\ScoreDistribution;
use App\Modules\Play\Infrastructure\Listeners\ForgetCachedLeaderboard;
use App\Modules\Play\Infrastructure\Listeners\NotifyModeratorsOfFlaggedScore;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

final class PlayServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PlayRules::class, fn (Application $app): PlayRules => new PlayRules(
            sessionTtlSeconds: (int) $app->make('config')->get('play.session_ttl_seconds'),
            minSessionSeconds: (int) $app->make('config')->get('play.min_session_seconds'),
            guestClaimTtlSeconds: (int) $app->make('config')->get('play.guest_claim_ttl_seconds'),
        ));
        $this->app->singleton(AnomalyPolicy::class, fn (Application $app): AnomalyPolicy => new AnomalyPolicy(
            factor: (float) $app->make('config')->get('play.anomaly.factor'),
            minSample: (int) $app->make('config')->get('play.anomaly.min_sample'),
        ));
        $this->app->bind(ScoreDistribution::class, SqlScoreDistribution::class);
        $this->app->bind(CachedLeaderboard::class, fn (Application $app): CachedLeaderboard => new CachedLeaderboard(
            $app->make(SqlLeaderboard::class),
            $app->make('cache.store'),
            (int) $app->make('config')->get('play.leaderboard_cache_seconds'),
        ));
        $this->app->bind(Leaderboard::class, CachedLeaderboard::class);
    }

    public function boot(): void
    {
        Event::listen([ScoreRecorded::class, ScoreHidden::class, ScoreRestored::class, ScoreKept::class], ForgetCachedLeaderboard::class);
        Event::listen(ScoreRecorded::class, NotifyModeratorsOfFlaggedScore::class);

        // S2: limite por jogador e jogo nas partidas (cada uma aceita um score) e por jogador nos envios.
        RateLimiter::for('game-sessions', function (Request $request): array {
            $user = (string) $request->user()?->getAuthIdentifier();

            return [
                Limit::perHour((int) config('play.sessions_per_hour_per_game'))->by('sessions|'.$user.'|'.(string) $request->route('slug')),
                Limit::perMinute(20)->by('sessions|'.$user),
            ];
        });
        // Sem conta, o limite é por IP: cada partida de visitante aceita um score pendente.
        RateLimiter::for('guest-plays', fn (Request $request): Limit => Limit::perHour((int) config('play.guest_plays_per_hour'))->by('guest-plays|'.$request->ip()));
        RateLimiter::for('guest-scores', fn (Request $request): Limit => Limit::perMinute(20)->by('guest-scores|'.$request->ip()));
        RateLimiter::for('score-submissions', fn (Request $request): Limit => Limit::perMinute(20)->by('scores|'.$request->user()?->getAuthIdentifier()));
    }
}
