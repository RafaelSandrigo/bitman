<?php

declare(strict_types=1);

use App\Modules\Audit\Domain\AuditEntry;
use App\Modules\Audit\Domain\AuditRecord;
use App\Modules\Catalog\Domain\Game;
use App\Modules\Identity\Application\Actions\PromoteToDeveloper;
use App\Modules\Identity\Domain\User;
use App\Modules\Versioning\Domain\GameVersion;
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

describe('linhas do tempo (Fase 7)', function (): void {
    function auditAbout(string $subjectType, string $subjectId, string $action, ?string $actorId, string $at, array $context = []): AuditEntry
    {
        $entry = AuditEntry::fromRecord(new AuditRecord($action, $subjectType, $subjectId, $actorId, $context), CarbonImmutable::parse($at));
        $entry->save();

        return $entry;
    }

    it('a linha do tempo do jogo reúne o jogo, as versões dele e os scores dele, e nada de outros jogos', function (): void {
        $admin = User::factory()->admin()->create();
        $game = Game::factory()->create();
        $other = Game::factory()->create();
        $version = GameVersion::factory()->create(['game_id' => $game->id]);
        $otherVersion = GameVersion::factory()->create(['game_id' => $other->id]);

        $submitted = auditAbout('game', $game->id, 'catalog.game-submitted', $game->owner_id, '2026-10-01 10:00:00');
        $fetched = auditAbout('game_version', $version->id, 'versioning.repository-fetched', null, '2026-10-01 11:00:00');
        $file = auditAbout('game_version', $version->id, 'versioning.file-approved', $admin->id, '2026-10-01 12:00:00', ['path' => 'index.html']);
        $score = auditAbout('score', '01JSCORE', 'play.score-hidden', $admin->id, '2026-10-01 13:00:00', ['game_id' => $game->id]);
        auditAbout('game', $other->id, 'catalog.game-submitted', null, '2026-10-01 10:30:00');
        auditAbout('game_version', $otherVersion->id, 'versioning.repository-fetched', null, '2026-10-01 11:30:00');
        auditAbout('score', '01JOTHER', 'play.score-hidden', $admin->id, '2026-10-01 13:30:00', ['game_id' => $other->id]);

        $this->actingAs($admin)->getJson("/api/v1/admin/audit-entries?game_id={$game->id}")
            ->assertOk()
            ->assertJsonCount(4, 'data')
            ->assertJsonPath('data.0.id', $score->id)
            ->assertJsonPath('data.1.id', $file->id)
            ->assertJsonPath('data.2.id', $fetched->id)
            ->assertJsonPath('data.3.id', $submitted->id);
    });

    it('a linha do tempo do usuário traz o que aconteceu com ele e o que ele fez', function (): void {
        $admin = User::factory()->admin()->create();
        $dev = User::factory()->developer()->create();
        $about = auditAbout('user', $dev->id, 'identity.role-granted', $admin->id, '2026-10-01 10:00:00');
        $did = auditAbout('game', '01JGAME', 'catalog.game-submitted', $dev->id, '2026-10-01 11:00:00');
        auditAbout('game', '01JGAME', 'catalog.slug-changed', $admin->id, '2026-10-01 12:00:00');

        $this->actingAs($admin)->getJson("/api/v1/admin/audit-entries?user_id={$dev->id}")
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $did->id)
            ->assertJsonPath('data.1.id', $about->id);
    });

    it('filtra por ação e por ator', function (): void {
        $admin = User::factory()->admin()->create();
        $other = User::factory()->admin()->create();
        $match = auditAbout('game_version', '01JV1', 'versioning.version-rejected', $admin->id, '2026-10-01 10:00:00');
        auditAbout('game_version', '01JV1', 'versioning.commit-approved', $admin->id, '2026-10-01 11:00:00');
        auditAbout('game_version', '01JV2', 'versioning.version-rejected', $other->id, '2026-10-01 12:00:00');

        $this->actingAs($admin)->getJson("/api/v1/admin/audit-entries?action=versioning.version-rejected&actor_id={$admin->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $match->id);
    });

    it('valida os novos filtros', function (): void {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->getJson('/api/v1/admin/audit-entries?game_id=x;DROP&action=A B&actor_id=../etc&user_id=%27')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['game_id', 'action', 'actor_id', 'user_id']);
    });

    it('quem não tem audit.view não consulta (I12)', function (): void {
        $this->actingAs(User::factory()->developer()->create())->getJson('/api/v1/admin/audit-entries?game_id=01JAAAAAAAAAAAAAAAAAAAAAAA')
            ->assertForbidden();
    });
});
