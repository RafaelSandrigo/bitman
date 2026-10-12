<?php

declare(strict_types=1);

namespace App\Modules\Versioning\Http\Resources;

use App\Modules\Versioning\Domain\GameVersion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Versão vista pelo dono do jogo: o estado e, quando parou, o motivo (rejeição em algum portão,
 * falha da busca ou violações da política). Não expõe quem decidiu nem a lista de arquivos ignorados.
 *
 * @mixin GameVersion
 */
final class OwnerVersionResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $failure = $this->fetchFailure();
        $violations = is_array($this->validation_report) && is_array($this->validation_report['violations'] ?? null)
            ? $this->validation_report['violations']
            : [];

        return [
            'id' => $this->id,
            'game_id' => $this->game_id,
            'number' => $this->number,
            'status' => $this->status->value,
            'in_progress' => $this->status->isInProgress(),
            'requested_ref' => $this->requested_ref,
            'commit_sha' => $this->commit_sha,
            'rejection_reason' => $this->import_rejection_reason ?? $this->commit_rejection_reason ?? $this->rejection_reason ?? $this->withdrawal_reason,
            'fetch_failure' => $failure === null ? null : ['code' => $failure->value, 'message' => $failure->message()],
            'violations' => array_values($violations),
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
