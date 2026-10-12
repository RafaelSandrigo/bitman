<?php

declare(strict_types=1);

namespace App\Modules\Versioning\Application;

use App\Modules\Versioning\Domain\FileReview;
use App\Modules\Versioning\Domain\GameVersion;

/** Versão com o que o admin precisa para decidir, incluindo aprovações anteriores do mesmo conteúdo. */
final readonly class VersionDetails
{
    /** @param  array<string, FileReview>  $previousApprovals  id do arquivo => última aprovação do mesmo path+hash em outra versão do jogo */
    public function __construct(
        public GameVersion $version,
        public array $previousApprovals,
    ) {}
}
