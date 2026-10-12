<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Controllers\Admin;

use App\Modules\Catalog\Application\Actions\BlockGame;
use App\Modules\Catalog\Application\Actions\ShowGame;
use App\Modules\Catalog\Application\Actions\UnblockGame;
use App\Modules\Catalog\Http\Requests\BlockGameRequest;
use App\Modules\Catalog\Http\Resources\GameResource;
use App\Modules\Identity\Domain\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

/** Detalhe do jogo, bloqueio e desbloqueio (J6). O histórico de versões vem de /admin/versions?game_id=. */
final class GameController
{
    public function show(string $game, #[CurrentUser] User $actor, ShowGame $showGame): JsonResponse
    {
        $details = $showGame($actor, $game);

        return (new GameResource($details->game))->additional(['meta' => ['is_served' => $details->isServed]])->response();
    }

    public function block(string $game, BlockGameRequest $request, #[CurrentUser] User $actor, BlockGame $blockGame): GameResource
    {
        return new GameResource($blockGame($actor, $game, $request->reason()));
    }

    public function unblock(string $game, #[CurrentUser] User $actor, UnblockGame $unblockGame): GameResource
    {
        return new GameResource($unblockGame($actor, $game));
    }
}
