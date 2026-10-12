<?php

declare(strict_types=1);

/*
| Ingestão de repositórios (docs/dominio.md#política-de-repositório-mvp, ADR 0003).
*/
return [

    // git-sandbox: só o worker da fila "ingestion" a alcança (rede sandbox).
    'sandbox' => [
        'url' => env('SANDBOX_URL', 'http://git-sandbox:8080'),
        'token' => env('SANDBOX_TOKEN'),
        // A sandbox desiste em 60 s (SANDBOX_TIMEOUT); aqui sobra margem para a resposta.
        'timeout' => 90,
        // Teto da resposta: 3 blobs de 1 MiB em base64 + a árvore. Acima disso, a resposta é descartada.
        'max_response_bytes' => 8 * 1024 * 1024,
    ],

    // Os únicos arquivos extraídos (raiz do repositório).
    'required_paths' => ['index.html', 'style.css', 'game.js'],

    'max_file_bytes' => 1024 * 1024,

    'max_tree_entries' => 500,

];
