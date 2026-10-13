<?php

declare(strict_types=1);

namespace App\Modules\Play\Domain;

/** `open` → `scored` (recebeu o score) | `expired` (outra partida começou ou o prazo venceu). */
enum SessionStatus: string
{
    case Open = 'open';
    case Scored = 'scored';
    case Expired = 'expired';
}
