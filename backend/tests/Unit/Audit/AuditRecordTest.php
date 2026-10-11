<?php

declare(strict_types=1);

use App\Modules\Audit\Domain\AuditRecord;

it('aceita uma ação no formato contexto.verbo', function (): void {
    $record = new AuditRecord('identity.role-granted', 'user', '01J', null, ['role' => 'admin']);

    expect($record->action)->toBe('identity.role-granted')
        ->and($record->actorId)->toBeNull();
});

it('rejeita ações fora do formato', function (string $action): void {
    new AuditRecord($action, 'user', '01J', null);
})->with(['', 'granted', 'Identity.Granted', 'identity.'])->throws(InvalidArgumentException::class);

it('exige a entidade auditada', function (): void {
    new AuditRecord('identity.role-granted', 'user', '', null);
})->throws(InvalidArgumentException::class);
