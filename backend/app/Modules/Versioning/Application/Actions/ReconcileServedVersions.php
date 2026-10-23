<?php

declare(strict_types=1);

namespace App\Modules\Versioning\Application\Actions;

use App\Modules\Catalog\Domain\Game;
use App\Modules\Catalog\Domain\GameStatus;
use App\Modules\Versioning\Application\ReconciliationReport;
use App\Modules\Versioning\Domain\ArtifactStore;
use App\Modules\Versioning\Domain\GameVersion;
use App\Modules\Versioning\Domain\VersionFile;
use App\Modules\Versioning\Domain\VersionStatus;
use Illuminate\Support\Collection;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * M12: o `served/` tem de ser exatamente as versões no ar (jogo publicado, versão publicada) com
 * os arquivos aprovados. Corrige só a partir do archive, conferindo os hashes (I6, I7): servido a
 * mais sai do ar (J6), servido a menos ou diferente volta pelo ServeArchivedVersion. Roda como sistema.
 */
final readonly class ReconcileServedVersions
{
    public function __construct(
        private ArtifactStore $artifacts,
        private ServeArchivedVersion $serveArchivedVersion,
        private LoggerInterface $logger,
    ) {}

    public function __invoke(): ReconciliationReport
    {
        $report = new ReconciliationReport;
        $live = $this->liveVersions();

        $this->removeStray($live->map(fn (GameVersion $version): string => (string) $version->public_id)->all(), $report);
        foreach ($live as $version) {
            $this->reconcile($version, $report);
        }

        return $report;
    }

    /** @return Collection<int, GameVersion> */
    private function liveVersions(): Collection
    {
        $liveIds = Game::query()->where('status', GameStatus::Published)->whereNotNull('published_version_id')->pluck('published_version_id');

        return GameVersion::query()->whereIn('id', $liveIds)->where('status', VersionStatus::Published)->whereNotNull('public_id')->with('files')->get();
    }

    /** @param  array<int, string>  $livePublicIds */
    private function removeStray(array $livePublicIds, ReconciliationReport $report): void
    {
        foreach (array_diff($this->artifacts->servedIds(), $livePublicIds) as $publicId) {
            $this->artifacts->unserve($publicId);
            $report->removed[] = $publicId;
            $this->logger->warning('served.removed', ['public_id' => $publicId, 'reason' => 'sem versão no ar']);
        }
    }

    private function reconcile(GameVersion $version, ReconciliationReport $report): void
    {
        $publicId = (string) $version->public_id;
        $wasServed = $this->artifacts->isServed($publicId);
        if ($wasServed && $this->servedMatchesApproved($version)) {
            return;
        }

        try {
            if ($wasServed) {
                $this->artifacts->unserve($publicId);
            }
            ($this->serveArchivedVersion)($version->id);
        } catch (Throwable $e) {
            $report->failed[] = $version->id;
            $this->logger->error('served.restore_failed', ['version_id' => $version->id, 'public_id' => $publicId, 'error' => $e->getMessage()]);

            return;
        }

        $wasServed ? $report->replaced[] = $publicId : $report->restored[] = $publicId;
        $this->logger->warning($wasServed ? 'served.replaced' : 'served.restored', ['version_id' => $version->id, 'public_id' => $publicId]);
    }

    private function servedMatchesApproved(GameVersion $version): bool
    {
        $paths = array_values($version->files->map(fn (VersionFile $file): string => $file->path)->all());
        $served = $this->artifacts->readServed((string) $version->public_id, $paths);

        return $version->files->every(fn (VersionFile $file): bool => $served[$file->path] !== null && hash_equals($file->sha256, hash('sha256', $served[$file->path])));
    }
}
