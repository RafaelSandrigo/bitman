<?php

declare(strict_types=1);

use App\Shared\Domain\Git\GitRef;
use App\Shared\Domain\Git\InvalidGitRef;

it('aceita branches, tags e SHAs comuns', function (string $ref): void {
    expect(GitRef::fromString($ref)->value)->toBe($ref);
})->with(['main', 'v1.2.0', 'feature/novo-nivel', 'release_2026', 'a84c7f91', str_repeat('a', 40)]);

it('rejeita refs que poderiam virar opção ou comando do git (G1)', function (string $ref): void {
    expect(fn () => GitRef::fromString($ref))->toThrow(InvalidGitRef::class);
})->with([
    'opção' => '--upload-pack=touch /tmp/pwned',
    'opção curta' => '-c',
    'traversal' => 'feature/../main',
    'espaço' => 'minha branch',
    'shell' => 'main;rm -rf /',
    'subshell' => '$(id)',
    'crase' => '`id`',
    'reflog' => 'main@{1}',
    'til' => 'main~1',
    'circunflexo' => 'main^',
    'dois-pontos' => 'main:arquivo',
    'barra inicial' => '/main',
    'barra final' => 'main/',
    'barras duplas' => 'feature//x',
    'termina com ponto' => 'main.',
    'termina com .lock' => 'main.lock',
    'segmento oculto' => 'feature/.x',
    'nova linha' => "main\n",
    'unicode' => 'versão',
    'vazia' => '',
    'longa demais' => str_repeat('a', 101),
]);
