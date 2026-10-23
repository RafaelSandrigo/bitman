<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain;

use App\Modules\Identity\Domain\Exceptions\InvalidGithubProfileUrl;

/**
 * Perfil do GitHub informado no pedido para ser developer (M18): só `https://github.com/{usuário}`,
 * com o usuário nas regras do GitHub. Texto do usuário: nunca é seguido pelo servidor.
 */
final readonly class GithubProfileUrl
{
    /** Até 39 caracteres, letras, números e hífens isolados, sem começar nem terminar com hífen. */
    private const PATTERN = '~^https://github\.com/(?<user>[A-Za-z0-9](?:[A-Za-z0-9]|-(?=[A-Za-z0-9])){0,38})/?$~iD';

    private function __construct(public string $value) {}

    public static function fromString(string $input): self
    {
        if (preg_match(self::PATTERN, trim($input), $match) !== 1) {
            throw new InvalidGithubProfileUrl;
        }

        return new self('https://github.com/'.$match['user']);
    }
}
