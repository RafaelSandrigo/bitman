<?php

declare(strict_types=1);

namespace App\Modules\Versioning\Http\Requests;

use App\Shared\Domain\Git\GitRef;
use App\Shared\Domain\Git\InvalidGitRef;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

/** M1: reenvio com uma branch ou tag opcional (só para esta versão); sem ela, vale a do jogo. */
final class ResubmitVersionRequest extends FormRequest
{
    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'ref' => ['nullable', 'string', function (string $attribute, mixed $value, Closure $fail): void {
                try {
                    GitRef::fromString(is_string($value) ? $value : '');
                } catch (InvalidGitRef $e) {
                    $fail($e->getMessage());
                }
            }],
        ];
    }

    public function ref(): ?GitRef
    {
        $ref = $this->input('ref');

        return is_string($ref) && trim($ref) !== '' ? GitRef::fromString($ref) : null;
    }
}
