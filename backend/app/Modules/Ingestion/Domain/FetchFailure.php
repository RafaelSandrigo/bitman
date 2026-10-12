<?php

declare(strict_types=1);

namespace App\Modules\Ingestion\Domain;

/**
 * Por que um fetch falhou. Os valores são os códigos da git-sandbox; o que a sandbox não
 * conhece (ou um código desconhecido) vira `FetchError`. A mensagem nunca inclui texto do
 * servidor remoto.
 */
enum FetchFailure: string
{
    case RefNotFound = 'ref_not_found';
    case RepositoryUnavailable = 'repository_unavailable';
    case Redirected = 'redirected';
    case Timeout = 'timeout';
    case TooLarge = 'too_large';
    case TooManyEntries = 'too_many_entries';
    case InvalidObjects = 'invalid_objects';
    case ProtocolNotAllowed = 'protocol_not_allowed';
    case RequestRefused = 'invalid_request';
    case SandboxUnavailable = 'sandbox_unavailable';
    case InvalidResponse = 'invalid_response';
    case FetchError = 'fetch_error';
    case Internal = 'internal';

    public static function fromSandbox(string $code): self
    {
        $failure = self::tryFrom($code);

        return $failure === null || in_array($failure, [self::SandboxUnavailable, self::InvalidResponse], true)
            ? self::FetchError
            : $failure;
    }

    public function message(): string
    {
        return match ($this) {
            self::RefNotFound => 'A branch ou tag não existe no repositório.',
            self::RepositoryUnavailable => 'O repositório não existe ou não é público.',
            self::Redirected => 'O repositório respondeu com um redirecionamento (foi renomeado ou movido?). Atualize a URL.',
            self::Timeout => 'A busca passou do tempo limite.',
            self::TooLarge => 'O repositório é grande demais para a busca.',
            self::TooManyEntries => 'O repositório tem arquivos demais.',
            self::InvalidObjects => 'O repositório tem objetos Git malformados ou nomes proibidos (recusados na verificação do Git).',
            self::ProtocolNotAllowed => 'Protocolo não permitido.',
            self::RequestRefused => 'A sandbox recusou o endereço ou a ref.',
            self::SandboxUnavailable => 'A sandbox de Git está indisponível. Tente de novo mais tarde.',
            self::InvalidResponse => 'A resposta da sandbox foi recusada pela verificação do Bitman.',
            self::FetchError => 'Não foi possível buscar o repositório.',
            self::Internal => 'Erro interno durante a importação.',
        };
    }
}
