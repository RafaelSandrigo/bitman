<?php

declare(strict_types=1);

namespace App\Shared\Domain\Git;

/**
 * URL de um repositório Git público num host permitido (G1–G3). É a única forma de uma URL
 * informada pelo usuário chegar à sandbox: só https, host da allowlist, sem credenciais, porta,
 * query ou fragmento, e cada segmento do caminho com caracteres seguros. Nada é resolvido na
 * rede aqui (I2).
 *
 * A allowlist precisa ser igual à do egress-proxy (docker/egress-proxy/allowed-hosts).
 */
final readonly class RepositoryUrl
{
    /** Host => número máximo de segmentos do caminho (o GitLab tem subgrupos). */
    private const HOSTS = ['github.com' => 2, 'gitlab.com' => 5, 'codeberg.org' => 2];

    private const MAX_LENGTH = 255;

    private const SEGMENT = '/^[A-Za-z0-9_][A-Za-z0-9._-]{0,99}$/D';

    private function __construct(public string $value) {}

    public static function fromString(string $input): self
    {
        $url = trim($input, ' ');

        if (strlen($url) > self::MAX_LENGTH || preg_match('~^https://(?<host>[A-Za-z0-9.-]+)(?<path>/.*)$~iD', $url, $match) !== 1) {
            throw new InvalidRepositoryUrl;
        }

        $host = strtolower($match['host']);
        if (! array_key_exists($host, self::HOSTS)) {
            throw new InvalidRepositoryUrl;
        }

        $segments = explode('/', substr((string) preg_replace('~/$~', '', $match['path']), 1));
        $last = array_key_last($segments);
        if (str_ends_with($segments[$last], '.git')) {
            $segments[$last] = substr($segments[$last], 0, -4);
        }

        if (count($segments) < 2 || count($segments) > self::HOSTS[$host]) {
            throw new InvalidRepositoryUrl;
        }

        foreach ($segments as $segment) {
            if (preg_match(self::SEGMENT, $segment) !== 1) {
                throw new InvalidRepositoryUrl;
            }
        }

        return new self("https://{$host}/".implode('/', $segments));
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
