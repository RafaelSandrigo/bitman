<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Controllers;

use App\Modules\Catalog\Application\Actions\ListPublishedGames;
use App\Modules\Catalog\Http\Resources\PublicGameResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/** Catálogo público: sem login (I9). */
final class GameCatalogController
{
    public function index(ListPublishedGames $listPublishedGames): AnonymousResourceCollection
    {
        return PublicGameResource::collection($listPublishedGames());
    }

    public function show(string $slug, ListPublishedGames $listPublishedGames): PublicGameResource
    {
        return new PublicGameResource($listPublishedGames->bySlug($slug) ?? abort(404));
    }
}
