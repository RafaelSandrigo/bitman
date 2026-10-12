<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Controllers\Admin;

use App\Modules\Catalog\Application\Actions\ChangeGameSlug;
use App\Modules\Catalog\Http\Requests\ChangeGameSlugRequest;
use App\Modules\Catalog\Http\Resources\GameResource;
use App\Modules\Identity\Domain\User;
use Illuminate\Container\Attributes\CurrentUser;

final class GameSlugController
{
    public function update(string $game, ChangeGameSlugRequest $request, #[CurrentUser] User $actor, ChangeGameSlug $changeSlug): GameResource
    {
        return new GameResource($changeSlug($actor, $game, $request->slug()));
    }
}
