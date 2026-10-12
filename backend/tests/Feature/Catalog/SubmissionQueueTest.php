<?php

declare(strict_types=1);

use App\Modules\Audit\Domain\AuditEntry;
use App\Modules\Catalog\Application\Actions\ChangeGameSlug;
use App\Modules\Catalog\Domain\Game;
use App\Modules\Catalog\Domain\Slug;
use App\Modules\Identity\Domain\User;
use Illuminate\Auth\Access\AuthorizationException;

it('o admin vê a fila de submissões, da mais antiga à mais recente, com o dono', function (): void {
    $admin = User::factory()->admin()->create();
    $owner = User::factory()->developer()->create(['name' => 'Ada']);
    Game::factory()->for($owner, 'owner')->create(['name' => 'Segundo', 'created_at' => now()->subHour()]);
    Game::factory()->for($owner, 'owner')->create(['name' => 'Primeiro', 'created_at' => now()->subDay()]);
    Game::factory()->published()->create();
    Game::factory()->rejected()->create();

    $this->actingAs($admin)->getJson('/api/v1/admin/submissions')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.name', 'Primeiro')
        ->assertJsonPath('data.1.name', 'Segundo')
        ->assertJsonPath('data.0.owner', ['id' => $owner->id, 'name' => 'Ada', 'email' => $owner->email])
        ->assertJsonPath('meta.total', 2);
});

it('o admin ajusta o slug antes da publicação e a mudança é auditada', function (): void {
    $admin = User::factory()->admin()->create();
    $game = Game::factory()->create(['slug' => 'snake']);

    $this->actingAs($admin)->patchJson("/api/v1/admin/games/{$game->id}/slug", ['slug' => 'snake-classico'])
        ->assertOk()
        ->assertJsonPath('data.slug', 'snake-classico');

    $entry = AuditEntry::sole();
    expect($game->fresh()?->slug)->toBe('snake-classico')
        ->and($entry->action)->toBe('catalog.slug-changed')
        ->and($entry->subject_id)->toBe($game->id)
        ->and($entry->actor_id)->toBe($admin->id)
        ->and($entry->context)->toEqual(['from' => 'snake', 'to' => 'snake-classico']);
});

it('manter o mesmo slug não gera auditoria', function (): void {
    $admin = User::factory()->admin()->create();
    $game = Game::factory()->create(['slug' => 'snake']);

    $this->actingAs($admin)->patchJson("/api/v1/admin/games/{$game->id}/slug", ['slug' => 'snake'])->assertOk();

    expect(AuditEntry::count())->toBe(0);
});

it('o slug é imutável depois da publicação', function (string $state): void {
    $admin = User::factory()->admin()->create();
    $game = Game::factory()->{$state}()->create(['slug' => 'snake']);

    $this->actingAs($admin)->patchJson("/api/v1/admin/games/{$game->id}/slug", ['slug' => 'outro'])
        ->assertUnprocessable()
        ->assertJsonPath('message', 'O slug não pode mudar depois que o jogo foi publicado.');

    expect($game->fresh()?->slug)->toBe('snake')->and(AuditEntry::count())->toBe(0);
})->with(['published', 'blocked']);

it('recusa slug já usado por outro jogo', function (): void {
    $admin = User::factory()->admin()->create();
    Game::factory()->create(['slug' => 'pacman']);
    $game = Game::factory()->create(['slug' => 'snake']);

    $this->actingAs($admin)->patchJson("/api/v1/admin/games/{$game->id}/slug", ['slug' => 'pacman'])
        ->assertUnprocessable()
        ->assertJsonPath('message', 'O slug pacman já é usado por outro jogo.');

    expect(AuditEntry::count())->toBe(0);
});

it('valida o formato do slug', function (string $slug): void {
    $admin = User::factory()->admin()->create();
    $game = Game::factory()->create();

    $this->actingAs($admin)->patchJson("/api/v1/admin/games/{$game->id}/slug", ['slug' => $slug])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['slug']);
})->with(['Snake', 'pac man', '-x', '', 'ação']);

it('responde 404 para jogo inexistente', function (): void {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->patchJson('/api/v1/admin/games/01JAAAAAAAAAAAAAAAAAAAAAAA/slug', ['slug' => 'x-y'])->assertNotFound();
});

it('a Action recusa quem não é admin mesmo sem passar pela rota (I12, defesa em profundidade)', function (): void {
    $owner = User::factory()->developer()->create();
    $game = Game::factory()->for($owner, 'owner')->create(['slug' => 'snake']);

    expect(fn () => app(ChangeGameSlug::class)($owner, $game->id, Slug::fromString('meu-slug')))->toThrow(AuthorizationException::class)
        ->and($game->fresh()?->slug)->toBe('snake');
});

it('filtra os jogos por estado (publicados e bloqueados, para o bloqueio)', function (): void {
    Game::factory()->create();
    $published = Game::factory()->published()->create();
    Game::factory()->blocked()->create();
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->getJson('/api/v1/admin/submissions?status=published')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $published->id);
    $this->actingAs($admin)->getJson('/api/v1/admin/submissions?status=hackeado')->assertUnprocessable();
});
