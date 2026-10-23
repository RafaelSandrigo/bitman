<?php

declare(strict_types=1);

use App\Modules\Catalog\Domain\Game;
use App\Modules\Identity\Domain\User;
use App\Modules\Versioning\Domain\GameVersion;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Tests\Support\Artifacts;
use Tests\Support\Publication;
use Tests\Support\Snapshots;

/*
| M12: reconciliação do que está no ar (I6, I7, J6). Compara as versões publicadas com o
| `served/` e corrige só a partir do archive, conferindo os hashes aprovados.
*/

beforeEach(function (): void {
    Storage::fake('quarantine');
    $this->root = Artifacts::temporaryRoot();
    $this->admin = User::factory()->admin()->create();
});

afterEach(function (): void {
    Artifacts::remove($this->root);
});

function servedFile(string $root, GameVersion $version, string $path = 'game.js'): string
{
    return "{$root}/served/{$version->public_id}/{$path}";
}

it('tudo certo: não muda nada', function (): void {
    $version = Publication::published($this->admin);
    $before = file_get_contents(servedFile($this->root, $version));

    $this->artisan('bitman:reconcile-served')->expectsOutputToContain('nenhuma correção')->assertSuccessful();

    expect(file_get_contents(servedFile($this->root, $version)))->toBe($before);
});

it('versão publicada fora do ar volta a ser servida a partir do archive', function (): void {
    Log::spy();
    $version = Publication::published($this->admin);
    Artifacts::remove("{$this->root}/served/{$version->public_id}");

    $this->artisan('bitman:reconcile-served')->assertSuccessful();

    expect(file_get_contents(servedFile($this->root, $version)))->toBe(Snapshots::gameFiles()['game.js']);
    Log::shouldHaveReceived('warning')->withArgs(fn (string $message, array $context): bool => $message === 'served.restored' && $context['public_id'] === $version->public_id);
});

it('I6: conteúdo servido diferente do aprovado é trocado pelo do archive', function (): void {
    $version = Publication::published($this->admin);
    $file = servedFile($this->root, $version);
    chmod($file, 0644);
    file_put_contents($file, 'minerador()');

    $this->artisan('bitman:reconcile-served')->assertSuccessful();

    expect(file_get_contents($file))->toBe(Snapshots::gameFiles()['game.js']);
});

it('archive adulterado: o jogo fica fora do ar e o comando falha com alerta', function (): void {
    Log::spy();
    $version = Publication::published($this->admin);
    Artifacts::remove("{$this->root}/served/{$version->public_id}");
    $archived = "{$this->root}/archive/{$version->game_id}/v1/game.js";
    chmod($archived, 0644);
    file_put_contents($archived, 'minerador()');

    $this->artisan('bitman:reconcile-served')->assertFailed();

    expect(file_exists("{$this->root}/served/{$version->public_id}"))->toBeFalse();
    Log::shouldHaveReceived('error')->withArgs(fn (string $message, array $context): bool => $message === 'served.restore_failed' && $context['version_id'] === $version->id);
});

it('J6: diretório servido sem versão publicada (jogo bloqueado ou desconhecido) sai do ar', function (): void {
    $version = Publication::published($this->admin);
    Game::query()->whereKey($version->game_id)->update(['status' => 'blocked', 'blocked_by_id' => $this->admin->id, 'block_reason' => 'x', 'blocked_at' => now()]);
    $stray = "{$this->root}/served/11111111-2222-4333-8444-555555555555";
    mkdir($stray, 0755);
    file_put_contents("{$stray}/index.html", 'órfão');

    $this->artisan('bitman:reconcile-served')->assertSuccessful();

    expect(file_exists("{$this->root}/served/{$version->public_id}"))->toBeFalse()
        ->and(file_exists($stray))->toBeFalse();
});

it('roda de hora em hora pelo scheduler', function (): void {
    $events = collect(app(Schedule::class)->events())->filter(fn ($event): bool => str_contains((string) $event->command, 'bitman:reconcile-served'));

    expect($events)->toHaveCount(1)
        ->and($events->first()->expression)->toBe('0 * * * *');
});
