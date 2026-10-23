<?php

declare(strict_types=1);

namespace App\Modules\Play\Application\Actions;

use App\Modules\Play\Domain\GameSession;
use App\Modules\Play\Domain\GuestPlay;
use App\Modules\Play\Domain\SessionStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\ConnectionInterface;
use Psr\Log\LoggerInterface;

/**
 * M14: uma sessão aberta depois do prazo já não aceita score (I10); aqui o banco passa a dizer o
 * mesmo (`expired`, com o fim no prazo), o que também conta as partidas abandonadas. Partidas de
 * visitante sem score para salvar (abandonadas ou com o prazo de salvar vencido) são apagadas.
 */
final readonly class ExpireOverdueSessions
{
    public function __construct(
        private ConnectionInterface $db,
        private LoggerInterface $logger,
    ) {}

    /** @return array{sessions: int, guest_plays: int} */
    public function __invoke(): array
    {
        $now = CarbonImmutable::now();
        $sessions = GameSession::query()
            ->where('status', SessionStatus::Open)
            ->where('expires_at', '<=', $now)
            ->toBase()
            ->update(['status' => SessionStatus::Expired->value, 'ended_at' => $this->db->raw('expires_at')]);
        $guestPlays = GuestPlay::query()
            ->whereNull('claimed_score_id')
            ->where(fn ($query) => $query
                ->where(fn ($abandoned) => $abandoned->whereNull('value')->where('expires_at', '<=', $now))
                ->orWhere('claim_expires_at', '<=', $now))
            ->delete();

        $this->logger->info('play.sessions_expired', ['sessions' => $sessions, 'guest_plays' => $guestPlays]);

        return ['sessions' => $sessions, 'guest_plays' => (int) $guestPlays];
    }
}
