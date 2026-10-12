<?php

declare(strict_types=1);

use App\Modules\Ingestion\Domain\Exceptions\InvalidSnapshot;
use App\Modules\Ingestion\Domain\RepositorySnapshot;
use App\Modules\Ingestion\Domain\TreeEntry;
use App\Shared\Domain\Git\CommitSha;
use Tests\Support\Snapshots;

$commit = fn (): CommitSha => CommitSha::fromString(Snapshots::COMMIT);

it('calcula o id do blob como o git (SHA-1 e SHA-256)', function (): void {
    // `printf 'olá\n' | git hash-object --stdin` e o mesmo com --object-format=sha256.
    expect(RepositorySnapshot::gitBlobId("hello\n"))->toBe('ce013625030ba8dba906f756967f9e9ca394464a')
        ->and(RepositorySnapshot::gitBlobId("hello\n", 64))->toBe('2cf8d83d9ee29543b34a87727421fdecb7e3f3a183d337639025de576db9ebb4');
});

it('aceita um snapshot coerente', function (): void {
    $snapshot = Snapshots::valid();

    expect($snapshot->entry('game.js')?->size)->toBe(strlen(Snapshots::gameFiles()['game.js']))
        ->and($snapshot->blob('game.js'))->toBe(Snapshots::gameFiles()['game.js'])
        ->and($snapshot->entry('nao-existe'))->toBeNull();
});

it('recusa blob cujo conteúdo não é o objeto apontado pela árvore (sandbox comprometida)', function () use ($commit): void {
    $entry = Snapshots::file('game.js', 'original');

    expect(fn () => new RepositorySnapshot($commit(), [$entry], ['game.js' => 'trocado']))->toThrow(InvalidSnapshot::class);
});

it('recusa blob com tamanho diferente do declarado', function () use ($commit): void {
    $entry = new TreeEntry('game.js', '100644', 'blob', RepositorySnapshot::gitBlobId('abc'), 99);

    expect(fn () => new RepositorySnapshot($commit(), [$entry], ['game.js' => 'abc']))->toThrow(InvalidSnapshot::class);
});

it('recusa blob sem entrada na árvore e paths repetidos', function () use ($commit): void {
    expect(fn () => new RepositorySnapshot($commit(), [], ['game.js' => 'x']))->toThrow(InvalidSnapshot::class)
        ->and(fn () => new RepositorySnapshot($commit(), [Snapshots::entry('a'), Snapshots::entry('a')], []))->toThrow(InvalidSnapshot::class)
        ->and(fn () => new RepositorySnapshot($commit(), [Snapshots::entry('lib', '160000', null, 'commit')], ['lib' => 'x']))->toThrow(InvalidSnapshot::class);
});

it('recusa entradas malformadas', function (string $path, string $mode, string $type, string $object, ?int $size): void {
    expect(fn () => new TreeEntry($path, $mode, $type, $object, $size))->toThrow(InvalidSnapshot::class);
})->with([
    'path vazio' => ['', '100644', 'blob', str_repeat('a', 40), 1],
    'modo' => ['a', '10064', 'blob', str_repeat('a', 40), 1],
    'tipo' => ['a', '100644', 'tag', str_repeat('a', 40), 1],
    'objeto' => ['a', '100644', 'blob', 'xyz', 1],
    'tamanho negativo' => ['a', '100644', 'blob', str_repeat('a', 40), -1],
]);

it('escapa o path para exibição (bytes de controle e UTF-8 inválido)', function (string $raw, string $shown): void {
    expect(TreeEntry::display($raw))->toBe($shown);
})->with([
    'comum' => ['src/game.js', 'src/game.js'],
    'acento' => ['ação.txt', 'ação.txt'],
    'quebra de linha' => ["a\nb", 'a\x0Ab'],
    'controle' => ["a\x01", 'a\x01'],
    'inválido' => ["a\xFF", 'a\xFF'],
    'barra invertida' => ['a\\b', 'a\\\\b'],
    'overlong' => ["\xC0\xAF", '\xC0\xAF'],
    'surrogate' => ["\xED\xA0\x80", '\xED\xA0\x80'],
]);

it('descreve o tipo da entrada', function (TreeEntry $entry, string $kind): void {
    expect($entry->kind())->toBe($kind);
})->with([
    [fn () => Snapshots::entry('a'), 'um arquivo comum'],
    [fn () => Snapshots::entry('a', '100755'), 'um executável'],
    [fn () => Snapshots::entry('a', '120000'), 'um symlink'],
    [fn () => Snapshots::entry('a', '160000', null, 'commit'), 'um submodule'],
    [fn () => Snapshots::entry('a', '040000', null, 'tree'), 'um diretório'],
]);
