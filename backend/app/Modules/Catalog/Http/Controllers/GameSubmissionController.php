<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Controllers;

use App\Modules\Catalog\Application\Actions\SubmitGame;
use App\Modules\Catalog\Http\Requests\SubmitGameRequest;
use App\Modules\Catalog\Http\Resources\GameResource;
use App\Modules\Identity\Domain\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

final class GameSubmissionController
{
    public function store(SubmitGameRequest $request, #[CurrentUser] User $owner, SubmitGame $submit): JsonResponse
    {
        return (new GameResource($submit($owner, $request->toInput())))->response()->setStatusCode(201);
    }
}
