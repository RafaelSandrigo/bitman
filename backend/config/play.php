<?php

declare(strict_types=1);

/*
| Partidas, scores e ranking (Fase 6, docs/dominio.md#gamesession-e-score-play).
| O score vem do navegador e pode ser forjado: estes limites dificultam e sinalizam, não impedem
| (docs/seguranca.md#limitação-explícita-scores).
*/
return [
    // Prazo para enviar o score depois de iniciar a partida.
    'session_ttl_seconds' => (int) env('PLAY_SESSION_TTL_SECONDS', 2 * 60 * 60),

    // I10: duração mínima de uma partida para registrar score.
    'min_session_seconds' => (int) env('PLAY_MIN_SESSION_SECONDS', 5),

    // Partida de visitante: prazo para entrar (ou criar a conta e confirmar o e-mail) e salvar o score.
    'guest_claim_ttl_seconds' => (int) env('PLAY_GUEST_CLAIM_TTL_SECONDS', 24 * 60 * 60),

    // S2: partidas de visitante por hora para cada IP.
    'guest_plays_per_hour' => 30,

    // S2: sinaliza scores acima do p99 do jogo × fator, a partir de uma amostra mínima.
    'anomaly' => [
        'factor' => 3.0,
        'min_sample' => 20,
    ],

    // S2: partidas por hora para cada jogador em cada jogo.
    'sessions_per_hour_per_game' => 120,

    'leaderboard_cache_seconds' => 600,
];
