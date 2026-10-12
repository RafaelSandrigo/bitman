<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Resources;

use App\Modules\Catalog\Application\PublishedGame;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Jogo no catálogo público (I9). Sem dono, repositório nem estado interno.
 *
 * @property PublishedGame $resource
 */
final class PublicGameResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $game = $this->resource->game;

        return [
            'slug' => $game->slug,
            'name' => $game->name,
            'description' => $game->description,
            'author_name' => $game->author_name,
            'thumbnail_url' => '/media/thumbnails/'.$game->thumbnail_path,
            'version_number' => $this->resource->versionNumber,
            'published_at' => $this->resource->publishedAt->toIso8601String(),
            'play_url' => $this->resource->playUrl,
        ];
    }
}
