<?php

declare(strict_types=1);

use App\Modules\Versioning\Domain\ArtifactStore;
use App\Modules\Versioning\Domain\Exceptions\ArtifactAlreadyExists;
use Tests\Support\Artifacts;

/*
| FilesystemArtifactStore no disco de verdade (I7, J6, permissões para o nginx de jogos).
*/

beforeEach(function (): void {
    $this->root = Artifacts::temporaryRoot();
    $this->store = app(ArtifactStore::class);
});

afterEach(function (): void {
    Artifacts::remove($this->root);
});

const ARTIFACT_GAME_ID = '01m4q69ybah9jvf0jnw3avwf0c';
const ARTIFACT_PUBLIC_ID = '0b9f6a52-3c1e-4d7a-9b2e-5f8c1a2d3e4f';

it('arquiva uma vez só (I7): o segundo archive falha e não toca no primeiro', function (): void {
    $this->store->archive(ARTIFACT_GAME_ID, 1, ['game.js' => 'v1']);

    expect(fn () => $this->store->archive(ARTIFACT_GAME_ID, 1, ['game.js' => 'outro']))->toThrow(ArtifactAlreadyExists::class)
        ->and($this->store->readArchive(ARTIFACT_GAME_ID, 1, ['game.js']))->toBe(['game.js' => 'v1'])
        ->and(file_exists("{$this->root}/served"))->toBeFalse();
});

it('serve com diretório 0755 e arquivos 0644, legíveis pelo nginx de jogos', function (): void {
    $this->store->serve(ARTIFACT_PUBLIC_ID, ['index.html' => '<!doctype html>', 'game.js' => 'x()']);

    $dir = "{$this->root}/served/".ARTIFACT_PUBLIC_ID;
    expect($this->store->isServed(ARTIFACT_PUBLIC_ID))->toBeTrue()
        ->and(Artifacts::mode($dir))->toBe('0755')
        ->and(Artifacts::mode("{$dir}/index.html"))->toBe('0644')
        ->and(file_get_contents("{$dir}/game.js"))->toBe('x()')
        // Nenhum resto do staging fica visível.
        ->and(array_values(array_diff(scandir("{$this->root}/served") ?: [], ['.', '..'])))->toBe([ARTIFACT_PUBLIC_ID]);
});

it('não sobrescreve um diretório servido', function (): void {
    $this->store->serve(ARTIFACT_PUBLIC_ID, ['game.js' => 'a']);

    expect(fn () => $this->store->serve(ARTIFACT_PUBLIC_ID, ['game.js' => 'b']))->toThrow(ArtifactAlreadyExists::class)
        ->and(file_get_contents("{$this->root}/served/".ARTIFACT_PUBLIC_ID.'/game.js'))->toBe('a');
});

it('tira do ar de uma vez e é idempotente', function (): void {
    $this->store->serve(ARTIFACT_PUBLIC_ID, ['game.js' => 'a']);
    $this->store->unserve(ARTIFACT_PUBLIC_ID);
    $this->store->unserve(ARTIFACT_PUBLIC_ID);

    expect($this->store->isServed(ARTIFACT_PUBLIC_ID))->toBeFalse()
        ->and(array_values(array_diff(scandir("{$this->root}/served") ?: [], ['.', '..'])))->toBe([]);
});

it('caminhos só com nossos ids e a allowlist', function (Closure $call): void {
    expect(fn () => $call($this->store))->toThrow(InvalidArgumentException::class);
})->with([
    'jogo com ..' => [fn (ArtifactStore $s) => $s->archive('../../etc', 1, ['game.js' => 'x'])],
    'número zero' => [fn (ArtifactStore $s) => $s->archive(ARTIFACT_GAME_ID, 0, ['game.js' => 'x'])],
    'arquivo fora da allowlist' => [fn (ArtifactStore $s) => $s->serve(ARTIFACT_PUBLIC_ID, ['../index.php' => 'x'])],
    'publicId que não é UUID' => [fn (ArtifactStore $s) => $s->serve('../../../tmp', ['game.js' => 'x'])],
    'publicId em maiúsculas' => [fn (ArtifactStore $s) => $s->unserve(strtoupper(ARTIFACT_PUBLIC_ID))],
]);

it('descarta um archive não confirmado', function (): void {
    $this->store->archive(ARTIFACT_GAME_ID, 2, ['game.js' => 'x']);
    $this->store->discardUncommittedArchive(ARTIFACT_GAME_ID, 2);

    expect(is_dir("{$this->root}/archive/".ARTIFACT_GAME_ID.'/v2'))->toBeFalse();
    $this->store->archive(ARTIFACT_GAME_ID, 2, ['game.js' => 'y']);
});
