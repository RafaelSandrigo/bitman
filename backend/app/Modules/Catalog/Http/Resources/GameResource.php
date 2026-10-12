<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Resources;

use App\Modules\Catalog\Domain\Game;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Visão do jogo para o dono e para o admin. O dono (`owner`) só aparece quando carregado (fila do admin).
 *
 * @mixin Game
 */
class GameResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
            'description' => $this->description,
            'author_name' => $this->author_name,
            'thumbnail_url' => '/media/thumbnails/'.$this->thumbnail_path,
            'repository_url' => $this->repository_url,
            'default_ref' => $this->default_ref,
            'status' => $this->status->value,
            'created_at' => $this->created_at->toIso8601String(),
            'owner' => $this->whenLoaded('owner', fn (): array => [
                'id' => $this->owner->id,
                'name' => $this->owner->name,
                'email' => $this->owner->email,
            ]),
        ];
    }
}
