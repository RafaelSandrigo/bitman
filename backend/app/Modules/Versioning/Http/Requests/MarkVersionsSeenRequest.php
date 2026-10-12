<?php

declare(strict_types=1);

namespace App\Modules\Versioning\Http\Requests;

use App\Modules\Versioning\Domain\VersionStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** As versões exibidas, cada uma com o estado em que foi vista (no máximo uma página da fila). */
final class MarkVersionsSeenRequest extends FormRequest
{
    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'versions' => ['required', 'array', 'min:1', 'max:25'],
            'versions.*.id' => ['required', 'string', 'ulid'],
            'versions.*.status' => ['required', 'string', Rule::enum(VersionStatus::class)],
        ];
    }

    /** @return array<string, VersionStatus> */
    public function shown(): array
    {
        $shown = [];
        foreach ((array) $this->validated('versions') as $version) {
            if (is_array($version) && is_string($version['id'] ?? null) && is_string($version['status'] ?? null)) {
                $shown[strtolower($version['id'])] = VersionStatus::from($version['status']);
            }
        }

        return $shown;
    }
}
