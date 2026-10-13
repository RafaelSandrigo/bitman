<?php

declare(strict_types=1);

namespace App\Modules\Play\Http\Resources;

use App\Modules\Play\Domain\Score;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Score visto na moderação: jogador, versão, duração da partida, sinalização e ocultação.
 *
 * @property Score $resource
 */
final class AdminScoreResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $score = $this->resource;
        $session = $score->session;

        return [
            'id' => $score->id,
            'game' => ['id' => $score->game_id, 'slug' => $score->game->slug, 'name' => $score->game->name],
            'player' => ['id' => $score->user_id, 'name' => $score->player->name],
            'version_number' => $score->version->number,
            'value' => $score->value,
            'session_seconds' => $session->ended_at === null ? null : (int) $session->started_at->diffInSeconds($session->ended_at),
            'flagged' => $score->flagged,
            'flag_reason' => $score->flag_reason,
            'hidden' => $score->isHidden() ? [
                'by' => $score->hiddenBy?->name,
                'at' => $score->hidden_at?->toIso8601String(),
                'reason' => $score->hide_reason,
            ] : null,
            'created_at' => $score->created_at->toIso8601String(),
        ];
    }
}
