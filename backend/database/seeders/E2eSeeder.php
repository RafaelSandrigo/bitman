<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Catalog\Domain\Game;
use App\Modules\Catalog\Domain\GameStatus;
use App\Modules\Identity\Domain\Role;
use App\Modules\Identity\Domain\User;
use App\Modules\Versioning\Application\Actions\PublishVersion;
use App\Modules\Versioning\Application\Actions\ReviewFile;
use App\Modules\Versioning\Domain\ChangeType;
use App\Modules\Versioning\Domain\FileDecision;
use App\Modules\Versioning\Domain\GameVersion;
use App\Modules\Versioning\Domain\QuarantinedFile;
use App\Modules\Versioning\Domain\QuarantineStore;
use App\Modules\Versioning\Domain\VersionFile;
use App\Modules\Versioning\Domain\VersionStatus;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * M11: dados da stack E2E descartável (tests/e2e). Nunca roda fora de APP_ENV=e2e. A busca no Git
 * é pulada (a versão nasce já buscada), mas a revisão e a publicação passam pelas Actions de verdade:
 * o jogo servido é exatamente o que foi aprovado (I6).
 */
final class E2eSeeder extends Seeder
{
    public const PASSWORD = 'senha-e2e-123';

    /** Jogo-fixture: confere o próprio isolamento (J1, J3) e manda `game-over` com 42 ao clicar. */
    private const GAME_FILES = [
        'index.html' => <<<'HTML'
            <!doctype html>
            <html lang="pt-BR">
            <head><meta charset="utf-8"><title>E2E</title><link rel="stylesheet" href="style.css"></head>
            <body><p id="isolation">verificando…</p><button id="finish" type="button">Terminar</button><script src="game.js"></script></body>
            </html>
            HTML,
        'style.css' => "body { font-family: sans-serif; }\n",
        'game.js' => <<<'JS'
            function blocked(attempt) { try { attempt(); return false } catch (error) { return true } }
            const isolated = blocked(() => window.parent.document.title) && blocked(() => document.cookie) && blocked(() => localStorage.length)
            document.getElementById('isolation').textContent = isolated ? 'isolado: sim' : 'isolado: NÃO'
            parent.postMessage({ source: 'bitman', version: 1, type: 'ready' }, '*')
            document.getElementById('finish').addEventListener('click', () => parent.postMessage({ source: 'bitman', version: 1, type: 'game-over', score: 42 }, '*'))
            JS,
    ];

    public function run(): void
    {
        if (! app()->environment('e2e')) {
            throw new RuntimeException('O E2eSeeder só roda com APP_ENV=e2e; ambiente atual: '.app()->environment().'.');
        }

        $this->call(RolesAndPermissionsSeeder::class);
        $admin = $this->verifiedUser('Admin E2E', 'admin@e2e.test', Role::Admin);
        $this->verifiedUser('Jogadora E2E', 'player@e2e.test', null);
        $this->publishFixtureGame($admin);
    }

    private function verifiedUser(string $name, string $email, ?Role $role): User
    {
        $user = User::register($name, $email, self::PASSWORD);
        $user->forceFill(['email_verified_at' => now()])->save();
        if ($role !== null) {
            $user->grantRole($role, null);
        }

        return $user;
    }

    private function publishFixtureGame(User $admin): void
    {
        $game = Game::query()->forceCreate([
            'slug' => 'e2e', 'name' => 'Jogo E2E', 'description' => 'Fixture dos testes de ponta a ponta.', 'author_name' => 'E2E',
            'thumbnail_path' => str_repeat('0', 32).'.webp', 'owner_id' => $admin->id, 'repository_url' => 'https://github.com/bitman/e2e',
            'default_ref' => 'main', 'status' => GameStatus::Submitted,
        ]);
        $version = GameVersion::query()->forceCreate([
            'game_id' => $game->id, 'number' => 1, 'status' => VersionStatus::AwaitingFileReview, 'repository_url' => $game->repository_url,
            'requested_ref' => 'main', 'requested_by_id' => $admin->id, 'import_decided_by_id' => $admin->id, 'import_decided_at' => now(),
            'commit_sha' => str_repeat('e', 40), 'fetched_at' => now(), 'commit_decided_by_id' => $admin->id, 'commit_decided_at' => now(),
            'validation_report' => ['blocked' => false, 'violations' => [], 'ignored' => [], 'ignored_total' => 0],
        ]);

        foreach (self::GAME_FILES as $path => $content) {
            $sha256 = app(QuarantineStore::class)->put($version->id, $content);
            // O 4º campo é o object id do blob no Git, como a sandbox o informaria.
            $blobId = hash('sha1', 'blob '.strlen($content)."\0".$content);
            $file = VersionFile::record($version, new QuarantinedFile($path, $sha256, strlen($content), $blobId, ChangeType::Added));
            app(ReviewFile::class)($admin, $version->id, $file->id, $sha256, FileDecision::Approved, null);
        }
        app(PublishVersion::class)($admin, $version->id);
    }
}
