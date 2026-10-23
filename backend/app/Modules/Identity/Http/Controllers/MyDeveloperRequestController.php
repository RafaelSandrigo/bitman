<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers;

use App\Modules\Identity\Application\Actions\RequestDeveloperRole;
use App\Modules\Identity\Domain\DeveloperRequest;
use App\Modules\Identity\Domain\User;
use App\Modules\Identity\Http\Requests\RequestDeveloperRoleRequest;
use App\Modules\Identity\Http\Resources\DeveloperRequestResource;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;

/** M18: o pedido do próprio usuário para ser developer. */
final class MyDeveloperRequestController
{
    /** O pedido mais recente (pendente, aprovado ou rejeitado, com o motivo), ou `null`. */
    public function show(#[CurrentUser] User $user): JsonResponse
    {
        $latest = DeveloperRequest::query()->where('user_id', $user->id)->with('decidedBy')->latest('created_at')->latest('id')->first();

        return response()->json(['data' => $latest === null ? null : new DeveloperRequestResource($latest)]);
    }

    public function store(RequestDeveloperRoleRequest $request, #[CurrentUser] User $user, RequestDeveloperRole $requestDeveloperRole): JsonResponse
    {
        return (new DeveloperRequestResource($requestDeveloperRole($user, $request->application())))->response()->setStatusCode(201);
    }
}
