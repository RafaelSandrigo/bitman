<?php

declare(strict_types=1);

namespace App\Modules\Audit\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class ListAuditEntriesRequest extends FormRequest
{
    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'subject_type' => ['nullable', 'string', 'max:50', 'regex:/^[a-z][a-z_-]*$/'],
            'subject_id' => ['nullable', 'string', 'max:64', 'alpha_dash:ascii'],
            'cursor' => ['nullable', 'string'],
        ];
    }

    public function subjectType(): ?string
    {
        return $this->optionalString('subject_type');
    }

    public function subjectId(): ?string
    {
        return $this->optionalString('subject_id');
    }

    private function optionalString(string $key): ?string
    {
        $value = $this->validated($key);

        return is_string($value) && $value !== '' ? $value : null;
    }
}
