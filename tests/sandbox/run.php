<?php

declare(strict_types=1);

/*
| Harness da git-sandbox (docs/seguranca.md G1–G7). Roda dentro da imagem real, sem rede
| (tests/infra/sandbox-fixtures.sh), depois de build-fixtures.sh.
|
| 1. Verificações da própria sandbox (protocolos, validação da requisição, timeout, limpeza).
| 2. Manifesto de cada fixture → stdout (JSON). O script compara com o golden versionado,
|    que o Pest usa para levar cada fixture pelo pipeline do Laravel (FetchRepositoryFixturesTest).
*/

require '/srv/sandbox/src/SandboxError.php';
require '/srv/sandbox/src/GitSandbox.php';

use Bitman\Sandbox\GitSandbox;
use Bitman\Sandbox\SandboxError;

const PATHS = ['index.html', 'style.css', 'game.js'];
const MAX_BLOB = 1024 * 1024;

$failures = 0;
$check = static function (bool $ok, string $label) use (&$failures): void {
    fwrite(STDERR, ($ok ? 'ok   ' : 'FAIL ').$label."\n");
    $failures += $ok ? 0 : 1;
};

/** Código do erro da busca, ou null se ela funcionou. */
$errorOf = static function (callable $fetch): ?string {
    try {
        $fetch();

        return null;
    } catch (SandboxError $error) {
        return $error->reason;
    }
};

$production = new GitSandbox('/tmp', 30);
$harness = new GitSandbox('/tmp', 30, ['file']);
$fixture = static fn (string $name): string => "file:///tmp/fixtures/{$name}";

// G2: a configuração de produção só fala HTTPS, mesmo que a URL escape da validação.
$check($errorOf(fn () => $production->fetchFrom($fixture('valid'), 'main', PATHS, MAX_BLOB)) === 'protocol_not_allowed', 'produção recusa file://');
foreach ([$production, $harness] as $sandbox) {
    $error = $errorOf(fn () => $sandbox->fetchFrom('ext::sh -c touch% /tmp/pwned', 'main', PATHS, MAX_BLOB));
    $check($error !== null && ! file_exists('/tmp/pwned'), 'ext:: nunca executa comando');
}

// G1–G3: a requisição é revalidada pela sandbox.
$valid = ['url' => 'https://github.com/ada/snake', 'ref' => 'main', 'paths' => PATHS, 'max_blob_bytes' => MAX_BLOB];
$invalid = [
    'file://' => ['url' => 'file:///etc'],
    'ext::' => ['url' => 'ext::sh -c id'],
    'ssh' => ['url' => 'ssh://git@github.com/ada/snake'],
    'scp' => ['url' => 'git@github.com:ada/snake.git'],
    'git://' => ['url' => 'git://github.com/ada/snake'],
    'http' => ['url' => 'http://github.com/ada/snake'],
    'credenciais' => ['url' => 'https://user:pass@github.com/ada/snake'],
    'porta' => ['url' => 'https://github.com:443/ada/snake'],
    'IP' => ['url' => 'https://140.82.121.4/ada/snake'],
    'outro host' => ['url' => 'https://example.com/ada/snake'],
    'host parecido' => ['url' => 'https://github.com.evil.io/ada/snake'],
    'query' => ['url' => 'https://github.com/ada/snake?x=1'],
    'traversal na URL' => ['url' => 'https://github.com/ada/../snake'],
    'opção na URL' => ['url' => 'https://github.com/-u/snake'],
    'ref opção' => ['ref' => '--upload-pack=touch /tmp/x'],
    'ref -b' => ['ref' => '-b'],
    'ref com ..' => ['ref' => 'a..b'],
    'ref .lock' => ['ref' => 'main.lock'],
    'ref vazia' => ['ref' => ''],
    'ref com espaço' => ['ref' => 'main x'],
    'path com barra' => ['paths' => ['js/game.js']],
    'path ..' => ['paths' => ['..']],
    'path .git' => ['paths' => ['.git']],
    'sem paths' => ['paths' => []],
    'blob 0' => ['max_blob_bytes' => 0],
    'blob enorme' => ['max_blob_bytes' => 64 * 1024 * 1024],
    'blob texto' => ['max_blob_bytes' => '1024'],
];
foreach ($invalid as $label => $override) {
    $check($errorOf(fn () => GitSandbox::validateRequest([...$valid, ...$override])) === 'invalid_request', "recusa requisição: {$label}");
}
$check($errorOf(fn () => GitSandbox::validateRequest($valid)) === null, 'aceita requisição válida');
$check($errorOf(fn () => GitSandbox::validateRequest([...$valid, 'url' => 'https://gitlab.com/g/sub/proj.git'])) === null, 'aceita subgrupo do GitLab');

// Erros classificados.
$check($errorOf(fn () => $harness->fetchFrom($fixture('valid'), 'nao-existe', PATHS, MAX_BLOB)) === 'ref_not_found', 'ref inexistente');
$check($errorOf(fn () => (new GitSandbox('/tmp', 0, ['file']))->fetchFrom($fixture('valid'), 'main', PATHS, MAX_BLOB)) === 'timeout', 'timeout total');

// Manifestos das fixtures (golden).
$fixtures = [
    'valid' => 'main',
    'valid-tag' => 'v1.0',
    'symlink' => 'main',
    'symlink-extra' => 'main',
    'submodule' => 'main',
    'executable' => 'main',
    'huge' => 'main',
    'binary' => 'main',
    'hostile-names' => 'main',
    'dotdot-tree' => 'main',
    'dotgit-tree' => 'main',
    'too-many' => 'main',
    'way-too-many' => 'main',
    'missing-files' => 'main',
    'empty' => 'main',
];

$manifests = [];
foreach ($fixtures as $name => $ref) {
    $repository = $name === 'valid-tag' ? 'valid' : $name;
    try {
        $manifests[$name] = ['status' => 200, 'body' => $harness->fetchFrom($fixture($repository), $ref, PATHS, MAX_BLOB)];
    } catch (SandboxError $error) {
        $manifests[$name] = ['status' => 422, 'body' => ['error' => $error->reason]];
    }
}

$check(! isset($manifests['valid']['body']['error']) && count((array) $manifests['valid']['body']['blobs']) === 3, 'fixture válida devolve os 3 blobs');
$check(! isset($manifests['huge']['body']['blobs']->{'game.js'}), 'blob acima do limite não é devolvido');
$check(($manifests['dotgit-tree']['body']['error'] ?? null) === 'invalid_objects', 'árvore com .git é recusada pelo fsck');
$check(($manifests['way-too-many']['body']['error'] ?? null) === 'too_many_entries', 'árvore acima do teto falha');
$check(glob('/tmp/ws-*') === [], 'nenhum workspace sobra em /tmp');

echo json_encode($manifests, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n";

fwrite(STDERR, $failures === 0 ? "sandbox: todas as verificações passaram\n" : "sandbox: {$failures} falha(s)\n");
exit($failures === 0 ? 0 : 1);
