<?php

declare(strict_types=1);

use App\Modules\Catalog\Domain\Exceptions\InvalidSlug;
use App\Modules\Catalog\Domain\Slug;

it('gera o slug a partir do nome do jogo', function (string $name, string $slug): void {
    expect(Slug::fromName($name)->value)->toBe($slug);
})->with([
    ['Snake', 'snake'],
    ['Pac-Man Clássico', 'pac-man-classico'],
    ['  Corrida   de   Naves!! ', 'corrida-de-naves'],
    ['Ação 2', 'acao-2'],
    ['!!!', 'jogo'],
]);

it('limita o slug gerado a 60 caracteres sem hífen no fim', function (): void {
    $slug = Slug::fromName(str_repeat('ab ', 40))->value;

    expect(strlen($slug))->toBeLessThanOrEqual(60)
        ->and($slug)->not->toEndWith('-');
});

it('acrescenta um sufixo numérico para desempatar sem passar do limite', function (): void {
    expect(Slug::fromString('snake')->withSuffix(2)->value)->toBe('snake-2')
        ->and(strlen(Slug::fromString(str_repeat('a', 60))->withSuffix(12)->value))->toBe(60);
});

it('aceita slugs editados pelo admin no formato permitido', function (string $slug): void {
    expect(Slug::fromString($slug)->value)->toBe($slug);
})->with(['snake', 'pac-man', 'jogo-2', 'ab']);

it('rejeita slugs fora do formato', function (string $slug): void {
    expect(fn () => Slug::fromString($slug))->toThrow(InvalidSlug::class);
})->with([
    'maiúsculas' => 'Snake',
    'espaço' => 'pac man',
    'hífen no início' => '-snake',
    'hífen no fim' => 'snake-',
    'hífens duplos' => 'pac--man',
    'barra' => 'pac/man',
    'acento' => 'ação',
    'curto' => 'a',
    'longo' => str_repeat('a', 61),
    'vazio' => '',
]);
