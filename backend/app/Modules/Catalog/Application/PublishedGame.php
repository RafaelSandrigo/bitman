<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application;

use App\Modules\Catalog\Domain\Game;
use Carbon\CarbonImmutable;

/** Jogo jogável (I9) e a versão que está no ar. */
final readonly class PublishedGame
{
    public function __construct(
        public Game $game,
        public int $versionNumber,
        public CarbonImmutable $publishedAt,
        /** {origem de jogos}/{publicId}/index.html: nada aqui vem do repositório. */
        public string $playUrl,
    ) {}
}
