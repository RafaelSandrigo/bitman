<?php

declare(strict_types=1);

it('expõe o healthcheck em /up', function (): void {
    $this->get('/up')->assertOk();
});

it('não serve páginas web: a SPA é servida pelo nginx', function (): void {
    $this->get('/')->assertNotFound();
});

it('responde JSON 401 na API para quem não está autenticado', function (): void {
    $this->getJson('/api/user')->assertUnauthorized()->assertJsonStructure(['message']);
});
