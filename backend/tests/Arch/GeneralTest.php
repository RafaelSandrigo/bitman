<?php

declare(strict_types=1);

arch('todo código da aplicação declara strict_types')
    ->expect('App')
    ->toUseStrictTypes();

arch('sem funções de depuração esquecidas')
    ->expect(['dd', 'dump', 'ddd', 'ray', 'var_dump', 'print_r', 'die', 'exit'])
    ->not->toBeUsed();

arch('preset de segurança do Pest')
    ->preset()->security();

arch('value objects e DTOs do kernel compartilhado são readonly')
    ->expect('App\Shared\ValueObjects')
    ->toBeReadonly();
