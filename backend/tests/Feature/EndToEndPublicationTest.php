<?php

declare(strict_types=1);

use App\Modules\Audit\Domain\AuditEntry;
use App\Modules\Identity\Domain\User;
use App\Modules\Ingestion\Domain\GitSourceFetcher;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\Artifacts;
use Tests\Support\FakeGitSourceFetcher;
use Tests\Support\Images;
use Tests\Support\Snapshots;

/*
| Aceite da Fase 5, pela API: submeter → autorizar → aprovar commit → aprovar arquivos → publicar →
| jogo no catálogo, com os arquivos servidos idênticos ao conteúdo aprovado. A busca usa a sandbox
| falsa (o harness da sandbox real tem os próprios testes); o volume de artefatos é um disco real.
*/

beforeEach(function (): void {
    Storage::fake('quarantine');
    Storage::fake('thumbnails');
    $this->root = Artifacts::temporaryRoot();
    app()->instance(GitSourceFetcher::class, FakeGitSourceFetcher::returning(Snapshots::valid()));
});

afterEach(function (): void {
    Artifacts::remove($this->root);
});

it('do envio pelo developer até o jogo publicado e jogável', function (): void {
    $developer = User::factory()->developer()->create();
    $admin = User::factory()->admin()->create();

    // 1. Submissão (nenhum acesso à rede, I2).
    $gameId = $this->actingAs($developer)->post('/api/v1/games', [
        'name' => 'Snake',
        'description' => 'O clássico da cobrinha.',
        'author_name' => 'Ada',
        'repository_url' => 'https://github.com/ada/snake',
        'ref' => 'main',
        'thumbnail' => UploadedFile::fake()->createWithContent('capa.png', Images::png()),
    ], ['Accept' => 'application/json'])->assertCreated()->json('data.id');
    $this->getJson('/api/v1/games')->assertJsonCount(0, 'data');

    // 2. Portão 0 → busca (fila síncrona nos testes) → Portão 1.
    $versionId = $this->actingAs($admin)->getJson('/api/v1/admin/versions?status=import_requested')->json('data.0.id');
    $this->actingAs($admin)->postJson("/api/v1/admin/versions/{$versionId}/authorize-import")->assertOk();
    $this->actingAs($admin)->postJson("/api/v1/admin/versions/{$versionId}/approve-commit")->assertOk()->assertJsonPath('data.status', 'awaiting_file_review');

    // 3. Portão 2: abrir cada arquivo e aprovar o hash exibido (I5).
    $files = $this->actingAs($admin)->getJson("/api/v1/admin/versions/{$versionId}")->json('data.files');
    expect($files)->toHaveCount(3);
    foreach ($files as $file) {
        $shown = $this->actingAs($admin)->getJson("/api/v1/admin/versions/{$versionId}/files/{$file['id']}/content")->json('data');
        expect($shown['content'])->toBe(Snapshots::gameFiles()[$file['path']]);
        $this->actingAs($admin)->postJson("/api/v1/admin/versions/{$versionId}/files/{$file['id']}/review", ['decision' => 'approved', 'sha256' => $shown['sha256']])->assertOk();
    }

    // 4. Publicação.
    $this->actingAs($admin)->postJson("/api/v1/admin/versions/{$versionId}/publish")->assertOk()->assertJsonPath('data.status', 'published');

    // 5. O jogo aparece no catálogo público e os arquivos servidos são exatamente os aprovados.
    $game = $this->getJson('/api/v1/games')->assertJsonCount(1, 'data')->json('data.0');
    expect($game['name'])->toBe('Snake')
        ->and($game['play_url'])->toMatch('#^https://bitman-games\.sandrigo\.com\.br/[0-9a-f-]{36}/index\.html$#');
    $publicId = explode('/', (string) parse_url($game['play_url'], PHP_URL_PATH))[1];
    foreach (Snapshots::gameFiles() as $path => $content) {
        expect(file_get_contents("{$this->root}/served/{$publicId}/{$path}"))->toBe($content);
    }

    // O dono vê o jogo publicado; a auditoria conta a história inteira.
    $this->actingAs($developer)->getJson('/api/v1/me/games')->assertJsonPath('data.0.status', 'published');
    expect(AuditEntry::query()->where('subject_id', $versionId)->orderBy('id')->pluck('action')->all())->toBe([
        'versioning.import-requested',
        'versioning.import-authorized',
        'versioning.repository-fetched',
        'versioning.commit-approved',
        'versioning.file-approved',
        'versioning.file-approved',
        'versioning.file-approved',
        'versioning.version-published',
    ])->and($gameId)->toBeString();

    // Fase 7: a linha do tempo do jogo responde as perguntas da seção Auditoria do contexto do produto.
    $timeline = collect($this->actingAs($admin)->getJson("/api/v1/admin/audit-entries?game_id={$gameId}")->assertOk()->json('data'))
        ->reverse()->values();
    $first = fn (string $action): array => $timeline->firstWhere('action', $action) ?? throw new RuntimeException("sem {$action}");

    $submitted = $first('catalog.game-submitted');
    expect($submitted['actor']['id'])->toBe($developer->id)                                    // quem submeteu / informou a URL
        ->and($submitted['context']['repository_url'])->toBe('https://github.com/ada/snake');  // qual URL
    expect($first('versioning.import-authorized')['actor']['id'])->toBe($admin->id);           // quem autorizou a execução Git
    $fetched = $first('versioning.repository-fetched');
    expect($fetched['occurred_at'])->toBeString()                                              // quando foi o fetch
        ->and($fetched['context']['commit'])->toMatch('/^[0-9a-f]{40}$/');                     // qual commit
    expect($first('versioning.commit-approved')['actor']['id'])->toBe($admin->id);             // quem aprovou o commit
    $approvals = $timeline->where('action', 'versioning.file-approved');
    expect($approvals->pluck('context.path')->sort()->values()->all())->toBe(collect(array_keys(Snapshots::gameFiles()))->sort()->values()->all())
        ->and($approvals->every(fn (array $e): bool => $e['actor']['id'] === $admin->id && preg_match('/^[0-9a-f]{64}$/', $e['context']['sha256']) === 1))->toBeTrue(); // arquivo, quem, hash e quando
    $published = $first('versioning.version-published');
    expect($published['context']['public_id'])->toBe($publicId)                                // qual versão e qual URI
        ->and($published['context']['number'])->toBe(1)
        ->and($published['actor']['id'])->toBe($admin->id);
});
