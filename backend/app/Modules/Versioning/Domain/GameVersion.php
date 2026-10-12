<?php

declare(strict_types=1);

namespace App\Modules\Versioning\Domain;

use App\Modules\Catalog\Domain\Game;
use App\Modules\Identity\Domain\User;
use App\Modules\Ingestion\Domain\FetchFailure;
use App\Modules\Ingestion\Domain\ValidationReport;
use App\Modules\Versioning\Domain\Events\CommitApproved;
use App\Modules\Versioning\Domain\Events\CommitRejected;
use App\Modules\Versioning\Domain\Events\FileReviewed;
use App\Modules\Versioning\Domain\Events\ImportAuthorized;
use App\Modules\Versioning\Domain\Events\ImportRejected;
use App\Modules\Versioning\Domain\Events\ImportRequested;
use App\Modules\Versioning\Domain\Events\RepositoryFetched;
use App\Modules\Versioning\Domain\Events\RepositoryFetchFailed;
use App\Modules\Versioning\Domain\Events\VersionRejected;
use App\Modules\Versioning\Domain\Exceptions\CommitHasBlockingViolations;
use App\Modules\Versioning\Domain\Exceptions\FileNotInVersion;
use App\Modules\Versioning\Domain\Exceptions\InvalidDecisionReason;
use App\Modules\Versioning\Domain\Exceptions\InvalidVersionTransition;
use App\Modules\Versioning\Domain\Exceptions\StaleFileReview;
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
use LogicException;

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
 * @property string|null $commit_decided_by_id
 * @property CarbonImmutable|null $commit_decided_at
 * @property string|null $commit_rejection_reason
 * @property string|null $rejected_by_id
 * @property CarbonImmutable|null $rejected_at
 * @property string|null $rejection_reason
 * @property CarbonImmutable|null $status_seen_at quando um admin viu a versão no estado atual (null = "nova")
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 * @property-read Game $game
 * @property-read User $requestedBy
 * @property-read User|null $importDecidedBy
 * @property-read User|null $commitDecidedBy
 * @property-read User|null $rejectedBy
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
        $version->moveTo(VersionStatus::ImportRequested, null);
        $version->save();

        $version->recordEvent(new ImportRequested($version->id, $gameId, $version->number, $repositoryUrl, $ref, $requestedById));

        return $version;
    }

    /** Portão 0: o admin autoriza o acesso ao repositório. */
    public function authorizeImport(User $actor): void
    {
        $this->ensureStatus(VersionStatus::ImportRequested, 'ter a importação autorizada');

        $this->moveTo(VersionStatus::Fetching, $actor);
        $this->import_decided_by_id = $actor->id;
        $this->import_decided_at = CarbonImmutable::now();
        $this->save();

        $this->recordEvent(new ImportAuthorized($this->id, $this->game_id, $this->number, $this->repository_url, $this->requested_ref, $actor->id));
    }

    /** Portão 0: o admin recusa o acesso ao repositório. Nenhuma rede é usada (I2). */
    public function rejectImport(User $actor, DecisionReason $reason): void
    {
        $this->ensureStatus(VersionStatus::ImportRequested, 'ter a importação rejeitada');

        $this->moveTo(VersionStatus::ImportRejected, $actor);
        $this->import_decided_by_id = $actor->id;
        $this->import_decided_at = CarbonImmutable::now();
        $this->import_rejection_reason = $reason->value;
        $this->save();

        $this->recordEvent(new ImportRejected($this->id, $this->game_id, $this->number, $this->repository_url, $this->requested_ref, $actor->id, $reason->value));
    }

    /**
     * Fetch concluído: commit resolvido, relatório da política e os arquivos já na quarentena.
     * Sem violações, a versão vai para o Portão 1. Com violação bloqueante, termina em
     * `validation_failed` sem arquivo candidato (I3): o dono corrige o repositório e reenvia.
     *
     * @param  list<QuarantinedFile>  $files
     */
    public function completeFetch(CommitSha $commit, ValidationReport $report, array $files): void
    {
        $this->ensureStatus(VersionStatus::Fetching, 'concluir a busca');
        if ($report->isBlocked() && $files !== []) {
            throw new LogicException('Versão bloqueada pela política não tem arquivos candidatos.');
        }

        $this->moveTo($report->isBlocked() ? VersionStatus::ValidationFailed : VersionStatus::AwaitingCommitReview, null);
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

        $this->moveTo(VersionStatus::FetchFailed, null);
        $this->fetch_failure = $failure->value;
        $this->fetched_at = CarbonImmutable::now();
        $this->save();

        $this->recordEvent(new RepositoryFetchFailed($this->id, $this->game_id, $failure->value));
    }

    /** Portão 1: o admin aprova o commit buscado. Nunca com violação bloqueante (I3). */
    public function approveCommit(User $actor): void
    {
        $this->ensureStatus(VersionStatus::AwaitingCommitReview, 'ter o commit aprovado');
        if (! is_array($this->validation_report) || ($this->validation_report['blocked'] ?? true) !== false) {
            throw new CommitHasBlockingViolations;
        }

        $this->moveTo(VersionStatus::AwaitingFileReview, $actor);
        $this->commit_decided_by_id = $actor->id;
        $this->commit_decided_at = CarbonImmutable::now();
        $this->save();

        $this->recordEvent(new CommitApproved($this->id, $this->game_id, $this->number, (string) $this->commit_sha, $actor->id));
    }

    /** Portão 1: o admin rejeita o commit, com motivo (I11). */
    public function rejectCommit(User $actor, DecisionReason $reason): void
    {
        $this->ensureStatus(VersionStatus::AwaitingCommitReview, 'ter o commit rejeitado');

        $this->moveTo(VersionStatus::CommitRejected, $actor);
        $this->commit_decided_by_id = $actor->id;
        $this->commit_decided_at = CarbonImmutable::now();
        $this->commit_rejection_reason = $reason->value;
        $this->save();

        $this->recordEvent(new CommitRejected($this->id, $this->game_id, $this->number, (string) $this->commit_sha, $actor->id, $reason->value));
    }

    /**
     * Portão 2: decisão sobre um arquivo, só em `awaiting_file_review` (I4) e só para o hash que o
     * admin analisou (I5). O admin pode mudar de ideia sobre uma aprovação; rejeitar um arquivo
     * (todos são obrigatórios) rejeita a versão, com o motivo (I11).
     */
    public function reviewFile(User $actor, VersionFile $file, string $analyzedSha256, FileDecision $decision, ?DecisionReason $reason): FileReview
    {
        $this->ensureStatus(VersionStatus::AwaitingFileReview, 'ter arquivos revisados');
        if ($file->game_version_id !== $this->id) {
            throw new FileNotInVersion;
        }
        if (! hash_equals($file->sha256, $analyzedSha256)) {
            throw new StaleFileReview;
        }
        if ($decision === FileDecision::Rejected && $reason === null) {
            throw new InvalidDecisionReason;
        }

        $review = FileReview::record($file, $decision, $actor, $reason);
        $file->markReviewed($decision->reviewStatus());
        $this->recordEvent(new FileReviewed($this->id, $file->id, $file->path, $file->sha256, $decision, $actor->id, $reason?->value));

        if ($decision === FileDecision::Rejected) {
            $this->reject($actor, "Arquivo {$file->path} rejeitado: {$reason->value}");
        }

        return $review;
    }

    /** Portão 2: o admin rejeita a versão inteira, com motivo (I11). */
    public function rejectVersion(User $actor, DecisionReason $reason): void
    {
        $this->ensureStatus(VersionStatus::AwaitingFileReview, 'ser rejeitada');
        $this->reject($actor, $reason->value);
    }

    /** Todos os arquivos aprovados: pronta para a publicação (Fase 5), que ainda confere os hashes (I6). */
    public function allFilesApproved(): bool
    {
        return $this->files->isNotEmpty()
            && $this->files->every(fn (VersionFile $file): bool => $file->review_status === FileReviewStatus::Approved);
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

    /** @return BelongsTo<User, $this> */
    public function commitDecidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'commit_decided_by_id');
    }

    /** @return BelongsTo<User, $this> */
    public function rejectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by_id');
    }

    /** @return HasMany<VersionFile, $this> */
    public function files(): HasMany
    {
        return $this->hasMany(VersionFile::class)->orderBy('path');
    }

    /**
     * Um admin viu a versão no estado atual (fila ou detalhe): ela deixa de ser "nova".
     * Não é decisão de portão, então não gera auditoria.
     */
    public function markSeen(): void
    {
        if ($this->status_seen_at === null) {
            $this->status_seen_at = CarbonImmutable::now();
            $this->save();
        }
    }

    /**
     * Toda transição passa por aqui. Mudança feita por um admin já nasce vista; a do sistema
     * ou do dono (submissão, reenvio, fim da busca) fica "nova" até um admin ver.
     */
    private function moveTo(VersionStatus $status, ?User $admin): void
    {
        $this->status = $status;
        $this->status_seen_at = $admin === null ? null : CarbonImmutable::now();
    }

    private function reject(User $actor, string $reason): void
    {
        $this->moveTo(VersionStatus::Rejected, $actor);
        $this->rejected_by_id = $actor->id;
        $this->rejected_at = CarbonImmutable::now();
        $this->rejection_reason = $reason;
        $this->save();

        $this->recordEvent(new VersionRejected($this->id, $this->game_id, $this->number, $actor->id, $reason));
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
            'commit_decided_at' => 'immutable_datetime',
            'rejected_at' => 'immutable_datetime',
            'status_seen_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
