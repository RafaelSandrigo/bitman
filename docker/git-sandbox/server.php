<?php

declare(strict_types=1);

/*
| git-sandbox (ADR 0003). Servidor embutido do PHP: uma requisição por vez (G7).
|
|   GET  /health  → {"status":"ok"}
|   POST /fetch   → manifesto (Authorization: Bearer SANDBOX_TOKEN; corpo JSON até 8 KiB)
|                   {url, ref, paths, max_blob_bytes} → {commit, entries, blobs}
|
| Erros: {"error": "<código>"}. O detalhe (saída do git) só vai para o log do container.
*/

require __DIR__.'/src/SandboxError.php';
require __DIR__.'/src/GitSandbox.php';

use Bitman\Sandbox\GitSandbox;
use Bitman\Sandbox\SandboxError;

header('Content-Type: application/json');
header('Cache-Control: no-store');

$respond = static function (int $status, array $body): void {
    http_response_code($status);
    echo json_encode($body, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
};

$method = $_SERVER['REQUEST_METHOD'] ?? '';
$uri = $_SERVER['REQUEST_URI'] ?? '';

if ($method === 'GET' && $uri === '/health') {
    $respond(200, ['status' => 'ok']);

    return;
}

if ($method !== 'POST' || $uri !== '/fetch') {
    $respond(404, ['error' => 'not_found']);

    return;
}

// G8: sem token configurado, a sandbox não atende ninguém (fail closed).
$token = (string) getenv('SANDBOX_TOKEN');
$given = (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? '');
if (strlen($token) < 32 || ! hash_equals('Bearer '.$token, $given)) {
    $respond(401, ['error' => 'unauthorized']);

    return;
}

$body = file_get_contents('php://input', length: 8193);
$request = is_string($body) && strlen($body) <= 8192 ? json_decode($body, true, 4) : null;
if (! is_array($request)) {
    $respond(400, ['error' => 'invalid_request']);

    return;
}

$started = microtime(true);
try {
    $manifest = (new GitSandbox('/tmp', (int) (getenv('SANDBOX_TIMEOUT') ?: 60)))->fetch($request);
    error_log(sprintf('fetch ok commit=%s entries=%d %.1fs', $manifest['commit'], count($manifest['entries']), microtime(true) - $started));
    $respond(200, $manifest);
} catch (SandboxError $error) {
    error_log(sprintf('fetch %s %.1fs: %s', $error->reason, microtime(true) - $started, GitSandbox::printable($error->getMessage())));
    $respond(match ($error->reason) {
        'invalid_request' => 400,
        'internal' => 500,
        default => 422,
    }, ['error' => $error->reason]);
} catch (Throwable $error) {
    error_log('fetch internal: '.GitSandbox::printable($error->getMessage()));
    $respond(500, ['error' => 'internal']);
}
