<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain;

/** `pending` → `approved` (vira developer) | `rejected` (com motivo; pode pedir de novo). */
enum DeveloperRequestStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
}
