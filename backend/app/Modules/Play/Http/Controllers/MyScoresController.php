<?php

declare(strict_types=1);

namespace App\Modules\Play\Http\Controllers;

use App\Modules\Identity\Domain\User;
use App\Modules\Play\Application\Actions\ListMyScores;
use App\Modules\Play\Http\Resources\MyScoreResource;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class MyScoresController
{
    public function __invoke(#[CurrentUser] User $player, ListMyScores $listMyScores): AnonymousResourceCollection
    {
        return MyScoreResource::collection($listMyScores($player));
    }
}
