<?php

declare(strict_types=1);

use App\Modules\Audit\Domain\AuditEntry;
use App\Modules\Catalog\Application\Actions\SubmitGame;
use App\Modules\Catalog\Application\SubmitGameInput;
use App\Modules\Catalog\Domain\Game;
use App\Modules\Catalog\Domain\GameStatus;
use App\Modules\Identity\Domain\User;
use App\Modules\Versioning\Domain\Events\ImportRequested;
use App\Modules\Versioning\Domain\GameVersion;
use App\Modules\Versioning\Domain\VersionStatus;
use App\Shared\Domain\Git\GitRef;
use App\Shared\Domain\Git\RepositoryUrl;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\Support\Images;

beforeEach(function (): void {
    Storage::fake('thumbnails');
    Http::fake();
    Queue::fake();
});

/** @return array<string, mixed> */
function submission(array $overrides = []): array
{
    return array_merge([
        'name' => 'Snake',
        'description' => 'O clássico da cobrinha.',
        'author_name' => 'Ada Lovelace',
        'repository_url' => 'https://github.com/ada/snake.git',
        'ref' => 'main',
        'thumbnail' => UploadedFile::fake()->createWithContent('capa.png', Images::png()),
    ], $overrides);
}

function nothingWasCreated(): void
{
    expect(Game::count())->toBe(0)
        ->and(GameVersion::count())->toBe(0)
        ->and(AuditEntry::count())->toBe(0)
        ->and(Storage::disk('thumbnails')->allFiles())->toBe([]);
}

it('o developer submete um jogo: nasce submitted, com a versão 1 aguardando o Portão 0, tudo auditado', function (): void {
    $developer = User::factory()->developer()->create();

    $response = $this->actingAs($developer)
        ->withServerVariables(['REMOTE_ADDR' => '203.0.113.7'])
        ->postJson('/api/v1/games', submission())
        ->assertCreated()
        ->assertJsonPath('data.slug', 'snake')
        ->assertJsonPath('data.name', 'Snake')
        ->assertJsonPath('data.author_name', 'Ada Lovelace')
        ->assertJsonPath('data.repository_url', 'https://github.com/ada/snake')
        ->assertJsonPath('data.default_ref', 'main')
        ->assertJsonPath('data.status', 'submitted');

    $game = Game::sole();
    $version = GameVersion::sole();
    expect($game->status)->toBe(GameStatus::Submitted)
        ->and($game->owner_id)->toBe($developer->id)
        ->and($response->json('data.thumbnail_url'))->toBe("/media/thumbnails/{$game->thumbnail_path}")
        ->and($version->game_id)->toBe($game->id)
        ->and($version->number)->toBe(1)
        ->and($version->status)->toBe(VersionStatus::ImportRequested)
        ->and($version->repository_url)->toBe('https://github.com/ada/snake')
        ->and($version->requested_ref)->toBe('main')
        ->and($version->requested_by_id)->toBe($developer->id);
    Storage::disk('thumbnails')->assertExists($game->thumbnail_path);

    $entries = AuditEntry::query()->orderBy('action')->get();
    expect($entries->pluck('action')->all())->toBe(['catalog.game-submitted', 'versioning.import-requested'])
        ->and($entries->pluck('actor_id')->unique()->all())->toBe([$developer->id])
        ->and($entries->pluck('ip_address')->unique()->all())->toBe(['203.0.113.7'])
        ->and($entries[0]->subject_id)->toBe($game->id)
        ->and($entries[0]->context)->toEqual(['slug' => 'snake', 'repository_url' => 'https://github.com/ada/snake', 'ref' => 'main'])
        ->and($entries[1]->subject_type)->toBe('game_version')
        ->and($entries[1]->subject_id)->toBe($version->id)
        ->and($entries[1]->context)->toEqual(['game_id' => $game->id, 'number' => 1, 'repository_url' => 'https://github.com/ada/snake', 'ref' => 'main']);
});

it('a submissão não acessa a rede nem agenda nenhum job (I2)', function (): void {
    $this->actingAs(User::factory()->developer()->create())->postJson('/api/v1/games', submission())->assertCreated();

    Http::assertNothingSent();
    Queue::assertNothingPushed();
});

it('rejeita URL inválida, de host fora da allowlist ou com credenciais, sem criar nada nem acessar a rede (G1–G3)', function (string $url): void {
    $this->actingAs(User::factory()->developer()->create())
        ->postJson('/api/v1/games', submission(['repository_url' => $url]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['repository_url' => 'Use o endereço HTTPS de um repositório público no GitHub, GitLab ou Codeberg.']);

    nothingWasCreated();
    Http::assertNothingSent();
    Queue::assertNothingPushed();
})->with([
    'http' => 'http://github.com/ada/snake',
    'ssh' => 'git@github.com:ada/snake.git',
    'file' => 'file:///etc/passwd',
    'host fora da allowlist' => 'https://bitbucket.org/ada/snake',
    'IP da LAN' => 'https://192.168.0.10/ada/snake',
    'serviço interno' => 'https://postgres/ada/snake',
    'credenciais' => 'https://user:token@github.com/ada/snake',
    'porta' => 'https://github.com:8443/ada/snake',
]);

it('rejeita refs que poderiam virar opção ou comando do git (G1)', function (string $ref): void {
    $this->actingAs(User::factory()->developer()->create())
        ->postJson('/api/v1/games', submission(['ref' => $ref]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['ref']);

    nothingWasCreated();
})->with(['--upload-pack=touch /tmp/x', 'main;id', '$(id)', 'feature/../main']);

// A triagem do FormRequest recusa pelo tipo; o que passa por ela disfarçado é recusado pelo
// ThumbnailProcessor, que confere o conteúdo. Nos dois casos nada é gravado.
it('rejeita thumbnails que não são PNG, JPEG ou WebP (A4)', function (string $file, string $bytes): void {
    $this->actingAs(User::factory()->developer()->create())
        ->postJson('/api/v1/games', submission(['thumbnail' => UploadedFile::fake()->createWithContent($file, $bytes)]))
        ->assertUnprocessable();

    nothingWasCreated();
})->with([
    'svg' => ['capa.svg', Images::svg()],
    'svg disfarçado de png' => ['capa.png', Images::svg()],
    'script disfarçado de png' => ['capa.png', '<?php system($_GET["c"]);'],
    'gif' => ['capa.gif', Images::gif()],
]);

it('o conteúdo da thumbnail é conferido além do tipo declarado (bomba de descompressão)', function (): void {
    $bomb = UploadedFile::fake()->createWithContent('capa.png', Images::pngHeaderClaiming(100_000, 100_000));

    $this->actingAs(User::factory()->developer()->create())
        ->postJson('/api/v1/games', submission(['thumbnail' => $bomb]))
        ->assertUnprocessable()
        ->assertJsonPath('message', 'A thumbnail deve ter entre 64 e 4096 pixels de largura e de altura.');

    nothingWasCreated();
});

it('valida os campos da submissão', function (): void {
    $this->actingAs(User::factory()->developer()->create())
        ->postJson('/api/v1/games', [
            'name' => 'S',
            'description' => str_repeat('a', 2001),
            'author_name' => '',
            'repository_url' => '',
            'ref' => '',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name', 'description', 'author_name', 'repository_url', 'ref', 'thumbnail']);
});

it('gera um slug livre quando o nome já está em uso', function (): void {
    Game::factory()->create(['slug' => 'snake']);
    Game::factory()->create(['slug' => 'snake-2']);

    $this->actingAs(User::factory()->developer()->create())
        ->postJson('/api/v1/games', submission())
        ->assertCreated()
        ->assertJsonPath('data.slug', 'snake-3');
});

it('se a criação da versão falhar, nada é gravado e a thumbnail é descartada', function (): void {
    Event::listen(ImportRequested::class, fn () => throw new RuntimeException('falha simulada'));

    $this->actingAs(User::factory()->developer()->create())->postJson('/api/v1/games', submission())->assertServerError();

    nothingWasCreated();
});

it('só developer com e-mail verificado submete', function (User $user): void {
    $this->actingAs($user)->postJson('/api/v1/games', submission())->assertForbidden();

    nothingWasCreated();
})->with([
    'player' => fn () => User::factory()->create(),
    'developer sem e-mail verificado' => fn () => User::factory()->unverified()->developer()->create(),
]);

it('visitante recebe 401', function (): void {
    $this->postJson('/api/v1/games', submission())->assertUnauthorized();
});

it('a Action recusa quem não tem games.submit mesmo sem passar pela rota (defesa em profundidade)', function (): void {
    $player = User::factory()->create();
    $input = new SubmitGameInput(
        name: 'Snake',
        description: 'x',
        authorName: 'Ada',
        repositoryUrl: RepositoryUrl::fromString('https://github.com/ada/snake'),
        ref: GitRef::fromString('main'),
        thumbnailPath: '/tmp/inexistente',
    );

    expect(fn () => app(SubmitGame::class)($player, $input))->toThrow(AuthorizationException::class);
    nothingWasCreated();
});

it('limita a 10 submissões por hora por usuário', function (): void {
    $developer = User::factory()->developer()->create();

    for ($i = 1; $i <= 10; $i++) {
        $this->actingAs($developer)->postJson('/api/v1/games', submission(['name' => "Jogo {$i}"]))->assertCreated();
    }

    $this->actingAs($developer)->postJson('/api/v1/games', submission(['name' => 'Jogo 11']))->assertTooManyRequests();
    expect(Game::count())->toBe(10);
});
