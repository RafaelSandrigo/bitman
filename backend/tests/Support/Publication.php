<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Modules\Catalog\Domain\Game;
use App\Modules\Identity\Domain\User;
use App\Modules\Versioning\Application\Actions\PublishVersion;
use App\Modules\Versioning\Application\Actions\ReviewFile;
use App\Modules\Versioning\Domain\ChangeType;
use App\Modules\Versioning\Domain\FileDecision;
use App\Modules\Versioning\Domain\GameVersion;
use App\Modules\Versioning\Domain\QuarantinedFile;
use App\Modules\Versioning\Domain\QuarantineStore;
use App\Modules\Versioning\Domain\VersionFile;
use RuntimeException;

/** Versões pelo caminho real (quarentena → revisão → archive → served). Exige `Artifacts::temporaryRoot()`. */
final class Publication
{
    /**
     * Versão em `awaiting_file_review` com os arquivos na quarentena (o tipo de mudança é dado pelo teste).
     *
     * @param  array<string, string>  $contents  path => conteúdo, sobre os arquivos padrão
     * @param  array<string, ChangeType>  $changes  path => tipo de mudança (padrão: added)
     */
    public static function awaitingReview(Game $game, array $contents = [], array $changes = []): GameVersion
    {
        $number = (int) GameVersion::query()->where('game_id', $game->id)->max('number') + 1;
        $version = GameVersion::factory()->for($game)->awaitingFileReview()->create(['number' => $number]);
        foreach ([...Snapshots::gameFiles(), ...$contents] as $path => $content) {
            $sha256 = app(QuarantineStore::class)->put($version->id, $content);
            VersionFile::record($version, new QuarantinedFile($path, $sha256, strlen($content), sha1($content), $changes[$path] ?? ChangeType::Added));
        }

        return $version;
    }

    /** @param  array<string, string>  $contents */
    public static function published(User $admin, ?Game $game = null, array $contents = []): GameVersion
    {
        $version = self::awaitingReview($game ?? Game::factory()->create(), $contents);
        foreach ($version->files()->get() as $file) {
            app(ReviewFile::class)($admin, $version->id, $file->id, $file->sha256, FileDecision::Approved, null);
        }
        app(PublishVersion::class)($admin, $version->id);

        return $version->fresh() ?? throw new RuntimeException('versão sumiu');
    }
}
