<?php

declare(strict_types=1);

namespace App\Modules\Versioning\Http\Resources;

use App\Modules\Versioning\Domain\GameVersion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resumo da versão para as filas do admin.
 *
 * @mixin GameVersion
 */
class VersionResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $failure = $this->fetchFailure();

        return [
            'id' => $this->id,
            'number' => $this->number,
            'status' => $this->status->value,
            'is_new' => $this->status_seen_at === null,
            'repository_url' => $this->repository_url,
            'requested_ref' => $this->requested_ref,
            'commit_sha' => $this->commit_sha,
            'blocked' => is_array($this->validation_report) ? (bool) ($this->validation_report['blocked'] ?? true) : null,
            'fetch_failure' => $failure === null ? null : ['code' => $failure->value, 'message' => $failure->message()],
            'fetched_at' => $this->fetched_at?->toIso8601String(),
            'public_id' => $this->public_id,
            'published_at' => $this->published_at?->toIso8601String(),
            'created_at' => $this->created_at->toIso8601String(),
            'game' => $this->whenLoaded('game', fn (): array => [
                'id' => $this->game->id,
                'name' => $this->game->name,
                'slug' => $this->game->slug,
            ]),
            'requested_by' => $this->whenLoaded('requestedBy', fn (): array => [
                'id' => $this->requestedBy->id,
                'name' => $this->requestedBy->name,
            ]),
        ];
    }
}
