<?php

declare(strict_types=1);

namespace App\Modules\Versioning\Http\Controllers;

use App\Modules\Identity\Domain\User;
use App\Modules\Versioning\Application\Actions\ListMyLatestVersions;
use App\Modules\Versioning\Application\Actions\RequestNewImport;
use App\Modules\Versioning\Http\Resources\OwnerVersionResource;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class MyVersionsController
{
    public function index(#[CurrentUser] User $owner, ListMyLatestVersions $listMyLatestVersions): AnonymousResourceCollection
    {
        return OwnerVersionResource::collection($listMyLatestVersions($owner));
    }

    /** Reenvio pelo dono: nova versão, de volta ao Portão 0. */
    public function store(string $game, #[CurrentUser] User $owner, RequestNewImport $requestNewImport): JsonResponse
    {
        return (new OwnerVersionResource($requestNewImport($owner, $game)))->response()->setStatusCode(201);
    }
}
