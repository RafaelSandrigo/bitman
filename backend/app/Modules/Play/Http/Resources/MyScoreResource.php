<?php

declare(strict_types=1);

namespace App\Modules\Play\Http\Resources;

use App\Modules\Play\Domain\Score;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Uma partida do histórico do jogador. A sinalização (S2) não é exposta; ocultar do ranking, sim.
 *
 * @property Score $resource
 */
final class MyScoreResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $score = $this->resource;

        return [
            'id' => $score->id,
            'game' => ['slug' => $score->game->slug, 'name' => $score->game->name],
            'version_number' => $score->version->number,
            'value' => $score->value,
            'hidden' => $score->isHidden(),
            'created_at' => $score->created_at->toIso8601String(),
        ];
    }
}
