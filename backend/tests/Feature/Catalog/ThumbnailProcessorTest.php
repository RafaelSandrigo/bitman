<?php

declare(strict_types=1);

use App\Modules\Catalog\Domain\Exceptions\InvalidThumbnail;
use App\Modules\Catalog\Domain\ThumbnailProcessor;
use Illuminate\Support\Facades\Storage;
use Tests\Support\Images;

/*
| A4: só PNG/JPEG/WebP verificados pelo conteúdo, com limites de bytes e dimensões,
| re-codificados em WebP sem metadados e gravados com nome aleatório.
*/

beforeEach(function (): void {
    Storage::fake('thumbnails');
});

function uploaded(string $bytes): string
{
    $path = tempnam(sys_get_temp_dir(), 'thumb');
    file_put_contents($path, $bytes);

    return $path;
}

function storeThumbnail(string $bytes): string
{
    return app(ThumbnailProcessor::class)->store(uploaded($bytes));
}

it('re-codifica PNG, JPEG e WebP em WebP com nome aleatório (A4)', function (string $bytes): void {
    $name = storeThumbnail($bytes);

    $stored = Storage::disk('thumbnails')->get($name);
    expect($name)->toMatch('/^[0-9a-f]{32}\.webp$/')
        ->and(substr((string) $stored, 0, 4))->toBe('RIFF')
        ->and(substr((string) $stored, 8, 4))->toBe('WEBP');
})->with([
    'png' => fn () => Images::png(),
    'jpeg' => fn () => Images::jpeg(),
    'webp' => fn () => Images::webp(),
    'png com paleta' => fn () => Images::palettePng(),
]);

it('gera um nome diferente a cada envio', function (): void {
    expect(storeThumbnail(Images::png()))->not->toBe(storeThumbnail(Images::png()));
});

it('reduz imagens grandes mantendo a proporção', function (): void {
    $name = storeThumbnail(Images::png(1600, 900));

    $size = getimagesizefromstring((string) Storage::disk('thumbnails')->get($name));
    expect($size[0] ?? null)->toBe(800)->and($size[1] ?? null)->toBe(450);
});

it('remove os metadados da imagem original (A4)', function (): void {
    $name = storeThumbnail(Images::jpegWithExif('SEGREDO-GPS-23.5S-46.6W'));

    expect((string) Storage::disk('thumbnails')->get($name))->not->toContain('SEGREDO-GPS')->not->toContain('Exif');
});

it('descarta o conteúdo extra de um arquivo poliglota (A4)', function (): void {
    $name = storeThumbnail(Images::png().'<script>alert(document.cookie)</script>');

    expect((string) Storage::disk('thumbnails')->get($name))->not->toContain('<script>');
});

it('recusa formatos não permitidos ou conteúdo que não é imagem (A4)', function (string $bytes): void {
    expect(fn () => storeThumbnail($bytes))->toThrow(InvalidThumbnail::class, 'A thumbnail deve ser uma imagem PNG, JPEG ou WebP válida.');
    expect(Storage::disk('thumbnails')->allFiles())->toBe([]);
})->with([
    'svg' => fn () => Images::svg(),
    'gif' => fn () => Images::gif(),
    'texto' => 'não sou uma imagem',
    'script com extensão de imagem' => '<?php system($_GET["c"]);',
    'png truncado' => fn () => substr(Images::png(), 0, 60),
    'vazio' => '',
]);

it('recusa dimensões fora dos limites sem decodificar a imagem (bomba de descompressão)', function (string $bytes): void {
    expect(fn () => storeThumbnail($bytes))->toThrow(InvalidThumbnail::class, 'A thumbnail deve ter entre 64 e 4096 pixels de largura e de altura.');
})->with([
    'bomba' => fn () => Images::pngHeaderClaiming(100_000, 100_000),
    'larga demais' => fn () => Images::pngHeaderClaiming(5000, 100),
    'pequena demais' => fn () => Images::png(32, 32),
]);

it('recusa arquivos maiores que 2 MB', function (): void {
    expect(fn () => storeThumbnail(Images::png().str_repeat("\0", 2 * 1024 * 1024)))
        ->toThrow(InvalidThumbnail::class, 'A thumbnail deve ter no máximo 2 MB.');
});

it('descarta uma thumbnail gravada e ignora nomes que não gerou', function (): void {
    $name = storeThumbnail(Images::png());
    Storage::disk('thumbnails')->put('outro.txt', 'x');

    app(ThumbnailProcessor::class)->discard($name);
    app(ThumbnailProcessor::class)->discard('../outro.txt');
    app(ThumbnailProcessor::class)->discard('outro.txt');

    expect(Storage::disk('thumbnails')->allFiles())->toBe(['outro.txt']);
});
