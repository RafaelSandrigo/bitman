<?php

declare(strict_types=1);

namespace App\Modules\Versioning\Http\Controllers;

use App\Modules\Identity\Domain\User;
use App\Modules\Versioning\Application\Actions\ListMyGameVersions;
use App\Modules\Versioning\Application\Actions\ListMyLatestVersions;
use App\Modules\Versioning\Application\Actions\RequestNewImport;
use App\Modules\Versioning\Http\Requests\ResubmitVersionRequest;
use App\Modules\Versioning\Http\Resources\OwnerVersionResource;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class MyVersionsController
{
    public function index(#[CurrentUser] User $owner, ListMyLatestVersions $listMyLatestVersions): AnonymousResourceCollection
    {
        return OwnerVersionResource::collection($listMyLatestVersions($owner));
    }

    /** M2: todas as versões do jogo do dono, com estado e motivo. */
    public function history(string $game, Request $request, #[CurrentUser] User $owner, ListMyGameVersions $listMyGameVersions): JsonResponse
    {
        [$found, $versions] = $listMyGameVersions($owner, $game);

        return response()->json([
            'data' => OwnerVersionResource::collection($versions->items())->resolve($request),
            'meta' => [
                'game' => ['id' => $found->id, 'name' => $found->name, 'slug' => $found->slug],
                'current_page' => $versions->currentPage(),
                'last_page' => $versions->lastPage(),
                'total' => $versions->total(),
            ],
        ]);
    }

    /** Reenvio pelo dono: nova versão, de volta ao Portão 0, com a ref do jogo ou outra (M1). */
    public function store(string $game, ResubmitVersionRequest $request, #[CurrentUser] User $owner, RequestNewImport $requestNewImport): JsonResponse
    {
        return (new OwnerVersionResource($requestNewImport($owner, $game, $request->ref())))->response()->setStatusCode(201);
    }
}
