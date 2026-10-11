<?php

declare(strict_types=1);

namespace App\Modules\Audit\Infrastructure;

use App\Modules\Audit\Domain\Auditable;
use App\Modules\Audit\Domain\AuditEntry;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

/**
 * Listener síncrono (não implementa ShouldQueue): grava na transação de quem despachou o evento.
 * O IP só existe quando a ação veio de uma requisição HTTP roteada; no console e na fila fica nulo.
 */
final readonly class RecordAuditEntry
{
    public function __construct(private Request $request) {}

    public function handle(Auditable $event): void
    {
        $ip = $this->request->route() !== null ? $this->request->ip() : null;

        AuditEntry::fromRecord($event->toAuditRecord(), CarbonImmutable::now(), $ip)->save();
    }
}
