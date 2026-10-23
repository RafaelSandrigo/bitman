<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Controllers\Admin;

use App\Modules\Catalog\Application\Actions\ChangeScoreCap;
use App\Modules\Catalog\Http\Requests\ChangeScoreCapRequest;
use App\Modules\Catalog\Http\Resources\GameResource;
use App\Modules\Identity\Domain\User;
use Illuminate\Container\Attributes\CurrentUser;

/** M15: teto de score do jogo. */
final class GameScoreCapController
{
    public function update(string $game, ChangeScoreCapRequest $request, #[CurrentUser] User $actor, ChangeScoreCap $changeScoreCap): GameResource
    {
        return new GameResource($changeScoreCap($actor, $game, $request->cap()));
    }
}
