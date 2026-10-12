<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Controllers;

use App\Modules\Catalog\Application\Actions\ListMyGames;
use App\Modules\Catalog\Http\Resources\GameResource;
use App\Modules\Identity\Domain\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class MyGamesController
{
    public function __invoke(#[CurrentUser] User $owner, ListMyGames $listMyGames): AnonymousResourceCollection
    {
        return GameResource::collection($listMyGames($owner));
    }
}
