<?php

declare(strict_types=1);

namespace Tests\Support;

use GdImage;
use RuntimeException;

/** Imagens geradas para os testes de thumbnail (A4): válidas e hostis, sem arquivos binários no repositório. */
final class Images
{
    public static function png(int $width = 320, int $height = 180): string
    {
        return self::encode(self::canvas($width, $height), 'png');
    }

    public static function jpeg(int $width = 320, int $height = 180): string
    {
        return self::encode(self::canvas($width, $height), 'jpeg');
    }

    public static function webp(int $width = 320, int $height = 180): string
    {
        return self::encode(self::canvas($width, $height), 'webp');
    }

    public static function gif(): string
    {
        return self::encode(self::canvas(320, 180), 'gif');
    }

    /** PNG com paleta (não truecolor), que o WebP não aceita sem conversão. */
    public static function palettePng(): string
    {
        $image = imagecreate(320, 180) ?: throw new RuntimeException('GD');
        imagecolorallocate($image, 10, 120, 200);

        return self::encode($image, 'png');
    }

    /** JPEG com um segmento APP1/Exif contendo um marcador (ex.: localização). */
    public static function jpegWithExif(string $marker): string
    {
        $jpeg = self::jpeg();
        $payload = "Exif\0\0".$marker;
        $segment = "\xFF\xE1".pack('n', strlen($payload) + 2).$payload;

        return substr($jpeg, 0, 2).$segment.substr($jpeg, 2);
    }

    /** Cabeçalho PNG válido declarando dimensões enormes, sem os dados: bomba de descompressão. */
    public static function pngHeaderClaiming(int $width, int $height): string
    {
        $chunk = fn (string $type, string $data): string => pack('N', strlen($data)).$type.$data.pack('N', crc32($type.$data));

        return "\x89PNG\r\n\x1a\n".$chunk('IHDR', pack('NNCCCCC', $width, $height, 8, 2, 0, 0, 0)).$chunk('IEND', '');
    }

    public static function svg(): string
    {
        return '<svg xmlns="http://www.w3.org/2000/svg" width="320" height="180"><script>alert(1)</script></svg>';
    }

    private static function canvas(int $width, int $height): GdImage
    {
        $image = imagecreatetruecolor($width, $height) ?: throw new RuntimeException('GD');
        imagefill($image, 0, 0, (int) imagecolorallocate($image, 200, 40, 90));

        return $image;
    }

    private static function encode(GdImage $image, string $format): string
    {
        ob_start();
        match ($format) {
            'png' => imagepng($image),
            'jpeg' => imagejpeg($image),
            'webp' => imagewebp($image),
            'gif' => imagegif($image),
        };

        return (string) ob_get_clean();
    }
}
