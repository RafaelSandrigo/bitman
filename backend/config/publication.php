<?php

declare(strict_types=1);

/*
| Publicação (Fase 5, docs/arquitetura.md#publicação). `artifacts_path` é o volume `artifacts`:
| `archive/` (write-once, permanente) e `served/` (só o que a origem de jogos expõe).
*/
return [
    'artifacts_path' => env('BITMAN_ARTIFACTS_PATH', '/var/bitman/artifacts'),

    // Origem isolada dos jogos (ADR 0004). A URL de jogo é sempre {origem}/{publicId}/index.html.
    'games_origin' => rtrim((string) env('GAMES_ORIGIN', 'https://bitman-games.sandrigo.com.br'), '/'),
];
