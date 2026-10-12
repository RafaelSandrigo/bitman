<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Infrastructure;

use App\Modules\Catalog\Domain\Exceptions\InvalidThumbnail;
use App\Modules\Catalog\Domain\ThumbnailProcessor;
use GdImage;
use Illuminate\Contracts\Filesystem\Filesystem;

/**
 * A4 com GD. A ordem importa: tamanho do arquivo → tipo e dimensões lidos do cabeçalho
 * (sem decodificar, contra bombas de descompressão) → decodificação → re-codificação em WebP.
 * A saída é gerada do zero a partir dos pixels, então metadados e conteúdo anexado
 * (arquivos poliglotas) ficam para trás.
 */
final readonly class GdThumbnailProcessor implements ThumbnailProcessor
{
    public const MAX_BYTES = 2 * 1024 * 1024;

    public const MIN_SIDE = 64;

    public const MAX_SIDE = 4096;

    /** Maior lado da imagem gravada: suficiente para os cards em telas de alta densidade. */
    private const OUTPUT_SIDE = 800;

    private const QUALITY = 82;

    private const ALLOWED_TYPES = [IMAGETYPE_PNG, IMAGETYPE_JPEG, IMAGETYPE_WEBP];

    private const NAME = '/^[0-9a-f]{32}\.webp$/D';

    public function __construct(private Filesystem $disk) {}

    public function store(string $uploadedPath): string
    {
        $size = is_file($uploadedPath) ? filesize($uploadedPath) : false;
        if ($size === false || $size === 0) {
            throw InvalidThumbnail::unsupported();
        }
        if ($size > self::MAX_BYTES) {
            throw InvalidThumbnail::tooLarge(self::MAX_BYTES);
        }

        $bytes = (string) file_get_contents($uploadedPath);

        // @: o GD emite avisos para conteúdo inválido; o retorno false já é tratado.
        $info = @getimagesizefromstring($bytes);
        if ($info === false || ! in_array($info[2], self::ALLOWED_TYPES, true)) {
            throw InvalidThumbnail::unsupported();
        }

        [$width, $height] = $info;
        if (min($width, $height) < self::MIN_SIDE || max($width, $height) > self::MAX_SIDE) {
            throw InvalidThumbnail::badDimensions(self::MIN_SIDE, self::MAX_SIDE);
        }

        $image = @imagecreatefromstring($bytes);
        if ($image === false) {
            throw InvalidThumbnail::unsupported();
        }

        $name = bin2hex(random_bytes(16)).'.webp';
        $this->disk->put($name, $this->encodeWebp($this->resize($image, $width, $height)));

        return $name;
    }

    public function discard(string $name): void
    {
        if (preg_match(self::NAME, $name) === 1) {
            $this->disk->delete($name);
        }
    }

    private function resize(GdImage $image, int $width, int $height): GdImage
    {
        imagepalettetotruecolor($image);

        if (max($width, $height) <= self::OUTPUT_SIDE) {
            return $image;
        }

        $scale = self::OUTPUT_SIDE / max($width, $height);
        $resized = imagescale($image, (int) round($width * $scale), (int) round($height * $scale));

        return $resized !== false ? $resized : throw InvalidThumbnail::unsupported();
    }

    private function encodeWebp(GdImage $image): string
    {
        imagealphablending($image, false);
        imagesavealpha($image, true);

        ob_start();
        $encoded = imagewebp($image, null, self::QUALITY);
        $webp = (string) ob_get_clean();

        return $encoded && $webp !== '' ? $webp : throw InvalidThumbnail::unsupported();
    }
}
