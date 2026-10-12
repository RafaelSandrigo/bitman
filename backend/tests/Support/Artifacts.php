<?php

declare(strict_types=1);

namespace Tests\Support;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/** Volume `artifacts` de verdade, num diretório temporário por teste (permissões e renames reais). */
final class Artifacts
{
    public static function temporaryRoot(): string
    {
        $root = sys_get_temp_dir().'/bitman-artifacts-'.bin2hex(random_bytes(6));
        mkdir($root, 0755);
        config(['publication.artifacts_path' => $root]);

        return $root;
    }

    public static function remove(string $root): void
    {
        if (! is_dir($root)) {
            return;
        }
        $items = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($items as $item) {
            /** @var SplFileInfo $item */
            @chmod($item->getPathname(), 0700);
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($root);
    }

    public static function mode(string $path): string
    {
        clearstatcache();

        return substr(sprintf('%o', fileperms($path)), -4);
    }
}
