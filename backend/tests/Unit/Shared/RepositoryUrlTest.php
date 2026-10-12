<?php

declare(strict_types=1);

use App\Shared\Domain\Git\InvalidRepositoryUrl;
use App\Shared\Domain\Git\RepositoryUrl;

it('aceita repositórios públicos dos hosts permitidos e normaliza a URL', function (string $input, string $canonical): void {
    expect(RepositoryUrl::fromString($input)->value)->toBe($canonical);
})->with([
    'github' => ['https://github.com/ada/snake', 'https://github.com/ada/snake'],
    'sufixo .git' => ['https://github.com/ada/snake.git', 'https://github.com/ada/snake'],
    'barra final' => ['https://github.com/ada/snake/', 'https://github.com/ada/snake'],
    'host e esquema em maiúsculas' => ['HTTPS://GitHub.com/Ada/Snake', 'https://github.com/Ada/Snake'],
    'codeberg' => ['https://codeberg.org/ada/snake', 'https://codeberg.org/ada/snake'],
    'gitlab com subgrupo' => ['https://gitlab.com/turma/jogos/snake', 'https://gitlab.com/turma/jogos/snake'],
    'nomes com ponto, hífen e sublinhado' => ['https://github.com/ada-l/my_game.v2', 'https://github.com/ada-l/my_game.v2'],
    'espaços nas pontas' => ['  https://github.com/ada/snake  ', 'https://github.com/ada/snake'],
]);

it('rejeita protocolos que não sejam https (G2)', function (string $url): void {
    expect(fn () => RepositoryUrl::fromString($url))->toThrow(InvalidRepositoryUrl::class);
})->with([
    'http://github.com/ada/snake',
    'git://github.com/ada/snake',
    'ssh://git@github.com/ada/snake',
    'git@github.com:ada/snake.git',
    'file:///etc/passwd',
    'ext::sh -c touch% /tmp/pwned',
    '//github.com/ada/snake',
    'github.com/ada/snake',
]);

it('rejeita hosts fora da allowlist, IPs, portas e credenciais (G3)', function (string $url): void {
    expect(fn () => RepositoryUrl::fromString($url))->toThrow(InvalidRepositoryUrl::class);
})->with([
    'outro host' => 'https://bitbucket.org/ada/snake',
    'subdomínio' => 'https://gist.github.com/ada/snake',
    'sufixo enganoso' => 'https://github.com.evil.example/ada/snake',
    'prefixo enganoso' => 'https://evilgithub.com/ada/snake',
    'IP literal' => 'https://140.82.112.3/ada/snake',
    'IP da LAN' => 'https://192.168.0.10/ada/snake',
    'localhost' => 'https://localhost/ada/snake',
    'serviço interno' => 'https://postgres/ada/snake',
    'porta' => 'https://github.com:443/ada/snake',
    'credenciais' => 'https://user:token@github.com/ada/snake',
    'usuário' => 'https://token@github.com/ada/snake',
    'host com ponto final' => 'https://github.com./ada/snake',
]);

it('rejeita caminhos malformados ou que não apontam para um repositório (G1)', function (string $url): void {
    expect(fn () => RepositoryUrl::fromString($url))->toThrow(InvalidRepositoryUrl::class);
})->with([
    'sem repositório' => 'https://github.com/ada',
    'só o host' => 'https://github.com',
    'página do GitHub' => 'https://github.com/ada/snake/tree/main',
    'subgrupo no GitHub' => 'https://github.com/ada/jogos/snake',
    'traversal' => 'https://github.com/ada/../snake',
    'segmento ponto' => 'https://github.com/./snake',
    'segmento vazio' => 'https://github.com/ada//snake',
    'query' => 'https://github.com/ada/snake?x=1',
    'fragmento' => 'https://github.com/ada/snake#readme',
    'percent-encoding' => 'https://github.com/ada/sn%61ke',
    'espaço' => 'https://github.com/ada/my snake',
    'opção do git' => 'https://github.com/-u/snake',
    'barra invertida' => 'https://github.com/ada\\snake',
    'caractere de controle' => "https://github.com/ada/snake\n",
    'unicode' => 'https://github.com/adá/snake',
    'só .git' => 'https://github.com/ada/.git',
    'segmento reservado do GitLab' => 'https://gitlab.com/ada/-/snake',
    'vazia' => '',
]);

it('rejeita URLs longas demais', function (): void {
    $url = 'https://github.com/ada/'.str_repeat('a', 250);

    expect(fn () => RepositoryUrl::fromString($url))->toThrow(InvalidRepositoryUrl::class);
});

it('a mensagem de erro é para o usuário', function (): void {
    expect(fn () => RepositoryUrl::fromString('https://bitbucket.org/ada/snake'))
        ->toThrow(InvalidRepositoryUrl::class, 'Use o endereço HTTPS de um repositório público no GitHub, GitLab ou Codeberg.');
});

it('compara pelo valor normalizado', function (): void {
    expect(RepositoryUrl::fromString('https://github.com/ada/snake.git')->equals(RepositoryUrl::fromString('https://GITHUB.com/ada/snake')))
        ->toBeTrue();
});
