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

arch('value objects e DTOs são readonly')
    ->expect([
        'App\Shared\Domain\Git\RepositoryUrl',
        'App\Shared\Domain\Git\GitRef',
        'App\Shared\Domain\Git\CommitSha',
        'App\Modules\Catalog\Domain\Slug',
        'App\Modules\Catalog\Application\SubmitGameInput',
        'App\Modules\Audit\Domain\AuditRecord',
        'App\Modules\Ingestion\Domain\TreeEntry',
        'App\Modules\Ingestion\Domain\RepositorySnapshot',
        'App\Modules\Ingestion\Domain\PolicyLimits',
        'App\Modules\Ingestion\Domain\Violation',
        'App\Modules\Ingestion\Domain\IgnoredFile',
        'App\Modules\Ingestion\Domain\ValidationReport',
        'App\Modules\Ingestion\Domain\RepositoryPolicy',
        'App\Modules\Ingestion\Domain\Rules',
        'App\Modules\Versioning\Domain\DecisionReason',
        'App\Modules\Versioning\Domain\QuarantinedFile',
    ])
    ->toBeReadonly();

arch('eventos de domínio são readonly')
    ->expect('App\Modules\*\Domain\Events')
    ->toBeReadonly();
