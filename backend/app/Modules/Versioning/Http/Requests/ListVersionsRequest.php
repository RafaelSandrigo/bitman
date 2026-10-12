<?php

declare(strict_types=1);

namespace App\Modules\Versioning\Http\Requests;

use App\Modules\Versioning\Domain\VersionStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ListVersionsRequest extends FormRequest
{
    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'status' => ['nullable', Rule::enum(VersionStatus::class)],
            'page' => ['nullable', 'integer', 'min:1'],
            'game_id' => ['nullable', 'string', 'ulid'],
        ];
    }

    public function gameId(): ?string
    {
        $gameId = $this->validated('game_id');

        return is_string($gameId) ? strtolower($gameId) : null;
    }

    public function status(): ?VersionStatus
    {
        $status = $this->validated('status');

        return is_string($status) ? VersionStatus::from($status) : null;
    }
}
