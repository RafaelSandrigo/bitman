<?php

declare(strict_types=1);

use App\Modules\Ingestion\Domain\PolicyLimits;
use App\Modules\Ingestion\Domain\RepositoryPolicy;
use App\Modules\Ingestion\Domain\RepositoryRule;
use App\Modules\Ingestion\Domain\RepositorySnapshot;
use App\Modules\Ingestion\Domain\Rules\NoSubmodules;
use App\Modules\Ingestion\Domain\Rules\NoSymlinks;
use App\Modules\Ingestion\Domain\Rules\RequiredFilesAreRegular;
use App\Modules\Ingestion\Domain\Rules\RequiredFilesAreText;
use App\Modules\Ingestion\Domain\Rules\RequiredFilesPresent;
use App\Modules\Ingestion\Domain\Rules\RequiredFilesWithinSize;
use App\Modules\Ingestion\Domain\Rules\SafePaths;
use App\Modules\Ingestion\Domain\Rules\TreeWithinEntryLimit;
use App\Modules\Ingestion\Domain\Violation;
use Tests\Support\Snapshots;

/*
| Política de repositório (docs/dominio.md#política-de-repositório-mvp): uma regra por bloco,
| cada uma testada isoladamente, e a composição no fim.
*/

/** @return list<string> */
function codes(RepositoryRule $rule, RepositorySnapshot $snapshot): array
{
    return array_map(fn (Violation $v): string => $v->code, $rule->check($snapshot));
}

describe('RequiredFilesPresent', function (): void {
    it('não reclama do jogo completo', function (): void {
        expect(codes(new RequiredFilesPresent(Snapshots::limits()), Snapshots::valid()))->toBe([]);
    });

    it('aponta cada obrigatório ausente', function (): void {
        $violations = (new RequiredFilesPresent(Snapshots::limits()))->check(Snapshots::of(['index.html' => 'x']));

        expect(array_map(fn (Violation $v) => $v->path, $violations))->toBe(['style.css', 'game.js'])
            ->and($violations[0]->message)->toBe('O arquivo obrigatório style.css não existe na raiz do repositório.');
    });

    it('não aceita o obrigatório fora da raiz', function (): void {
        $files = Snapshots::gameFiles();
        $snapshot = Snapshots::of(['index.html' => $files['index.html'], 'style.css' => $files['style.css'], 'src/game.js' => $files['game.js']]);

        expect(codes(new RequiredFilesPresent(Snapshots::limits()), $snapshot))->toBe(['required_file_missing']);
    });
});

describe('RequiredFilesAreRegular', function (): void {
    it('recusa obrigatório que não é arquivo comum', function (string $mode, string $type, string $kind): void {
        $snapshot = Snapshots::replacing('game.js', Snapshots::entry('game.js', $mode, null, $type));
        $violations = (new RequiredFilesAreRegular(Snapshots::limits()))->check($snapshot);

        expect($violations)->toHaveCount(1)
            ->and($violations[0]->message)->toBe("game.js precisa ser um arquivo comum (modo 100644), mas é {$kind}.");
    })->with([
        'symlink' => ['120000', 'blob', 'um symlink'],
        'executável' => ['100755', 'blob', 'um executável'],
        'submodule' => ['160000', 'commit', 'um submodule'],
        'diretório' => ['040000', 'tree', 'um diretório'],
    ]);

    it('aceita os obrigatórios comuns', function (): void {
        expect(codes(new RequiredFilesAreRegular(Snapshots::limits()), Snapshots::valid()))->toBe([]);
    });
});

describe('RequiredFilesWithinSize', function (): void {
    it('aceita até o limite, inclusive', function (): void {
        $files = [...Snapshots::gameFiles(), 'game.js' => str_repeat('a', 1024)];

        expect(codes(new RequiredFilesWithinSize(Snapshots::limits(1024)), Snapshots::of($files)))->toBe([]);
    });

    it('recusa acima do limite e o blob que ficou no servidor (tamanho nulo)', function (?int $size): void {
        $snapshot = Snapshots::replacing('game.js', Snapshots::entry('game.js', '100644', $size));
        $violations = (new RequiredFilesWithinSize(Snapshots::limits(1024)))->check($snapshot);

        expect($violations)->toHaveCount(1)
            ->and($violations[0]->code)->toBe('file_too_large')
            ->and($violations[0]->message)->toBe('game.js passa do limite de 1 KiB.');
    })->with([1025, null]);
});

describe('RequiredFilesAreText', function (): void {
    it('aceita UTF-8 com acentos e emojis', function (): void {
        $files = [...Snapshots::gameFiles(), 'game.js' => "const título = '🎮';\n"];

        expect(codes(new RequiredFilesAreText(Snapshots::limits()), Snapshots::of($files)))->toBe([]);
    });

    it('recusa binário e UTF-8 inválido', function (string $content): void {
        $files = [...Snapshots::gameFiles(), 'index.html' => $content];

        expect(codes(new RequiredFilesAreText(Snapshots::limits()), Snapshots::of($files)))->toBe(['not_text']);
    })->with(['nulo' => "<html>\0</html>", 'inválido' => "<html>\xFF\xFE</html>", 'latin1' => "a\xE7\xE3o"]);

    it('bloqueia obrigatório cujo conteúdo não foi entregue', function (): void {
        $snapshot = Snapshots::replacing('game.js', Snapshots::entry('game.js', '100644', 10));

        expect(codes(new RequiredFilesAreText(Snapshots::limits()), $snapshot))->toBe(['content_missing']);
    });
});

describe('NoSubmodules', function (): void {
    it('recusa entradas 160000 e .gitmodules', function (): void {
        $snapshot = Snapshots::of(Snapshots::gameFiles(), [
            Snapshots::entry('vendor/lib', '160000', null, 'commit'),
            Snapshots::entry('.gitmodules'),
        ]);

        expect(codes(new NoSubmodules, $snapshot))->toBe(['submodule', 'submodule']);
    });

    it('aceita repositório sem submodule', function (): void {
        expect(codes(new NoSubmodules, Snapshots::valid()))->toBe([]);
    });
});

describe('NoSymlinks', function (): void {
    it('recusa symlink em qualquer lugar da árvore', function (): void {
        $snapshot = Snapshots::of(Snapshots::gameFiles(), [Snapshots::entry('docs/etc', '120000')]);
        $violations = (new NoSymlinks)->check($snapshot);

        expect($violations)->toHaveCount(1)
            ->and($violations[0]->message)->toBe('O repositório tem um symlink em docs/etc.');
    });
});

describe('TreeWithinEntryLimit', function (): void {
    it('aceita até o limite e recusa acima dele', function (): void {
        $rule = new TreeWithinEntryLimit(Snapshots::limits(maxTreeEntries: 4));
        $four = Snapshots::of(Snapshots::gameFiles(), [Snapshots::entry('README.md')]);
        $five = Snapshots::of(Snapshots::gameFiles(), [Snapshots::entry('README.md'), Snapshots::entry('LICENSE')]);

        expect(codes($rule, $four))->toBe([])
            ->and($rule->check($five)[0]->message)->toBe('O repositório tem 5 arquivos; o limite é 4.');
    });
});

describe('SafePaths', function (): void {
    it('aceita paths comuns', function (string $path): void {
        expect(SafePaths::isSafe($path))->toBeTrue();
    })->with(['game.js', 'assets/img/logo.png', 'acentuação.txt', '-rf', '.github/workflows/ci.yml', 'a.git']);

    it('recusa paths hostis (G6)', function (string $path): void {
        expect(SafePaths::isSafe($path))->toBeFalse();
    })->with([
        'traversal' => '../etc/passwd',
        'traversal no meio' => 'a/../b',
        'ponto' => 'a/./b',
        'absoluto' => '/etc/passwd',
        'barra invertida' => 'a\\b.js',
        'segmento vazio' => 'a//b',
        'barra final' => 'a/',
        'quebra de linha' => "a\nb",
        'nulo' => "a\0b",
        'DEL' => "a\x7Fb",
        'UTF-8 inválido' => "a\xFF",
        '.git' => '.git/config',
        '.GIT' => 'x/.GIT/hooks',
    ]);

    it('a violação mostra o nome escapado', function (): void {
        $snapshot = Snapshots::of(Snapshots::gameFiles(), [Snapshots::entry("quebra\nde-linha.txt")]);

        expect((new SafePaths)->check($snapshot)[0]->message)->toBe('Nome de arquivo não permitido: quebra\x0Ade-linha.txt.');
    });
});

describe('RepositoryPolicy', function (): void {
    it('jogo válido: sem violações, extras listados como ignorados', function (): void {
        $snapshot = Snapshots::of(Snapshots::gameFiles(), [Snapshots::entry('README.md', size: 7), Snapshots::entry('assets/logo.png', size: null)]);
        $report = RepositoryPolicy::standard(Snapshots::limits())->evaluate($snapshot);

        expect($report->isBlocked())->toBeFalse()
            ->and($report->toArray())->toBe([
                'blocked' => false,
                'violations' => [],
                'ignored' => [
                    ['path' => 'README.md', 'mode' => '100644', 'size' => 7],
                    ['path' => 'assets/logo.png', 'mode' => '100644', 'size' => null],
                ],
                'ignored_total' => 2,
            ]);
    });

    it('junta as violações de todas as regras', function (): void {
        $snapshot = Snapshots::of(['index.html' => "\0"], [Snapshots::entry('game.js', '120000'), Snapshots::entry('a\\b')]);
        $report = RepositoryPolicy::standard(Snapshots::limits())->evaluate($snapshot);

        expect($report->isBlocked())->toBeTrue()
            ->and(array_map(fn (Violation $v) => $v->code, $report->violations))
            ->toBe(['required_file_missing', 'required_file_not_regular', 'not_text', 'symlink', 'unsafe_path']);
    });

    it('limita a lista de ignorados, mas conta todos', function (): void {
        $extra = array_map(fn (int $i) => Snapshots::entry("f{$i}.txt"), range(1, 30));
        $report = RepositoryPolicy::standard(Snapshots::limits(maxTreeEntries: 20))->evaluate(Snapshots::of(Snapshots::gameFiles(), $extra));

        expect($report->ignored)->toHaveCount(20)
            ->and($report->ignoredTotal)->toBe(30)
            ->and($report->hasViolation('too_many_entries'))->toBeTrue();
    });

    it('recusa limites inválidos', function (): void {
        expect(fn () => new PolicyLimits([], 1, 1))->toThrow(InvalidArgumentException::class)
            ->and(fn () => new PolicyLimits(['../x'], 1, 1))->toThrow(InvalidArgumentException::class)
            ->and(fn () => new PolicyLimits(['a'], 0, 1))->toThrow(InvalidArgumentException::class);
    });
});
