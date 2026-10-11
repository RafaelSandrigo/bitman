<?php

declare(strict_types=1);

namespace App\Modules\Audit\Domain;

/**
 * Contrato público do módulo Audit: todo evento de domínio que o implementa gera um
 * AuditEntry na mesma transação (I11), por um listener síncrono.
 */
interface Auditable
{
    public function toAuditRecord(): AuditRecord;
}
