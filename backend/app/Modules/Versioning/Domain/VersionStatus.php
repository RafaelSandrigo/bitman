<?php

declare(strict_types=1);

namespace App\Modules\Versioning\Domain;

/**
 * Ciclo de vida da versão (docs/dominio.md). As transições entram com as fases que as
 * implementam; estados terminais nunca voltam.
 */
enum VersionStatus: string
{
    case ImportRequested = 'import_requested';
    case ImportRejected = 'import_rejected';
    case Fetching = 'fetching';
    case FetchFailed = 'fetch_failed';
    case AwaitingCommitReview = 'awaiting_commit_review';
    case CommitRejected = 'commit_rejected';
    case AwaitingFileReview = 'awaiting_file_review';
    case Rejected = 'rejected';
    case Published = 'published';
    case Superseded = 'superseded';
    case Withdrawn = 'withdrawn';

    /**
     * Versões em andamento: no máximo uma por jogo (índice parcial no banco).
     *
     * @return list<self>
     */
    public static function inProgress(): array
    {
        return [self::ImportRequested, self::Fetching, self::AwaitingCommitReview, self::AwaitingFileReview];
    }

    public function isInProgress(): bool
    {
        return in_array($this, self::inProgress(), true);
    }

    /** Para mensagens: "A versão está {label}…". */
    public function label(): string
    {
        return match ($this) {
            self::ImportRequested => 'aguardando autorização',
            self::ImportRejected => 'com a importação rejeitada',
            self::Fetching => 'em busca',
            self::FetchFailed => 'com a busca falha',
            self::AwaitingCommitReview => 'aguardando a revisão do commit',
            self::CommitRejected => 'com o commit rejeitado',
            self::AwaitingFileReview => 'aguardando a revisão dos arquivos',
            self::Rejected => 'rejeitada',
            self::Published => 'publicada',
            self::Superseded => 'substituída',
            self::Withdrawn => 'retirada',
        };
    }
}
