<?php

declare(strict_types=1);

namespace App\Modules\Versioning\Application;

/** M12: o que a reconciliação do `served/` corrigiu, e o que não conseguiu. */
final class ReconciliationReport
{
    /** @var list<string> publicIds que voltaram ao ar a partir do archive */
    public array $restored = [];

    /** @var list<string> publicIds cujo conteúdo servido divergia e foi trocado */
    public array $replaced = [];

    /** @var list<string> publicIds servidos sem versão no ar, retirados */
    public array $removed = [];

    /** @var list<string> versões que deveriam estar no ar e não puderam voltar */
    public array $failed = [];

    public function corrections(): int
    {
        return count($this->restored) + count($this->replaced) + count($this->removed);
    }

    public function hasFailures(): bool
    {
        return $this->failed !== [];
    }
}
