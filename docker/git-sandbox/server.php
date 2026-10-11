<?php

declare(strict_types=1);

// Stub da Fase 0: só responde ao healthcheck. A busca de repositórios entra na Fase 3.
header('Content-Type: application/json');

if (($_SERVER['REQUEST_URI'] ?? '') === '/health') {
    echo json_encode(['status' => 'ok']);

    return;
}

http_response_code(501);
echo json_encode(['error' => 'not_implemented']);
