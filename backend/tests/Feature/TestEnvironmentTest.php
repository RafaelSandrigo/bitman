<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;

// Trava de segurança: o projeto roda no servidor de produção, então os testes
// NUNCA podem tocar o banco da aplicação (docs/convencoes.md).
it('roda os testes no banco PostgreSQL dedicado bitman_test', function (): void {
    expect(DB::connection()->getDriverName())->toBe('pgsql')
        ->and(DB::connection()->getDatabaseName())->toBe('bitman_test')
        ->and(app()->environment())->toBe('testing');
});
