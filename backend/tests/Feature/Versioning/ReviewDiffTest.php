<?php

declare(strict_types=1);

use App\Modules\Identity\Domain\User;
use App\Modules\Versioning\Domain\ChangeType;
use Illuminate\Support\Facades\Storage;
use Tests\Support\Artifacts;
use Tests\Support\Publication;

/*
| M7: no Portão 2, um arquivo `modified` vem com o conteúdo da versão publicada, lido do archive e
| conferido contra o hash aprovado dela (I6, I7), para o diff linha a linha (J5: tudo como texto).
*/

beforeEach(function (): void {
    Storage::fake('quarantine');
    $this->root = Artifacts::temporaryRoot();
    $this->admin = User::factory()->admin()->create();
    $this->published = Publication::published($this->admin, contents: ['game.js' => "let a = 1;\nlet b = 2;\n"]);
    $this->candidate = Publication::awaitingReview($this->published->game, ['game.js' => "let a = 1;\nlet b = 3;\n"], ['game.js' => ChangeType::Modified]);
});

afterEach(function (): void {
    Artifacts::remove($this->root);
});

function fileContent(User $admin, string $versionId, string $path)
{
    $file = test()->candidate->files()->where('path', $path)->sole();

    return test()->actingAs($admin)->getJson("/api/v1/admin/versions/{$versionId}/files/{$file->id}/content");
}

it('arquivo modificado vem com o conteúdo da versão publicada, do archive', function (): void {
    fileContent($this->admin, $this->candidate->id, 'game.js')
        ->assertOk()
        ->assertJsonPath('data.content', "let a = 1;\nlet b = 3;\n")
        ->assertJsonPath('data.change_type', 'modified')
        ->assertJsonPath('data.previous.version_number', 1)
        ->assertJsonPath('data.previous.content', "let a = 1;\nlet b = 2;\n");
});

it('arquivo adicionado não tem versão anterior', function (): void {
    fileContent($this->admin, $this->candidate->id, 'style.css')->assertOk()->assertJsonPath('data.previous', null);
});

it('archive que não confere com o hash aprovado nunca aparece como "anterior"', function (): void {
    $archived = "{$this->root}/archive/{$this->published->game_id}/v1/game.js";
    chmod($archived, 0644);
    file_put_contents($archived, 'adulterado');

    fileContent($this->admin, $this->candidate->id, 'game.js')
        ->assertOk()
        ->assertJsonPath('data.previous', null)
        ->assertJsonPath('data.previous_unavailable', true);
});
