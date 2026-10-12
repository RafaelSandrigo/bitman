<?php

declare(strict_types=1);

namespace Bitman\Sandbox;

use RuntimeException;

/** Falha com código estável para o Laravel; o detalhe (`getMessage`) só vai para o log da sandbox. */
final class SandboxError extends RuntimeException
{
    public function __construct(public readonly string $reason, string $detail = '')
    {
        parent::__construct($detail);
    }
}
