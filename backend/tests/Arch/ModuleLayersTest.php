<?php

declare(strict_types=1);

/*
| Regras de dependência entre camadas e módulos (docs/arquitetura.md).
|
|   Http ──► Application ──► Domain ◄── Infrastructure
|
| Um módulo pode usar de outro apenas o Domain (eventos, IDs, contratos).
*/

const BITMAN_MODULES = ['Identity', 'Catalog', 'Versioning', 'Ingestion', 'Play', 'Audit'];

foreach (BITMAN_MODULES as $module) {
    $base = "App\\Modules\\{$module}";

    arch("{$module}: Domain não depende de Application, Infrastructure, Http nem de I/O do framework")
        ->expect("{$base}\\Domain")
        ->not->toUse([
            "{$base}\\Application",
            "{$base}\\Infrastructure",
            "{$base}\\Http",
            'Illuminate\Http',
            'Illuminate\Routing',
            'Illuminate\Support\Facades',
            'Illuminate\Console',
        ]);

    arch("{$module}: Application não depende de Infrastructure nem de Http")
        ->expect("{$base}\\Application")
        ->not->toUse(["{$base}\\Infrastructure", "{$base}\\Http"]);

    foreach (array_diff(BITMAN_MODULES, [$module]) as $other) {
        arch("{$module} só usa o Domain de {$other}")
            ->expect($base)
            ->not->toUse([
                "App\\Modules\\{$other}\\Application",
                "App\\Modules\\{$other}\\Infrastructure",
                "App\\Modules\\{$other}\\Http",
            ]);
    }
}
