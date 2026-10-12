<?php

declare(strict_types=1);

namespace App\Modules\Versioning\Domain;

use App\Modules\Catalog\Domain\Game;
use App\Modules\Identity\Domain\User;
use App\Modules\Ingestion\Domain\FetchFailure;
use App\Modules\Ingestion\Domain\ValidationReport;
use App\Modules\Versioning\Domain\Events\ImportAuthorized;
use App\Modules\Versioning\Domain\Events\ImportRejected;
use App\Modules\Versioning\Domain\Events\ImportRequested;
use App\Modules\Versioning\Domain\Events\RepositoryFetched;
use App\Modules\Versioning\Domain\Events\RepositoryFetchFailed;
use App\Modules\Versioning\Domain\Exceptions\InvalidVersionTransition;
use App\Shared\Domain\EmitsDomainEvents;
use App\Shared\Domain\Git\CommitSha;
use App\Shared\Domain\Git\GitRef;
use App\Shared\Domain\Git\RepositoryUrl;
use App\Shared\Domain\RecordsDomainEvents;
use Carbon\CarbonImmutable;
use Database\Factories\GameVersionFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Agregado central do versionamento. Nasce como solicitação de importação: nenhum acesso à
 * rede acontece antes da autorização do Portão 0 (I2). Cada transição confere o estado atual
 * e registra um evento auditável; estados terminais nunca voltam.
 *
 * @property string $id
 * @property string $game_id
 * @property int $number
 * @property VersionStatus $status
 * @property string $repository_url snapshot da URL no momento da solicitação
 * @property string $requested_ref
 * @property string $requested_by_id
 * @property string|null $import_decided_by_id
 * @property CarbonImmutable|null $import_decided_at
 * @property string|null $import_rejection_reason
 * @property string|null $commit_sha
 * @property array<string, mixed>|null $validation_report
 * @property string|null $fetch_failure
 * @property CarbonImmutable|null $fetched_at
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 * @property-read Game $game
 * @property-read User $requestedBy
 * @property-read User|null $importDecidedBy
 * @property-read Collection<int, VersionFile> $files
 */
#[UseFactory(GameVersionFactory::class)]
final class GameVersion extends Model implements EmitsDomainEvents
{
    /** @use HasFactory<GameVersionFactory> */
    use HasFactory, HasUlids, RecordsDomainEvents;

    protected $guarded = ['*'];

    public static function requestImport(string $gameId, RepositoryUrl $repositoryUrl, GitRef $ref, string $requestedById): self
    {
        $version = new self;
        $version->game_id = $gameId;
        $version->number = self::nextNumberFor($gameId);
        $version->repository_url = $repositoryUrl->value;
        $version->requested_ref = $ref->value;
        $version->requested_by_id = $requestedById;
        $version->status = VersionStatus::ImportRequested;
        $version->save();

        $version->recordEvent(new ImportRequested($version->id, $gameId, $version->number, $repositoryUrl, $ref, $requestedById));

        return $version;
    }

    /** Portão 0: o admin autoriza o acesso ao repositório. */
    public function authorizeImport(User $actor): void
    {
        $this->ensureStatus(VersionStatus::ImportRequested, 'ter a importação autorizada');

        $this->status = VersionStatus::Fetching;
        $this->import_decided_by_id = $actor->id;
        $this->import_decided_at = CarbonImmutable::now();
        $this->save();

        $this->recordEvent(new ImportAuthorized($this->id, $this->game_id, $this->number, $this->repository_url, $this->requested_ref, $actor->id));
    }

    /** Portão 0: o admin recusa o acesso ao repositório. Nenhuma rede é usada (I2). */
    public function rejectImport(User $actor, DecisionReason $reason): void
    {
        $this->ensureStatus(VersionStatus::ImportRequested, 'ter a importação rejeitada');

        $this->status = VersionStatus::ImportRejected;
        $this->import_decided_by_id = $actor->id;
        $this->import_decided_at = CarbonImmutable::now();
        $this->import_rejection_reason = $reason->value;
        $this->save();

        $this->recordEvent(new ImportRejected($this->id, $this->game_id, $this->number, $this->repository_url, $this->requested_ref, $actor->id, $reason->value));
    }

    /**
     * Fetch concluído: commit resolvido, relatório da política e os arquivos já na quarentena.
     * A versão vai para o Portão 1 mesmo com violações; a aprovação é que fica bloqueada (I3).
     *
     * @param  list<QuarantinedFile>  $files
     */
    public function completeFetch(CommitSha $commit, ValidationReport $report, array $files): void
    {
        $this->ensureStatus(VersionStatus::Fetching, 'concluir a busca');

        $this->status = VersionStatus::AwaitingCommitReview;
        $this->commit_sha = $commit->value;
        $this->validation_report = $report->toArray();
        $this->fetched_at = CarbonImmutable::now();
        $this->save();

        foreach ($files as $file) {
            VersionFile::record($this, $file);
        }

        $this->recordEvent(new RepositoryFetched($this->id, $this->game_id, $commit->value, $report->isBlocked(), count($report->violations), count($files)));
    }

    public function failFetch(FetchFailure $failure): void
    {
        $this->ensureStatus(VersionStatus::Fetching, 'falhar a busca');

        $this->status = VersionStatus::FetchFailed;
        $this->fetch_failure = $failure->value;
        $this->fetched_at = CarbonImmutable::now();
        $this->save();

        $this->recordEvent(new RepositoryFetchFailed($this->id, $this->game_id, $failure->value));
    }

    public function fetchFailure(): ?FetchFailure
    {
        return $this->fetch_failure === null ? null : FetchFailure::tryFrom($this->fetch_failure) ?? FetchFailure::FetchError;
    }

    /** @return BelongsTo<Game, $this> */
    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    /** @return BelongsTo<User, $this> */
    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_id');
    }

    /** @return BelongsTo<User, $this> */
    public function importDecidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'import_decided_by_id');
    }

    /** @return HasMany<VersionFile, $this> */
    public function files(): HasMany
    {
        return $this->hasMany(VersionFile::class)->orderBy('path');
    }

    private function ensureStatus(VersionStatus $expected, string $action): void
    {
        if ($this->status !== $expected) {
            throw InvalidVersionTransition::from($this->status, $action);
        }
    }

    /** Numeração sequencial por jogo; `unique(game_id, number)` no banco é a garantia final. */
    private static function nextNumberFor(string $gameId): int
    {
        $last = self::query()->where('game_id', $gameId)->max('number');

        return (is_numeric($last) ? (int) $last : 0) + 1;
    }

    protected function casts(): array
    {
        return [
            'number' => 'integer',
            'status' => VersionStatus::class,
            'validation_report' => 'array',
            'import_decided_at' => 'immutable_datetime',
            'fetched_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
