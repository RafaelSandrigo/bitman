<?php

declare(strict_types=1);

namespace Bitman\Sandbox;

/**
 * Busca um repositório Git e devolve o manifesto (ADR 0003, docs/seguranca.md G1–G8).
 *
 * - Revalida URL, ref e paths (não confia em quem chama, nem no Laravel).
 * - git sempre por array de argumentos (sem shell), só HTTPS, sem redirects, sem hooks,
 *   sem config do sistema/usuário, com fsck dos objetos recebidos.
 * - Repositório bare + clone raso com filtro de blobs: não existe checkout, então symlinks e
 *   submodules são só entradas da árvore, lidas como dados.
 * - Workspace próprio por requisição, apagado ao final; timeout total para todos os comandos.
 * - Erros saem como códigos: a saída do git contém texto do servidor remoto, que é hostil.
 */
final class GitSandbox
{
    /** Host => máximo de segmentos do caminho (o mesmo de RepositoryUrl no Laravel). */
    private const HOSTS = ['github.com' => 2, 'gitlab.com' => 5, 'codeberg.org' => 2];

    private const SEGMENT = '/^[A-Za-z0-9_][A-Za-z0-9._-]{0,99}$/D';

    private const MAX_BLOB_BYTES = 4 * 1024 * 1024;

    private const MAX_PATHS = 10;

    /** Teto da árvore devolvida: acima disso a busca falha (a política do Laravel é bem menor). */
    public const MAX_ENTRIES = 10_000;

    private const GIT_CONFIG = [
        'protocol.allow=never',
        'http.followRedirects=false',
        'http.lowSpeedLimit=1024',
        'http.lowSpeedTime=20',
        'core.hooksPath=/dev/null',
        'core.symlinks=false',
        'core.askPass=',
        'credential.helper=',
        'transfer.fsckObjects=true',
        'init.defaultBranch=main',
        'advice.detachedHead=false',
    ];

    private float $deadline = 0.0;

    /**
     * @param  list<string>  $protocols  só `https` em produção; o harness de fixtures (tests/sandbox) usa `file`
     */
    public function __construct(
        private readonly string $workRoot = '/tmp',
        private readonly int $timeoutSeconds = 60,
        private readonly array $protocols = ['https'],
    ) {}

    /**
     * @param  array<mixed>  $request  {url, ref, paths: list<string>, max_blob_bytes: int}
     * @return array<string, mixed> manifesto
     *
     * @throws SandboxError
     */
    public function fetch(array $request): array
    {
        [$url, $ref, $paths, $maxBlobBytes] = self::validateRequest($request);

        return $this->fetchFrom($url, $ref, $paths, $maxBlobBytes);
    }

    /**
     * Busca sem a validação da requisição: só para quem já validou (fetch) e para o harness de
     * fixtures. Os protocolos continuam limitados por `$protocols` (G2).
     *
     * @param  list<string>  $paths
     * @return array<string, mixed>
     *
     * @throws SandboxError
     */
    public function fetchFrom(string $url, string $ref, array $paths, int $maxBlobBytes): array
    {
        $this->deadline = microtime(true) + $this->timeoutSeconds;
        $workspace = $this->createWorkspace();

        try {
            $repository = $workspace.'/repo.git';
            $this->cloneBare($url, $ref, $repository, $maxBlobBytes, $workspace);

            return $this->manifest($repository, $paths, $maxBlobBytes);
        } finally {
            self::removeTree($workspace);
        }
    }

    /**
     * @param  array<mixed>  $request
     * @return array{string, string, list<string>, int}
     *
     * @throws SandboxError
     */
    public static function validateRequest(array $request): array
    {
        $url = $request['url'] ?? null;
        $ref = $request['ref'] ?? null;
        $paths = $request['paths'] ?? null;
        $maxBlobBytes = $request['max_blob_bytes'] ?? null;

        if (! is_string($url) || ! self::isAllowedUrl($url)) {
            throw new SandboxError('invalid_request', 'url');
        }
        if (! is_string($ref) || ! self::isSafeRef($ref)) {
            throw new SandboxError('invalid_request', 'ref');
        }
        if (! is_array($paths) || ! array_is_list($paths) || $paths === [] || count($paths) > self::MAX_PATHS) {
            throw new SandboxError('invalid_request', 'paths');
        }
        foreach ($paths as $path) {
            if (! is_string($path) || preg_match(self::SEGMENT, $path) !== 1 || str_contains($path, '..')) {
                throw new SandboxError('invalid_request', 'paths');
            }
        }
        if (! is_int($maxBlobBytes) || $maxBlobBytes < 1 || $maxBlobBytes > self::MAX_BLOB_BYTES) {
            throw new SandboxError('invalid_request', 'max_blob_bytes');
        }

        return [$url, $ref, array_values(array_unique($paths)), $maxBlobBytes];
    }

    /** Só `https://{host da allowlist}/{segmentos}`: sem credenciais, porta, query, fragmento ou IP (G2, G3). */
    public static function isAllowedUrl(string $url): bool
    {
        if (strlen($url) > 255 || preg_match('~^https://([a-z0-9.-]+)/(.+)$~D', $url, $match) !== 1) {
            return false;
        }

        $maxSegments = self::HOSTS[$match[1]] ?? null;
        if ($maxSegments === null) {
            return false;
        }

        $path = str_ends_with($match[2], '.git') ? substr($match[2], 0, -4) : $match[2];
        $segments = explode('/', $path);
        if (count($segments) < 2 || count($segments) > $maxSegments) {
            return false;
        }

        foreach ($segments as $segment) {
            if (preg_match(self::SEGMENT, $segment) !== 1 || str_contains($segment, '..')) {
                return false;
            }
        }

        return true;
    }

    /** Mesma regra de GitRef no Laravel (G1): nunca vira opção do git. */
    public static function isSafeRef(string $ref): bool
    {
        return preg_match('~^[A-Za-z0-9._/-]{1,100}$~D', $ref) === 1
            && ! str_starts_with($ref, '-')
            && ! str_starts_with($ref, '/')
            && ! str_starts_with($ref, '.')
            && ! str_ends_with($ref, '/')
            && ! str_ends_with($ref, '.')
            && ! str_ends_with($ref, '.lock')
            && ! str_contains($ref, '..')
            && ! str_contains($ref, '//')
            && ! str_contains($ref, '/.');
    }

    /**
     * Clone bare e raso, sem tags, com os blobs acima do limite deixados no servidor (G5, G7).
     *
     * @throws SandboxError
     */
    private function cloneBare(string $url, string $ref, string $repository, int $maxBlobBytes, string $home): void
    {
        $result = $this->git([
            'clone', '--bare', '--quiet', '--depth=1', '--no-tags', '--single-branch',
            '--filter=blob:limit='.$maxBlobBytes, '--branch='.$ref, '--', $url, $repository,
        ], $home, null, 64 * 1024);

        if ($result['code'] !== 0) {
            throw new SandboxError(self::classifyCloneError($result['stderr'], $result['timedOut']), self::printable($result['stderr']));
        }
    }

    /**
     * Árvore completa (path, modo, tipo, objeto, tamanho) e o conteúdo apenas dos paths pedidos
     * que forem blobs regulares dentro do limite. Paths vão em base64: são bytes arbitrários.
     *
     * @param  list<string>  $paths
     * @return array<string, mixed>
     *
     * @throws SandboxError
     */
    private function manifest(string $repository, array $paths, int $maxBlobBytes): array
    {
        $commit = trim($this->gitOk(['rev-parse', '--verify', '--end-of-options', 'HEAD^{commit}'], $repository, null, 200));
        if (preg_match('/^(?:[0-9a-f]{40}|[0-9a-f]{64})$/D', $commit) !== 1) {
            throw new SandboxError('fetch_error', 'commit inválido');
        }

        $tree = $this->gitOk(['ls-tree', '-r', '-z', '--full-tree', '--end-of-options', $commit], $repository, null, 8 * 1024 * 1024);

        $entries = [];
        foreach (explode("\0", rtrim($tree, "\0")) as $line) {
            if ($line === '') {
                continue;
            }
            if (count($entries) >= self::MAX_ENTRIES) {
                throw new SandboxError('too_many_entries', 'árvore grande demais');
            }
            if (preg_match('/^([0-7]{6}) (blob|tree|commit) ([0-9a-f]{40,64})\t(.+)$/sD', $line, $m) !== 1) {
                throw new SandboxError('fetch_error', 'linha de árvore inesperada');
            }
            $entries[] = ['path' => $m[4], 'mode' => $m[1], 'type' => $m[2], 'object' => $m[3], 'size' => null];
        }

        $sizes = $this->blobSizes($repository, $entries);
        $blobs = [];
        foreach ($entries as $i => $entry) {
            $size = $entry['type'] === 'blob' ? ($sizes[$entry['object']] ?? null) : null;
            $entries[$i]['size'] = $size;

            if (in_array($entry['path'], $paths, true) && $entry['mode'] === '100644' && $size !== null && $size <= $maxBlobBytes) {
                $content = $this->gitOk(['cat-file', 'blob', $entry['object']], $repository, null, $maxBlobBytes);
                $blobs[$entry['path']] = base64_encode($content);
            }
        }

        return [
            'commit' => $commit,
            'entries' => array_map(static fn (array $e): array => [...$e, 'path' => base64_encode($e['path'])], $entries),
            'blobs' => (object) $blobs,
        ];
    }

    /**
     * Tamanho dos blobs presentes. Os que ficaram fora pelo filtro aparecem como "missing" e
     * não são buscados (GIT_NO_LAZY_FETCH): o tamanho fica nulo, ou seja, acima do limite.
     *
     * @param  list<array{path: string, mode: string, type: string, object: string, size: null}>  $entries
     * @return array<string, int>
     */
    private function blobSizes(string $repository, array $entries): array
    {
        $objects = array_values(array_unique(array_map(
            static fn (array $e): string => $e['object'],
            array_filter($entries, static fn (array $e): bool => $e['type'] === 'blob'),
        )));
        if ($objects === []) {
            return [];
        }

        $output = $this->gitOk(
            ['cat-file', '--batch-check=%(objectname) %(objecttype) %(objectsize)'],
            $repository,
            implode("\n", $objects)."\n",
            count($objects) * 120,
        );

        $sizes = [];
        foreach (explode("\n", trim($output)) as $line) {
            if (preg_match('/^([0-9a-f]{40,64}) blob (\d+)$/D', $line, $m) === 1) {
                $sizes[$m[1]] = (int) $m[2];
            }
        }

        return $sizes;
    }

    /** @throws SandboxError */
    private function gitOk(array $args, string $cwd, ?string $stdin, int $maxOutput): string
    {
        $result = $this->git($args, $cwd, $stdin, $maxOutput);
        if ($result['timedOut']) {
            throw new SandboxError('timeout', 'tempo esgotado');
        }
        if ($result['code'] !== 0) {
            throw new SandboxError($result['overflow'] ? 'too_large' : 'fetch_error', self::printable($result['stderr']));
        }

        return $result['stdout'];
    }

    /**
     * Executa o git sem shell, com ambiente mínimo, teto de saída e o prazo total da requisição.
     *
     * @param  list<string>  $args
     * @return array{code: int, stdout: string, stderr: string, timedOut: bool, overflow: bool}
     */
    private function git(array $args, string $cwd, ?string $stdin, int $maxOutput): array
    {
        $command = ['git'];
        foreach (self::GIT_CONFIG as $config) {
            array_push($command, '-c', $config);
        }
        foreach ($this->protocols as $protocol) {
            array_push($command, '-c', "protocol.{$protocol}.allow=always");
        }
        array_push($command, ...$args);

        $environment = [
            'PATH' => '/usr/local/bin:/usr/bin:/bin',
            'HOME' => $cwd,
            'LC_ALL' => 'C',
            'GIT_CONFIG_NOSYSTEM' => '1',
            'GIT_CONFIG_GLOBAL' => '/dev/null',
            'GIT_TERMINAL_PROMPT' => '0',
            'GIT_ALLOW_PROTOCOL' => implode(':', $this->protocols),
            'GIT_PROTOCOL_FROM_USER' => '0',
            'GIT_NO_LAZY_FETCH' => '1',
            'GIT_NO_REPLACE_OBJECTS' => '1',
        ];
        foreach (['HTTPS_PROXY', 'https_proxy'] as $proxy) {
            $value = getenv($proxy);
            if (is_string($value) && $value !== '') {
                $environment[$proxy] = $value;
            }
        }

        $process = proc_open($command, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $cwd, $environment);
        if (! is_resource($process)) {
            throw new SandboxError('internal', 'proc_open falhou');
        }

        fwrite($pipes[0], $stdin ?? '');
        fclose($pipes[0]);
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        $stdout = '';
        $stderr = '';
        $timedOut = false;
        $overflow = false;
        $open = [1 => $pipes[1], 2 => $pipes[2]];

        while ($open !== []) {
            $remaining = $this->deadline - microtime(true);
            if ($remaining <= 0) {
                $timedOut = true;
                break;
            }

            $read = array_values($open);
            $write = $except = null;
            if (stream_select($read, $write, $except, 0, (int) min($remaining * 1_000_000, 200_000)) === false) {
                break;
            }

            foreach ($open as $fd => $pipe) {
                $chunk = fread($pipe, 65536);
                if ($chunk === false || ($chunk === '' && feof($pipe))) {
                    fclose($pipe);
                    unset($open[$fd]);

                    continue;
                }
                if ($fd === 1) {
                    $stdout .= $chunk;
                    if (strlen($stdout) > $maxOutput) {
                        $overflow = true;
                        break 2;
                    }
                } elseif (strlen($stderr) < 16 * 1024) {
                    $stderr .= $chunk;
                }
            }
        }

        if ($timedOut || $overflow) {
            proc_terminate($process, 9);
        }
        foreach ($open as $pipe) {
            fclose($pipe);
        }
        $code = proc_close($process);

        return [
            'code' => ($timedOut || $overflow) ? -1 : $code,
            'stdout' => $stdout,
            'stderr' => $stderr,
            'timedOut' => $timedOut,
            'overflow' => $overflow,
        ];
    }

    public static function classifyCloneError(string $stderr, bool $timedOut): string
    {
        return match (true) {
            $timedOut => 'timeout',
            str_contains($stderr, 'not found in upstream') => 'ref_not_found',
            preg_match('/returned error: 30\d|redirect/i', $stderr) === 1 => 'redirected',
            str_contains($stderr, 'could not read Username'),
            str_contains($stderr, 'Repository not found'),
            str_contains($stderr, 'returned error: 404'),
            str_contains($stderr, 'returned error: 403'),
            str_contains($stderr, 'returned error: 401') => 'repository_unavailable',
            str_contains($stderr, 'No space left'),
            str_contains($stderr, 'Cannot allocate memory') => 'too_large',
            str_contains($stderr, 'fsck'),
            str_contains($stderr, 'bad object'),
            str_contains($stderr, 'invalid object') => 'invalid_objects',
            str_contains($stderr, 'transport') && str_contains($stderr, 'not allowed') => 'protocol_not_allowed',
            default => 'fetch_error',
        };
    }

    /** Para o log da sandbox: só ASCII imprimível, curto (a saída inclui texto do servidor remoto). */
    public static function printable(string $text): string
    {
        return substr((string) preg_replace('/[^\x20-\x7E\n]/', '?', $text), 0, 2000);
    }

    private function createWorkspace(): string
    {
        $path = rtrim($this->workRoot, '/').'/ws-'.bin2hex(random_bytes(12));
        if (! mkdir($path, 0700)) {
            throw new SandboxError('internal', 'workspace');
        }

        return $path;
    }

    /** Remove o workspace sem seguir symlinks (o git não cria nenhum num bare, mas não custa). */
    public static function removeTree(string $path): void
    {
        if (is_link($path) || is_file($path)) {
            @unlink($path);

            return;
        }
        if (! is_dir($path)) {
            return;
        }

        foreach (scandir($path) ?: [] as $item) {
            if ($item !== '.' && $item !== '..') {
                self::removeTree($path.'/'.$item);
            }
        }
        @rmdir($path);
    }
}
