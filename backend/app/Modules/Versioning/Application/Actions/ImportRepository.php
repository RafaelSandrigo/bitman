<?php

declare(strict_types=1);

namespace App\Modules\Versioning\Application\Actions;

use App\Modules\Ingestion\Domain\Exceptions\FetchFailed;
use App\Modules\Ingestion\Domain\FetchFailure;
use App\Modules\Ingestion\Domain\GitSourceFetcher;
use App\Modules\Ingestion\Domain\RepositoryPolicy;
use App\Modules\Ingestion\Domain\RepositorySnapshot;
use App\Modules\Versioning\Domain\ChangeType;
use App\Modules\Versioning\Domain\GameVersion;
use App\Modules\Versioning\Domain\QuarantinedFile;
use App\Modules\Versioning\Domain\QuarantineStore;
use App\Modules\Versioning\Domain\VersionFile;
use App\Modules\Versioning\Domain\VersionStatus;
use App\Shared\Application\DomainEventPublisher;
use App\Shared\Domain\BusinessRuleViolation;
use App\Shared\Domain\Git\GitRef;
use App\Shared\Domain\Git\RepositoryUrl;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Database\ConnectionInterface;
use Throwable;

/**
 * Executa a busca autorizada no Portão 0 (roda na fila `ingestion`, como sistema):
 * sandbox → snapshot → política → quarentena → diff com a versão publicada → Portão 1.
 * Com violação bloqueante, nada vai para a quarentena e a versão termina em `validation_failed`.
 *
 * Nunca termina com erro não tratado: qualquer falha leva a versão para `fetch_failed`.
 */
final readonly class ImportRepository
{
    public function __construct(
        private GitSourceFetcher $fetcher,
        private RepositoryPolicy $policy,
        private QuarantineStore $quarantine,
        private ConnectionInterface $db,
        private DomainEventPublisher $publisher,
        private ExceptionHandler $exceptions,
    ) {}

    public function __invoke(string $versionId): void
    {
        $version = GameVersion::find($versionId);
        if ($version === null || $version->status !== VersionStatus::Fetching) {
            return; // Já processada (ou nunca autorizada): nada a fazer.
        }

        try {
            $snapshot = $this->fetch($version);
            $report = $this->policy->evaluate($snapshot);
            $files = $report->isBlocked() ? [] : $this->quarantineFiles($version, $snapshot);

            $this->db->transaction(function () use ($versionId, $snapshot, $report, $files): void {
                $version = GameVersion::query()->lockForUpdate()->findOrFail($versionId);
                $version->completeFetch($snapshot->commit, $report, $files);
                $this->publisher->publishFrom($version);
            });
        } catch (FetchFailed $e) {
            $this->fail($versionId, $e->failure);
        } catch (Throwable $e) {
            $this->exceptions->report($e);
            $this->fail($versionId, FetchFailure::Internal);
        }
    }

    /** Leva a versão para `fetch_failed` (também usado pelo job quando o worker estoura o tempo). */
    public function fail(string $versionId, FetchFailure $failure): void
    {
        $failed = $this->db->transaction(function () use ($versionId, $failure): bool {
            $version = GameVersion::query()->lockForUpdate()->find($versionId);
            if ($version === null || $version->status !== VersionStatus::Fetching) {
                return false; // Já concluída: a quarentena dela é válida e fica.
            }
            $version->failFetch($failure);
            $this->publisher->publishFrom($version);

            return true;
        });

        if ($failed) {
            $this->quarantine->purge($versionId);
        }
    }

    /** URL e ref vêm do banco, mas passam de novo pelos value objects (G1–G3). */
    private function fetch(GameVersion $version): RepositorySnapshot
    {
        try {
            $url = RepositoryUrl::fromString($version->repository_url);
            $ref = GitRef::fromString($version->requested_ref);
        } catch (BusinessRuleViolation $e) {
            throw new FetchFailed(FetchFailure::RequestRefused, $e);
        }

        $limits = $this->policy->limits;

        return $this->fetcher->fetch($url, $ref, $limits->requiredPaths, $limits->maxFileBytes);
    }

    /**
     * Grava na quarentena os obrigatórios (G6: nada além da allowlist). Só roda sem violação, mas
     * confere de novo cada arquivo (defesa em profundidade). O diff é contra a versão publicada do jogo.
     *
     * @return list<QuarantinedFile>
     */
    private function quarantineFiles(GameVersion $version, RepositorySnapshot $snapshot): array
    {
        $published = $this->publishedHashes($version->game_id);
        $limits = $this->policy->limits;

        $files = [];
        foreach ($limits->requiredPaths as $path) {
            $entry = $snapshot->entry($path);
            $content = $snapshot->blob($path);
            if ($entry === null || ! $entry->isRegularFile() || $content === null || strlen($content) > $limits->maxFileBytes) {
                continue;
            }

            $sha256 = $this->quarantine->put($version->id, $content);
            $files[] = new QuarantinedFile($path, $sha256, strlen($content), $entry->objectId, ChangeType::against($published[$path] ?? null, $sha256));
        }

        return $files;
    }

    /** @return array<string, string> path => sha256 dos arquivos da versão publicada */
    private function publishedHashes(string $gameId): array
    {
        $published = GameVersion::query()->where('game_id', $gameId)->where('status', VersionStatus::Published)->first();
        if ($published === null) {
            return [];
        }

        return $published->files->mapWithKeys(fn (VersionFile $file): array => [$file->path => $file->sha256])->all();
    }
}
