<?php

declare(strict_types=1);

namespace App\Modules\Versioning\Domain;

/** Situação da revisão de um arquivo (Portão 2, Fase 4). */
enum FileReviewStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
}
