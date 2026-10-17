<?php

declare(strict_types=1);

namespace App\Modules\Audit\Http\Requests;

use App\Modules\Audit\Application\AuditFilter;
use Illuminate\Foundation\Http\FormRequest;

final class ListAuditEntriesRequest extends FormRequest
{
    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'subject_type' => ['nullable', 'string', 'max:50', 'regex:/^[a-z][a-z_-]*$/'],
            'subject_id' => ['nullable', 'string', 'max:64', 'alpha_dash:ascii'],
            'action' => ['nullable', 'string', 'max:100', 'regex:/^[a-z][a-z-]*\.[a-z][a-z-]*$/'],
            'actor_id' => ['nullable', 'string', 'max:64', 'alpha_dash:ascii'],
            'game_id' => ['nullable', 'string', 'max:64', 'alpha_dash:ascii'],
            'user_id' => ['nullable', 'string', 'max:64', 'alpha_dash:ascii'],
            'cursor' => ['nullable', 'string'],
        ];
    }

    public function filter(): AuditFilter
    {
        return new AuditFilter(
            subjectType: $this->optionalString('subject_type'),
            subjectId: $this->optionalString('subject_id'),
            action: $this->optionalString('action'),
            actorId: $this->optionalString('actor_id'),
            gameId: $this->optionalString('game_id'),
            userId: $this->optionalString('user_id'),
        );
    }

    private function optionalString(string $key): ?string
    {
        $value = $this->validated($key);

        return is_string($value) && $value !== '' ? $value : null;
    }
}
