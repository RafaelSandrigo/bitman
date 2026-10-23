<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Resources;

use App\Modules\Identity\Domain\DeveloperRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Pedido para ser developer (M18). O `user` só vai na visão do admin.
 *
 * @property DeveloperRequest $resource
 */
final class DeveloperRequestResource extends JsonResource
{
    public bool $withUser = false;

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $developerRequest = $this->resource;

        return [
            'id' => $developerRequest->id,
            'user' => $this->when($this->withUser, fn (): array => [
                'id' => $developerRequest->user_id,
                'name' => $developerRequest->user->name,
                'email' => $developerRequest->user->email,
            ]),
            'name' => $developerRequest->name,
            'github_url' => $developerRequest->github_url,
            'bio' => $developerRequest->bio,
            'status' => $developerRequest->status->value,
            'decided_by' => $developerRequest->decidedBy?->name,
            'decided_at' => $developerRequest->decided_at?->toIso8601String(),
            'decision_reason' => $developerRequest->decision_reason,
            'created_at' => $developerRequest->created_at->toIso8601String(),
        ];
    }

    public function forAdmin(): self
    {
        $this->withUser = true;

        return $this;
    }
}
