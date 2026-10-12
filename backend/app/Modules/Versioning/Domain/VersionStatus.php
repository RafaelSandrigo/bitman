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
}
