<?php

declare(strict_types=1);

namespace App\Modules\Versioning\Http\Controllers\Admin;

use App\Modules\Identity\Domain\User;
use App\Modules\Versioning\Application\Actions\ListVersions;
use App\Modules\Versioning\Application\Actions\MarkVersionsSeen;
use App\Modules\Versioning\Application\Actions\RequestNewImport;
use App\Modules\Versioning\Application\Actions\ShowVersion;
use App\Modules\Versioning\Application\Actions\SummarizeVersions;
use App\Modules\Versioning\Http\Requests\ListVersionsRequest;
use App\Modules\Versioning\Http\Requests\MarkVersionsSeenRequest;
use App\Modules\Versioning\Http\Resources\VersionDetailResource;
use App\Modules\Versioning\Http\Resources\VersionResource;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

final class VersionController
{
    public function index(ListVersionsRequest $request, #[CurrentUser] User $actor, ListVersions $listVersions): AnonymousResourceCollection
    {
        return VersionResource::collection($listVersions($actor, $request->status()));
    }

    /** Contadores das filas: total e "novos" por estado. */
    public function summary(#[CurrentUser] User $actor, SummarizeVersions $summarizeVersions): JsonResponse
    {
        return response()->json(['data' => $summarizeVersions($actor)]);
    }

    public function seen(MarkVersionsSeenRequest $request, #[CurrentUser] User $actor, MarkVersionsSeen $markVersionsSeen): Response
    {
        $markVersionsSeen($actor, $request->shown());

        return response()->noContent();
    }

    public function show(string $version, #[CurrentUser] User $actor, ShowVersion $showVersion): VersionDetailResource
    {
        return new VersionDetailResource($showVersion($actor, $version));
    }

    public function store(string $game, #[CurrentUser] User $actor, RequestNewImport $requestNewImport): JsonResponse
    {
        return (new VersionResource($requestNewImport($actor, $game)))->response()->setStatusCode(201);
    }
}
