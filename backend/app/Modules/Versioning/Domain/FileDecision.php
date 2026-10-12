<?php

declare(strict_types=1);

namespace App\Modules\Versioning\Domain;

/** Decisão do Portão 2 sobre um arquivo, sempre vinculada ao SHA-256 analisado (I5). */
enum FileDecision: string
{
    case Approved = 'approved';
    case Rejected = 'rejected';

    public function reviewStatus(): FileReviewStatus
    {
        return match ($this) {
            self::Approved => FileReviewStatus::Approved,
            self::Rejected => FileReviewStatus::Rejected,
        };
    }
}
