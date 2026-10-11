<?php

declare(strict_types=1);

use App\Modules\Audit\Domain\AuditEntry;
use App\Modules\Audit\Domain\AuditRecord;
use App\Modules\Identity\Application\Actions\PromoteToDeveloper;
use App\Modules\Identity\Domain\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

function auditEntry(string $subjectId = 'S1', ?string $actorId = null, string $at = '2026-10-01 12:00:00'): AuditEntry
{
    $entry = AuditEntry::fromRecord(
        new AuditRecord('identity.role-granted', 'user', $subjectId, $actorId, ['role' => 'developer']),
        CarbonImmutable::parse($at),
    );
    $entry->save();

    return $entry;
}

describe('append-only (I11)', function (): void {
    it('o banco rejeita UPDATE', function (): void {
        $entry = auditEntry();

        expect(fn () => DB::table('audit_entries')->where('id', $entry->id)->update(['reason' => 'adulterado']))
            ->toThrow(QueryException::class, 'append-only');
    });

    it('o banco rejeita DELETE', function (): void {
        $entry = auditEntry();

        expect(fn () => DB::table('audit_entries')->where('id', $entry->id)->delete())
            ->toThrow(QueryException::class, 'append-only');
    });

    it('o banco rejeita TRUNCATE', function (): void {
        auditEntry();

        expect(fn () => DB::statement('TRUNCATE audit_entries'))->toThrow(QueryException::class, 'append-only');
    });

    it('um usuário que é ator de auditoria não pode ser apagado', function (): void {
        $actor = User::factory()->create();
        auditEntry(actorId: $actor->id);

        expect(fn () => $actor->delete())->toThrow(QueryException::class);
    });
});

it('a auditoria é gravada na transação da ação: se ela falhar, nada fica registrado', function (): void {
    $admin = User::factory()->admin()->create();
    $player = User::factory()->create();

    try {
        DB::transaction(function () use ($admin, $player): void {
            app(PromoteToDeveloper::class)($admin, $player->id);

            throw new RuntimeException('falha depois da concessão');
        });
    } catch (RuntimeException) {
    }

    expect(AuditEntry::count())->toBe(0)
        ->and($player->fresh()->hasRole('developer'))->toBeFalse();
});

it('o admin consulta o histórico de uma entidade, do mais recente ao mais antigo', function (): void {
    $admin = User::factory()->admin()->create(['name' => 'Rafael']);
    auditEntry('S1', $admin->id, '2026-10-01 10:00:00');
    $latest = auditEntry('S1', null, '2026-10-02 10:00:00');
    auditEntry('S2', null, '2026-10-03 10:00:00');

    $this->actingAs($admin)->getJson('/api/v1/admin/audit-entries?subject_type=user&subject_id=S1')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.id', $latest->id)
        ->assertJsonPath('data.0.actor', null)
        ->assertJsonPath('data.1.actor.name', 'Rafael')
        ->assertJsonPath('data.1.context', ['role' => 'developer']);
});

it('valida os filtros da consulta', function (): void {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->getJson('/api/v1/admin/audit-entries?subject_type=User;DROP')
        ->assertUnprocessable()
        ->assertJsonValidationErrors('subject_type');
});
